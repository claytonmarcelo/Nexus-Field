<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A conta raiz é a identidade administrativa do projeto: ela tem o domínio inteiro do
 * sistema, não pode ser apagada, desativada, remanejada, nem deixar de ser raiz por
 * request nenhum, e essa proteção mora no model e no schema — não na tela que esconde
 * o botão. O endereço vigente vem de `DatabaseSeeder::ROOT_EMAIL`.
 */
class RootAccountTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private function raiz(): User
    {
        // O seeder lê o e-mail do ambiente. O teste fixa a constante para não
        // depender do `.env` de quem roda a suíte.
        $this->mudarAmbientePara(DatabaseSeeder::ROOT_EMAIL);

        $this->seed(DatabaseSeeder::class);

        return User::query()->where('email', DatabaseSeeder::ROOT_EMAIL)->firstOrFail();
    }

    public function test_a_conta_raiz_e_semeada_marcada_no_schema_e_nao_se_duplica(): void
    {
        $raiz = $this->raiz();

        $this->assertTrue($raiz->is_root);
        $this->assertSame('active', $raiz->status);
        $this->assertSame('nexusfield', $raiz->company->slug);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('is_root', true)->count());
        $this->assertSame(1, User::query()->where('email', DatabaseSeeder::ROOT_EMAIL)->count());
    }

    public function test_nenhum_request_consegue_transformar_alguem_na_conta_raiz(): void
    {
        $empresa = $this->makeCompany();
        $this->seedPermissions();

        $usuario = User::create([
            'company_id' => $empresa->id,
            'name' => 'Quem se achou raiz',
            'email' => 'invasor@test.local',
            'password' => 'Senha-Forte-123',
            'status' => 'active',
            'is_root' => true,
        ]);

        $this->assertFalse((bool) $usuario->is_root);
        $this->assertFalse((bool) $usuario->fresh()->is_root);
    }

    public function test_a_conta_raiz_nao_pode_ser_excluida(): void
    {
        $raiz = $this->raiz();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não pode ser excluída');

        $raiz->delete();
    }

    public function test_a_conta_raiz_nao_pode_ser_desativada(): void
    {
        $raiz = $this->raiz();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ser desativada');

        $raiz->status = 'inactive';
        $raiz->save();
    }

    public function test_a_conta_raiz_nao_pode_ter_e_mail_ou_empresa_trocados(): void
    {
        $raiz = $this->raiz();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ter o e-mail trocado');

        $raiz->email = 'outro@endereco.local';
        $raiz->save();
    }

    public function test_a_conta_raiz_nao_pode_deixar_de_ser_raiz(): void
    {
        $raiz = $this->raiz();

        // Sem esta guarda bastaria desligar a bandeira para que a linha seguinte
        // passasse no `deleting` e a conta sumisse do sistema.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('perder a condição de conta raiz');

        $raiz->forceFill(['is_root' => false])->save();
    }

    public function test_a_troca_de_senha_da_conta_raiz_continua_possivel(): void
    {
        $raiz = $this->raiz();

        $raiz->password = 'Nova-Senha-2026';
        $raiz->save();

        $this->post(route('login'), [
            'email' => DatabaseSeeder::ROOT_EMAIL,
            'password' => 'Nova-Senha-2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_o_dominio_absoluto_nao_depende_de_papel(): void
    {
        $raiz = $this->raiz();
        $raiz->roles()->detach();

        $recarregada = $raiz->fresh();

        $todas = Permission::query()->pluck('slug')->all();
        $this->assertNotEmpty($todas);
        $this->assertCount(count($todas), $recarregada->permissionSlugs());
        $this->assertTrue($recarregada->hasPermission('financial.delete'));
        $this->assertTrue($recarregada->hasPermission('users.delete'));
    }

    public function test_a_raiz_abre_o_painel_com_todos_os_blocos(): void
    {
        $html = $this->actingAs($this->raiz())->get(route('dashboard'))->assertOk()->getContent();

        foreach ([
            'Ordens na fila', 'Agenda de hoje', 'Chamados na fila', 'Equipe',
            'Estoque para repor', 'Carteira financeira', 'Notificações', 'Base consultada',
        ] as $bloco) {
            $this->assertStringContainsString($bloco, $html, "O painel da raiz perdeu o bloco [{$bloco}].");
        }
    }

    public function test_as_guardas_valem_somente_para_a_conta_raiz(): void
    {
        $empresa = $this->makeCompany();
        $this->seedPermissions();

        $comum = $this->makeUser('employee', $empresa, 'comum@test.local');
        $comum->status = 'inactive';
        $comum->save();

        $this->assertSame('inactive', $comum->fresh()->status);

        $comum->delete();
        $this->assertSoftDeleted($comum);
    }

    public function test_o_endereco_aposentado_da_raiz_nao_volta_a_ser_semeado(): void
    {
        $this->raiz();

        $this->assertSame(0, User::query()->where('email', DatabaseSeeder::RETIRED_ROOT_EMAIL)->count());

        // O `.env` manda no endereço da raiz, então é por ele que o aposentado poderia
        // voltar. A recusa tem que estar no seeder, não na tela que não mostra o campo.
        $this->mudarAmbientePara(DatabaseSeeder::RETIRED_ROOT_EMAIL);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('aposentado por medida de segurança');

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * `putenv` sobrevive ao método e à classe: sem o `tearDown` devolvendo a constante,
     * a próxima classe que semeasse herdaria o endereço recusado.
     */
    private function mudarAmbientePara(string $email): void
    {
        putenv('SEED_ADMIN_EMAIL='.$email);
        $_ENV['SEED_ADMIN_EMAIL'] = $_SERVER['SEED_ADMIN_EMAIL'] = $email;
    }

    protected function tearDown(): void
    {
        $this->mudarAmbientePara(DatabaseSeeder::ROOT_EMAIL);

        parent::tearDown();
    }
}
