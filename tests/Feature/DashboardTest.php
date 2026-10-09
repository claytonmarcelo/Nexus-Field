<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * O painel só pode desenhar o que o MySQL respondeu, e só pode consultar o que o
 * papel do usuário alcança. Estes testes amarram as duas pontas: o número da tela
 * é o número do banco, e o bloco sem permissão não chega a ter a query montada.
 */
class DashboardTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cada_kpi_do_painel_e_a_contagem_que_o_banco_faz(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $this->ordem($empresa, $cliente, ['number' => 'OS-01', 'status' => 'open',
            'scheduled_starts_at' => '2026-10-10 08:00', 'scheduled_ends_at' => '2026-10-10 10:00']);
        $this->ordem($empresa, $cliente, ['number' => 'OS-02', 'status' => 'in_progress',
            'scheduled_starts_at' => '2026-10-08 14:00', 'scheduled_ends_at' => '2026-10-08 16:00']);
        $this->ordem($empresa, $cliente, ['number' => 'OS-03', 'status' => 'open',
            'scheduled_starts_at' => '2026-10-06 09:00', 'scheduled_ends_at' => '2026-10-06 11:00']);
        $this->ordem($empresa, $cliente, ['number' => 'OS-04', 'status' => 'completed',
            'completed_at' => '2026-10-07 18:00']);
        $this->ordem($empresa, $cliente, ['number' => 'OS-05', 'status' => 'completed',
            'completed_at' => '2026-09-28 18:00']);
        $this->ordem($empresa, $cliente, ['number' => 'OS-06', 'status' => 'canceled']);

        $this->chamado($empresa, $cliente, ['protocol' => 'CH-01', 'priority' => 'urgent']);
        $this->chamado($empresa, $cliente, ['protocol' => 'CH-02']);
        $this->chamado($empresa, $cliente, ['protocol' => 'CH-03', 'status' => 'resolved',
            'resolved_at' => '2026-10-05 15:00']);

        Appointment::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'title' => 'Visita técnica',
            'starts_at' => '2026-10-08 15:00',
            'ends_at' => '2026-10-08 16:00',
        ]);

        Technician::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Marina Prado',
            'status' => 'available',
        ]);

        $pendente = FinancialRecord::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'type' => 'revenue',
            'category' => 'servico',
            'description' => 'Cobrança da OS-01',
            'amount' => 100,
            'due_date' => '2026-10-11',
            'status' => 'pending',
        ]);
        FinancialRecord::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'type' => 'revenue',
            'category' => 'servico',
            'description' => 'Cobrança vencida',
            'amount' => 50,
            'due_date' => '2026-10-01',
            'status' => 'pending',
        ]);
        $quitada = FinancialRecord::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'type' => 'revenue',
            'category' => 'servico',
            'description' => 'Já quitada',
            'amount' => 40,
            'due_date' => '2026-10-02',
            'status' => 'paid',
            'occurred_at' => '2026-10-02',
        ]);
        Payment::query()->create([
            'company_id' => $empresa->id,
            'financial_record_id' => $quitada->id,
            'amount' => 40,
            'method' => 'pix',
            'paid_at' => '2026-10-02',
        ]);
        FinancialRecord::query()->create([
            'company_id' => $empresa->id,
            'type' => 'expense',
            'category' => 'combustivel',
            'description' => 'Despesa do mês',
            'amount' => 20,
            'due_date' => '2026-10-03',
            'status' => 'paid',
            'occurred_at' => '2026-10-03',
        ]);

        $produto = Product::query()->create([
            'company_id' => $empresa->id,
            'sku' => 'HY-400',
            'name' => 'Filtro depurador de coifa',
            'unit' => 'un',
            'reorder_point' => 8,
            'status' => 'active',
        ]);
        $this->movimentacao($empresa, $produto, 'purchase', 10);
        $this->movimentacao($empresa, $produto, 'load', 3);
        $this->movimentacao($empresa, $produto, 'consume', 5);
        $estavel = Product::query()->create([
            'company_id' => $empresa->id,
            'sku' => 'EL-200',
            'name' => 'Resistência de aquecimento',
            'unit' => 'un',
            'reorder_point' => 2,
            'status' => 'active',
        ]);
        $this->movimentacao($empresa, $estavel, 'purchase', 10);

        $html = $this->abrindoPainel($usuario);

        $this->assertSame([
            'Ordens abertas' => '3',
            'Concluídas em 7 dias' => '1',
            'Com prazo vencido' => '1',
            'Agenda de hoje' => '1',
            'Chamados abertos' => '2',
            'Técnicos em campo agora' => '0',
            'Clientes ativos' => '1',
            'Itens abaixo do ponto de reposição' => '1',
            // As quatro movimentações desta ficha são de 2026-10-05: o cartão conta o
            // dia de hoje, e um número aqui que não fosse 0 estaria somando o livro
            // inteiro.
            'Movimentações de hoje' => '0',
            'A receber' => 'R$ 150,00',
            'Recebido no mês' => 'R$ 40,00',
            'Despesa do mês' => 'R$ 20,00',
            'Notificações sem leitura' => '0',
        ], $this->kpis($html));

        // O vencido é soma do que está pendente e atrasado, não um destaque fixo.
        $this->assertStringContainsString('R$ 50,00 vencidos', $html);
        // Saldo central: compra 10 - carga 3 = 7. O consumo sai do técnico, não do centro.
        $this->assertStringContainsString('7,00</span> <span class="nf-text-muted-2">/ 8,00', $html);
        $this->assertStringNotContainsString('EL-200', $html);
        $this->assertStringContainsString('OS-03', $html);
        $this->assertStringNotContainsString('OS-06', $html);
        $this->assertStringContainsString('Urgente', $html);
        $this->assertStringContainsString('Visita técnica', $html);
    }

    public function test_bloco_sem_permissao_nao_aparece_e_a_query_nao_roda(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $cliente = $this->cliente($empresa, 'Cliente da conta');
        $this->ordem($empresa, $cliente, ['number' => 'OS-01']);
        $this->chamado($empresa, $cliente, ['protocol' => 'CH-01']);
        $usuario = $this->makeUser('client', $empresa, 'cliente@test.local');

        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas) {
            $consultas[] = $consulta->sql;
        });

        $html = $this->abrindoPainel($usuario);

        $this->assertStringContainsString('Ordens na fila', $html);
        $this->assertStringContainsString('Chamados na fila', $html);

        foreach (['Carteira financeira', 'Estoque para repor', 'Equipe', 'Base consultada', 'Agenda de hoje'] as $bloco) {
            $this->assertStringNotContainsString($bloco, $html, "O bloco [{$bloco}] apareceu para quem não tem a permissão.");
        }

        $this->assertNotEmpty($consultas, 'Sem nenhuma consulta registrada o teste acima não provaria nada.');

        foreach (['financial_records', 'payments', 'stock_movements', 'appointments'] as $tabela) {
            foreach ($consultas as $sql) {
                $this->assertStringNotContainsString(
                    $tabela,
                    $sql,
                    "Sem permissão, a consulta em [{$tabela}] não deveria nem ter sido montada."
                );
            }
        }

        // A ficha do próprio usuário é lida pelo escopo de ordens — permissão que esta conta
        // tem, e é um `select *` com `limit 1`. O que não pode existir é consulta que conta
        // ou agrupa o quadro sem `technicians.view`: aí já é o bloco de equipe respondendo
        // pela operação inteira na tela de quem não o viu.
        foreach ($consultas as $sql) {
            if (str_contains($sql, 'technicians')) {
                $this->assertStringNotContainsString(
                    'count(',
                    mb_strtolower($sql),
                    'O bloco de equipe montou uma contagem sobre `technicians` para uma conta sem a permissão.'
                );
            }
        }
    }

    public function test_empresa_sem_dados_mostra_estado_vazio_em_vez_de_numero_inventado(): void
    {
        [, $usuario] = $this->empresaComAdmin();

        $html = $this->abrindoPainel($usuario);

        foreach ([
            'Nenhuma ordem agendada',
            'Sem ordens registradas',
            'Nenhum chamado aberto',
            'Dia sem compromissos',
            'Nenhum técnico cadastrado',
            'Nenhum item abaixo do ponto',
        ] as $estado) {
            $this->assertStringContainsString($estado, $html, "Faltou o estado vazio [{$estado}].");
        }

        $this->assertSame('0', $this->kpis($html)['Ordens abertas']);
    }

    public function test_dados_da_outra_empresa_nao_entram_no_painel(): void
    {
        [$alfa, $usuarioAlfa] = $this->empresaComAdmin();
        $clienteAlfa = $this->cliente($alfa, 'Padaria Sant’Anna');
        $this->ordem($alfa, $clienteAlfa, [
            'number' => 'OS-ALFA',
            'scheduled_starts_at' => '2026-10-09 08:00',
            'scheduled_ends_at' => '2026-10-09 10:00',
        ]);

        $beta = $this->makeCompany('beta');
        $clienteBeta = $this->cliente($beta, 'Cliente Beta');
        foreach (['OS-B1', 'OS-B2', 'OS-B3', 'OS-B4'] as $numero) {
            $this->ordem($beta, $clienteBeta, [
                'number' => $numero,
                'scheduled_starts_at' => '2026-10-09 08:00',
                'scheduled_ends_at' => '2026-10-09 10:00',
            ]);
        }

        $html = $this->abrindoPainel($usuarioAlfa);

        $this->assertSame('1', $this->kpis($html)['Ordens abertas']);
        $this->assertStringContainsString('OS-ALFA', $html);
        $this->assertStringNotContainsString('OS-B1', $html);
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    private function abrindoPainel(User $usuario): string
    {
        $html = $this->actingAs($usuario)->get(route('dashboard'))->assertOk()->getContent();

        // Colapsa a indentação do Blade: as asserções falam do markup, não de quantos
        // espaços o template trocou.
        return preg_replace('/\s+/', ' ', $html);
    }

    private function cliente(Company $empresa, string $nome): Client
    {
        return Client::query()->create([
            'company_id' => $empresa->id,
            'name' => $nome,
            'status' => 'active',
        ]);
    }

    private function ordem(Company $empresa, Client $cliente, array $atributos = []): ServiceOrder
    {
        return ServiceOrder::query()->create(array_merge([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'number' => 'OS-'.Str::upper(Str::random(6)),
            'title' => 'Manutenção preventiva',
            'priority' => 'normal',
            'status' => 'open',
        ], $atributos));
    }

    private function chamado(Company $empresa, Client $cliente, array $atributos = []): Ticket
    {
        return Ticket::query()->create(array_merge([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'protocol' => 'CH-'.Str::upper(Str::random(6)),
            'subject' => 'Equipamento sem refrigeração',
            'category' => 'refrigeracao',
            'priority' => 'normal',
            'status' => 'open',
            'opened_at' => '2026-10-07 09:00',
        ], $atributos));
    }

    private function movimentacao(Company $empresa, Product $produto, string $tipo, float $quantidade): StockMovement
    {
        return StockMovement::query()->create([
            'company_id' => $empresa->id,
            'product_id' => $produto->id,
            'type' => $tipo,
            'quantity' => $quantidade,
            'recorded_at' => '2026-10-05 09:00',
        ]);
    }

    /**
     * Lê os KPIs pela marcação e não pela variável do controller: o que tem que
     * bater com o banco é o número que chegou na tela.
     *
     * @return array<string, string>
     */
    private function kpis(string $html): array
    {
        preg_match_all(
            '/<p class="nf-kpi-label mb-1">([^<]+)<\/p> <p class="nf-kpi-value[^"]*">([^<]*)<\/p>/',
            $html,
            $correspondencias,
            PREG_SET_ORDER
        );

        $kpis = [];
        foreach ($correspondencias as $linha) {
            $kpis[trim($linha[1])] = trim($linha[2]);
        }

        return $kpis;
    }
}
