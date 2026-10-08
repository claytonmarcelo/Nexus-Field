<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A identidade raiz trocou de endereço. Quem sobe o sistema do zero recebe o endereço
 * vigente pela seed; quem já tem banco semeado recebe pela migration. Os dois caminhos
 * têm que parar no mesmo lugar: uma linha de raiz só, no endereço novo, e nada no
 * sistema apontando para o aposentado.
 */
class RootAccountEmailSwapTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_08_000004_replace_root_account_email.php');
    }

    private function linhaNoEnderecoAposentado(): int
    {
        $empresa = $this->makeCompany();

        return DB::table('users')->insertGetId([
            'company_id' => $empresa->id,
            'name' => 'Administrador Nexus-Field',
            'email' => DatabaseSeeder::RETIRED_ROOT_EMAIL,
            'password' => 'hash-que-nao-importa-aqui',
            'status' => 'active',
            'is_root' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_troca_renomeia_a_linha_e_fica_com_o_mesmo_id(): void
    {
        $id = $this->linhaNoEnderecoAposentado();

        $this->migration()->up();

        $depois = DB::table('users')->where('id', $id)->first();

        $this->assertNotNull($depois, 'A linha da raiz sumiu em vez de trocar de endereço.');
        $this->assertSame(DatabaseSeeder::ROOT_EMAIL, $depois->email);
        $this->assertSame(1, (int) $depois->is_root);

        // O `id` preservado é a prova de que o histórico sobrevive: papéis, auditoria e
        // notificações pendurados na conta raiz continuam na mesma linha.
        $this->assertSame(0, DB::table('users')->where('email', DatabaseSeeder::RETIRED_ROOT_EMAIL)->count());
        $this->assertSame(1, DB::table('users')->where('is_root', true)->count());
    }

    public function test_a_troca_duas_vezes_nao_dobra_nem_apaga_a_raiz(): void
    {
        $this->linhaNoEnderecoAposentado();

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(DatabaseSeeder::ROOT_EMAIL, DB::table('users')->value('email'));
    }

    public function test_banco_novo_passa_pela_troca_sem_nada_para_fazer(): void
    {
        $this->migration()->up();

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_conflito_de_endereco_recusa_em_vez_de_escolher_quem_perde(): void
    {
        $empresa = $this->makeCompany();

        DB::table('users')->insert([
            'company_id' => $empresa->id,
            'name' => 'Quem ocupou o endereço novo',
            'email' => DatabaseSeeder::ROOT_EMAIL,
            'password' => 'hash-que-nao-importa-aqui',
            'status' => 'active',
            'is_root' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'company_id' => $empresa->id,
            'name' => 'Administrador Nexus-Field',
            'email' => DatabaseSeeder::RETIRED_ROOT_EMAIL,
            'password' => 'hash-que-nao-importa-aqui',
            'status' => 'active',
            'is_root' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('resolva o conflito antes de migrar');

        $this->migration()->up();
    }

    public function test_o_rollback_e_recusado_por_que_recolocaria_o_endereco_aposentado(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('irreversível');

        $this->migration()->down();
    }
}
