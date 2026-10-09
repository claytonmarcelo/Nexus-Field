<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\TenantContext;
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
     *
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
        (new DemoSeeder)->run();
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

    /**
     * O medidor de pontualidade do painel só vale se a casa tiver as duas histórias
     * para contar. Uma demonstração em que toda entrega atrasa (ou nenhuma) desenha
     * um anel que não mede nada — é fixture de tela lendo um caso só.
     */
    public function test_a_demonstracao_da_o_contraste_que_o_medidor_de_pontualidade_precisa(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('email', 'admin.demo@nexusfield.local')->firstOrFail();
        TenantContext::resolveFromUser($admin);

        $medidor = (new DashboardMetrics($admin))->toArray()['ordens']['pontualidade'];

        $this->assertNotNull($medidor, 'Sem conclusão a demonstração não dá o que o medidor medir.');
        $this->assertGreaterThan(0, $medidor['no_prazo'], 'Nenhuma entrega dentro do prazo: o anel só saberia mostrar vermelho.');
        $this->assertLessThan($medidor['total'], $medidor['no_prazo'], 'Toda entrega dentro do prazo: o anel só saberia mostrar verde.');
        $this->assertContains($medidor['tom'], ['done', 'waiting', 'canceled'], 'O tom do anel vem do registro único da casa.');

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('class="nf-gauge tone-'.$medidor['tom'].'"', $html,
            'O painel tem de desenhar o anel com o tom que a proporção da demonstração pediu.');
        $this->assertStringContainsString($medidor['no_prazo'].' de '.$medidor['total'].' ordens concluídas',
            preg_replace('/\s+/', ' ', $html));
    }

    /**
     * A demonstração é uma história, não um monte de linhas: a hora em que a ordem
     * terminou é a mesma hora em que o estado virou "concluído" na trilha, em que o
     * técnico registrou a saída do endereço e em que a comissão dele foi liberada.
     * Estas três amarras são o que faz o medidor de pontualidade do painel contar a
     * verdade que a ficha mostra.
     */
    public function test_a_entrega_de_cada_ordem_e_a_mesma_hora_em_todos_os_lugares(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $concluidas = ServiceOrder::query()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->with(['statusHistory', 'checkins', 'assignments'])
            ->get();

        $this->assertGreaterThan(0, $concluidas->count());

        foreach ($concluidas as $ordem) {
            $trilha = $ordem->statusHistory
                ->firstWhere('to_status', 'completed');

            $this->assertNotNull($trilha, "{$ordem->number} terminou sem passar pela trilha de estado.");
            $this->assertSame(
                $ordem->completed_at->toDateTimeString(),
                $trilha->created_at->toDateTimeString(),
                "{$ordem->number}: a trilha conta uma hora de entrega e a ficha conta outra."
            );

            $visita = $ordem->checkins->firstWhere('checkout_at', '!=', null);

            if ($visita !== null) {
                $this->assertSame(
                    $ordem->completed_at->toDateTimeString(),
                    $visita->checkout_at->toDateTimeString(),
                    "{$ordem->number}: o técnico saiu do endereço antes de a ordem ser dada por concluída."
                );
                $this->assertTrue($visita->checkin_at->lte($visita->checkout_at),
                    "{$ordem->number}: chegada depois da saída.");
            }

            foreach ($ordem->assignments as $comissao) {
                if ($comissao->released_at !== null) {
                    $this->assertTrue($comissao->released_at->gte($ordem->completed_at),
                        "{$ordem->number}: a comissão foi liberada antes da entrega.");
                }
            }
        }
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
