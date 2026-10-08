<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fixture de tela é útil, mas não pode se misturar com a operação nem existir onde
 * ela roda de verdade: produção recusa, e tudo que o DemoSeeder cria fica preso na
 * empresa "nexusfield-demo".
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Tabelas que carregam a empresa na própria linha. */
    private const TABELAS = [
        'clients', 'technicians', 'teams', 'products', 'services',
        'service_orders', 'service_order_checkins', 'tickets', 'appointments',
        'financial_records', 'payments', 'stock_movements', 'notifications',
    ];

    /**
     * Tabelas-filhas que não têm company_id de propósito: a empresa vem do pai.
     * @var array<string, array{string, string}> tabela => [tabela do pai, FK]
     */
    private const FILHAS = [
        'service_order_items' => ['service_orders', 'service_order_id'],
        'service_order_status_history' => ['service_orders', 'service_order_id'],
        'ticket_comments' => ['tickets', 'ticket_id'],
    ];

    public function test_recusa_rodar_em_producao(): void
    {
        // O guard lê app()->environment(), que vem do binding 'env' do container —
        // mexer em config('app.env') não engana o seeder.
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder não roda em produção');

        // Chamado direto, não via artisan: em produção o `db:seed` já pede
        // confirmação antes de qualquer seeder, e a prova seria do prompt do
        // framework, não da nossa regra.
        (new DemoSeeder())->run();
    }

    public function test_exige_o_catalogo_de_permissoes_antes_dele(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rode DatabaseSeeder antes');

        $this->seed(DemoSeeder::class);
    }

    public function test_toda_a_demonstracao_fica_na_empresa_demo_nada_na_real(): void
    {
        $this->seed(DatabaseSeeder::class);
        $real = Company::query()->where('slug', 'nexusfield')->firstOrFail();

        $this->seed(DemoSeeder::class);
        $demo = Company::query()->where('slug', DemoSeeder::COMPANY_SLUG)->firstOrFail();

        $this->assertNotSame($real->id, $demo->id);

        foreach ($this->tabelasPopuladas() as $tabela) {
            $this->assertGreaterThan(
                0,
                $this->contagemNaEmpresa($tabela, $demo->id),
                "A demonstração não populou [{$tabela}], que o painel consulta."
            );
            $this->assertSame(
                0,
                $this->contagemNaEmpresa($tabela, $real->id),
                "A empresa real recebeu linha de demonstração em [{$tabela}]."
            );
        }
    }

    public function test_rodar_de_novo_recria_a_demonstracao_sem_duplicar(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $primeira = $this->contagemDaDemo();
        $ordens = ServiceOrder::query()->count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($primeira, $this->contagemDaDemo(), 'Rodar o seeder de novo duplicou a demonstração.');
        $this->assertSame($ordens, ServiceOrder::query()->count());
    }

    public function test_o_painel_abre_com_a_demonstracao_e_mostra_o_numero_do_banco(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        $demo = Company::query()->where('slug', DemoSeeder::COMPANY_SLUG)->firstOrFail();

        $admin = User::query()->where('email', 'admin.demo@nexusfield.local')->firstOrFail();
        $this->assertSame($demo->id, $admin->company_id);

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Ordens na fila', $html);
        $this->assertStringContainsString('OS-2026-', $html, 'O painel mostrou a demonstração? O número tem vir do banco.');
        $this->assertStringContainsString('Carteira financeira', $html);

        $tecnico = User::query()->where('email', 'campo.demo@nexusfield.local')->firstOrFail();
        $this->assertStringNotContainsString(
            'Carteira financeira',
            $this->actingAs($tecnico)->get(route('dashboard'))->assertOk()->getContent()
        );
    }

    /** Toda tabela que o seeder escreve, própria ou filha de uma que tem empresa. */
    private function tabelasPopuladas(): array
    {
        return [...self::TABELAS, ...array_keys(self::FILHAS)];
    }

    /**
     * Filha sem company_id é contada pela empresa do pai, que é exatamente como o
     * modelo resolve o tenant.
     */
    private function contagemNaEmpresa(string $tabela, int $empresa): int
    {
        if (! isset(self::FILHAS[$tabela])) {
            return DB::table($tabela)->where('company_id', $empresa)->count();
        }

        [$pai, $fk] = self::FILHAS[$tabela];

        return DB::table($tabela)
            ->join($pai, "{$tabela}.{$fk}", '=', "{$pai}.id")
            ->where("{$pai}.company_id", $empresa)
            ->count();
    }

    /** @return array<string, int> */
    private function contagemDaDemo(): array
    {
        $demo = Company::query()->where('slug', DemoSeeder::COMPANY_SLUG)->firstOrFail();

        $contagem = [];
        foreach ($this->tabelasPopuladas() as $tabela) {
            $contagem[$tabela] = $this->contagemNaEmpresa($tabela, $demo->id);
        }

        return $contagem;
    }
}
