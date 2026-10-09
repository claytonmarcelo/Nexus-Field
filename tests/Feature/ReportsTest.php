<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Relatorio;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 18 fecha o período. O que estes testes amarram é a régua, não a decoração:
 * cada número do fechado tem que ser o número que o próprio módulo calcula quando
 * grava o fato — `pagoSql()` na carteira, `totalPorOrdemSql()` na ordem,
 * `prazoSql()` no chamado, `centralBalanceQuery()` no estoque. Em volta disso fica
 * o que a tela promete: o período é resolvido antes de consultar (padrão, lado que
 * falta, inversão e teto com aviso), o CSV sai com as mesmas linhas e a mesma ordem
 * da tabela, e ler o fechado de um módulo exige ler o módulo — não é botão
 * escondido, é 403.
 */
class ReportsTest extends TestCase
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

    public function test_as_rotas_de_fechamento_são_cinco_telas_e_uma_exportação(): void
    {
        $rotas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($rota) => str_starts_with($rota->uri(), 'relatorios'))
            ->map(fn ($rota) => $rota->methods()[0].' /'.$rota->uri())
            ->unique()
            ->sort(SORT_STRING)
            ->values()
            ->all();

        // A exportação é uma rota só com a chave constrained: `/{relatorio}/exportar`
        // sem o `where` aceitaria qualquer palavra e o 404 do controller seria o único
        // guarda. Com o `where`, rota que não existe é 404 antes de entrar no código.
        $this->assertSame([
            'GET /relatorios',
            'GET /relatorios/chamados',
            'GET /relatorios/estoque',
            'GET /relatorios/financeiro',
            'GET /relatorios/operacao',
            'GET /relatorios/{relatorio}/exportar',
        ], $rotas);
    }

    public function test_o_período_padrão_do_fechamento_é_o_mês_corrente(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        // O hub soma dinheiro que mudou de mão, não conta prevista: sem pagamento
        // dentro da janela, o cartão de receita fica em zero mesmo com conta vencendo.
        $outubro = $this->conta($empresa, ['amount' => 100, 'due_date' => '2026-10-05']);
        $this->pagando($empresa, $outubro, 100, '2026-10-06', 'pix');

        $setembro = $this->conta($empresa, ['amount' => 900, 'due_date' => '2026-09-28', 'category' => 'contrato_mensal']);
        $this->pagando($empresa, $setembro, 900, '2026-09-28', 'pix');

        $html = $this->abrindoFechamento($admin);

        $this->assertStringContainsString('01/10/2026 a 08/10/2026 · 8 dias', $html);
        $this->assertStringContainsString('R$ 100,00', $html);
        $this->assertStringNotContainsString('R$ 900,00', $html, 'Setembro pago não entra no fechado de outubro.');
    }

    public function test_o_lado_que_falta_no_período_ganha_o_que_o_outro_pediu(): void
    {
        [, $admin] = $this->empresaComAdmin();

        // Só o fim: um mês para trás a partir dele.
        $this->assertStringContainsString(
            '06/09/2026 a 05/10/2026 · 30 dias',
            $this->abrindoFechamento($admin, ['fim' => '2026-10-05']),
        );

        // Só o início: até hoje, porque o período que ainda não aconteceu não fecha.
        $this->assertStringContainsString(
            '15/09/2026 a 08/10/2026 · 24 dias',
            $this->abrindoFechamento($admin, ['inicio' => '2026-09-15']),
        );

        // Invertido: a mesma janela, trocada em vez de devolver tabela vazia.
        $this->assertStringContainsString(
            '01/10/2026 a 05/10/2026 · 5 dias',
            $this->abrindoFechamento($admin, ['inicio' => '2026-10-05', 'fim' => '2026-10-01']),
        );
    }

    public function test_a_janela_acima_do_teto_é_cortada_com_o_que_ficou_de_fora_escrito(): void
    {
        [, $admin] = $this->empresaComAdmin();

        $html = $this->abrindoFechamento($admin, ['inicio' => '2025-01-01', 'fim' => '2026-10-08']);

        $this->assertStringContainsString('08/10/2025 a 08/10/2026 · 366 dias', $html);
        $this->assertStringContainsString('A janela pedida tinha 646 dias', $html);
        $this->assertStringContainsString('o início foi trazido para 08/10/2025', $html);
        $this->assertStringContainsString('Exporte em duas janelas', $html);
    }

    public function test_o_financeiro_soma_a_conta_pelo_vencimento_e_o_dinheiro_pela_data_do_caixa(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        // Vence em outubro e recebe em outubro: entra nas duas somas.
        $outubro = $this->conta($empresa, ['amount' => 300, 'due_date' => '2026-10-04']);
        $this->pagando($empresa, $outubro, 300, '2026-10-04', 'pix');

        // Vence em outubro, recebe em novembro: conta do período, dinheiro de outro mês.
        $this->conta($empresa, ['amount' => 200, 'due_date' => '2026-10-06', 'category' => 'emergencia']);

        // Vence em setembro, recebe em outubro: dinheiro deste período vindo de conta
        // que não está nesta tabela — é exatamente a diferença que o relatório mostra.
        $setembro = $this->conta($empresa, [
            'amount' => 150,
            'type' => FinancialRecord::EXPENSE,
            'category' => 'deslocamento',
            'due_date' => '2026-09-30',
        ]);
        $this->pagando($empresa, $setembro, 150, '2026-10-02', 'fuel_card');

        $tela = $this->abrindoTela($admin, 'financeiro');

        $this->assertStringContainsString('01/10/2026 a 08/10/2026 · 8 dias', $tela);
        // O previsto do período são as duas receitas que vencem em outubro: 500.
        $this->assertStringContainsString('R$ 500,00', $tela);
        // Baixado delas: só os 300 pagos dentro do mês.
        $this->assertStringContainsString('R$ 300,00', $tela);
        $this->assertStringContainsString('R$ 200,00', $tela);
        // O caixa do período é 300 + 150, vindo de duas contas com vencimentos diferentes.
        $this->assertStringContainsString('R$ 450,00', $tela);
        $this->assertStringContainsString('Visita técnica avulsa', $tela);
        $this->assertStringContainsString('Atendimento de emergência', $tela);
        $this->assertStringContainsString('Deslocamento e combustível', $tela);
        // A forma de pagamento é a outra metade do mesmo dinheiro.
        $this->assertStringContainsString('PIX', $tela);
        $this->assertStringContainsString('R$ 300,00', $tela);
    }

    public function test_o_relatório_de_financeiro_filtra_pelo_lado_do_caixa_escolhido_na_tela(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estaConta = $this->conta($empresa, ['amount' => 120, 'due_date' => '2026-10-03']);
        $estaDespesa = $this->conta($empresa, [
            'amount' => 80,
            'type' => FinancialRecord::EXPENSE,
            'category' => 'pessoal',
            'due_date' => '2026-10-03',
        ]);

        $receita = $this->abrindoTela($admin, 'financeiro', ['tipo' => FinancialRecord::REVENUE]);
        $this->assertStringContainsString('R$ 120,00', $receita);
        $this->assertStringNotContainsString('Pessoal', $receita);

        $despesa = $this->abrindoTela($admin, 'financeiro', ['tipo' => FinancialRecord::EXPENSE]);
        $this->assertStringContainsString('R$ 80,00', $despesa);
        $this->assertStringNotContainsString('Visita técnica avulsa', $despesa);

        // O recorte vai junto na exportação, senão o arquivo discordaria da tela.
        $csv = $this->exportando($admin, 'financeiro', ['tipo' => FinancialRecord::EXPENSE]);
        $linhas = $this->csv($csv);
        $this->assertCount(2, $linhas, 'Uma linha de cabeçalho e uma de despesa.');
        $this->assertSame('Despesa', $linhas[1][0]);
        $this->assertSame('Pessoal', $linhas[1][1]);
    }

    public function test_a_operação_peça_o_tempo_pela_soma_e_pela_contagem_não_pela_média_das_médias(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $ana = $this->tecnico($empresa, 'Ana Vieira');
        $bruno = $this->tecnico($empresa, 'Bruno Sales');

        // Ana fecha duas ordens: 90 min e 30 min. A média certa é 60 min; a média das
        // médias das duas linhas seria a mesma conta aqui, mas deixaria de ser na
        // terceira ordem — é por soma e contagem que o fechado responde.
        $this->ordemConcluída($empresa, $cliente, $ana, [
            'number' => 'OS-01', 'started_at' => '2026-10-02 09:00', 'completed_at' => '2026-10-02 10:30',
        ], [['servico', 'Troca de resistência', 2, 50.0], ['servico', 'Visita', 1, 10.0]], 20.0);

        $this->ordemConcluída($empresa, $cliente, $ana, [
            'number' => 'OS-02', 'started_at' => '2026-10-05 08:00', 'completed_at' => '2026-10-05 08:30',
        ], [['servico', 'Ajuste', 1, 40.0]]);

        // Bruno: uma ordem de 60 min, um item só, sem desconto.
        $this->ordemConcluída($empresa, $cliente, $bruno, [
            'number' => 'OS-03', 'started_at' => '2026-10-06 14:00', 'completed_at' => '2026-10-06 15:00',
        ], [['servico', 'Revisão geral', 1, 40.0]]);

        // Ana tem duas visitas: uma encerrada em 40 min a 100 m do endereço e uma
        // aberta, sem posição medida.
        $this->visita($empresa, $this->recarregandoOrdem('OS-01'), $ana, [
            'checkin_at' => '2026-10-02 09:00', 'checkout_at' => '2026-10-02 09:40',
            'checkin_distance' => 100, 'checkin_latitude' => -23.5, 'checkin_longitude' => -46.5,
        ]);
        $this->visita($empresa, $this->recarregandoOrdem('OS-02'), $ana, [
            'checkin_at' => '2026-10-05 08:00', 'checkin_latitude' => null, 'checkin_longitude' => null,
        ]);

        $tela = $this->abrindoTela($admin, 'operacao');

        $this->assertStringContainsString('Ana Vieira', $tela);
        $this->assertStringContainsString('Bruno Sales', $tela);
        // 2×50 + 10 + 40 = 150, menos os 20 de desconto da ordem: 130 para Ana.
        $this->assertStringContainsString('R$ 130,00', $tela);
        $this->assertStringContainsString('R$ 40,00', $tela);
        // 120 min em duas ordens = 1 h; Bruno tem 1 h na única ordem dele.
        $this->assertStringContainsString('1 h', $tela);
        $this->assertStringContainsString('R$ 20,00', $tela);
        // Visita encerrada: 40 min no local e 100 m de chegada. A aberta não tem
        // medida e aparece como o que é.
        $this->assertStringContainsString('40 min', $tela);
        $this->assertStringContainsString('100 m', $tela);
        $this->assertStringContainsString('Sem posição', $tela);
        $this->assertStringContainsString('Ainda abertas', $tela);
    }

    public function test_a_ordem_sem_técnico_apontado_e_a_ordem_fora_da_janela_têm_lugares_diferentes(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Cliente Único');
        $ana = $this->tecnico($empresa, 'Ana Vieira');

        $this->ordemConcluída($empresa, $cliente, null, ['number' => 'OS-SEM']);
        $this->ordemConcluída($empresa, $cliente, $ana, ['number' => 'OS-DENTRO']);
        $this->ordemConcluída($empresa, $cliente, $ana, [
            'number' => 'OS-FORA', 'completed_at' => '2026-09-20 10:00', 'started_at' => '2026-09-20 09:00',
        ]);

        $tela = $this->abrindoTela($admin, 'operacao');

        $this->assertStringContainsString('Sem técnico apontado', $tela);
        $this->assertStringContainsString('ordem fechada sem técnico na ficha', $tela);
        $this->assertStringNotContainsString('OS-FORA', $tela, 'O relatório não lista ordens, e a de setembro não é deste período.');
        // Duas linhas: Ana e o grupo sem técnico. A ordem de setembro ficou de fora.
        $this->assertStringContainsString('2 técnicos com fato', $tela);
    }

    public function test_o_chamado_por_prioridade_mostra_as_quatro_mesmo_com_zero_e_med_o_prazo_da_linha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        // Urgente com prazo de 4 h: resolvido em 2 h está no prazo; resolvido em 6 h não.
        $this->chamado($empresa, $cliente, [
            'protocol' => 'CH-01', 'priority' => 'urgent', 'status' => 'resolved',
            'opened_at' => '2026-10-02 08:00', 'resolved_at' => '2026-10-02 10:00',
        ]);
        $this->chamado($empresa, $cliente, [
            'protocol' => 'CH-02', 'priority' => 'urgent', 'status' => 'resolved',
            'opened_at' => '2026-10-03 08:00', 'resolved_at' => '2026-10-03 14:00',
        ]);
        // Alta, ainda aberta, aberta há mais de 8 h do seu prazo: está vencendo agora.
        $this->chamado($empresa, $cliente, [
            'protocol' => 'CH-03', 'priority' => 'high', 'status' => 'open',
            'opened_at' => '2026-10-06 08:00', 'resolved_at' => null,
        ]);

        $tela = $this->abrindoTela($admin, 'chamados');

        $this->assertStringContainsString('Urgente', $tela);
        $this->assertStringContainsString('Alta', $tela);
        $this->assertStringContainsString('Normal', $tela);
        $this->assertStringContainsString('Baixa', $tela);
        $this->assertStringContainsString('prazo de 4 horas', $tela);
        // Urgente: 2 chamados, 1 resolvido dentro das 4 h do prazo → taxa de 50,0%.
        $this->assertStringContainsString('50,0%', $tela);
        // A fatia do período: urgente é dois de três protocolos, alta é um.
        $this->assertStringContainsString('66,7%', $tela);
        $this->assertStringContainsString('33,3%', $tela);
        // Resposta média do urgente: (2 h + 6 h) / 2 = 4 h; a alta está aberta, então
        // não tem resposta média — traço, não zero.
        $this->assertStringContainsString('4,0 h', $tela);
    }

    public function test_o_ângulo_do_chamado_troca_o_agrupamento_e_um_angulo_inventado_volta_para_o_padrão(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $ana = $this->tecnico($empresa, 'Ana Vieira');

        $this->categoria($empresa, 'Refrigeração', 'refrigeracao');
        $this->chamado($empresa, $cliente, [
            'protocol' => 'CH-10', 'category' => 'refrigeracao', 'priority' => 'normal',
            'technician_id' => $ana->id, 'opened_at' => '2026-10-02 08:00',
        ]);
        $this->chamado($empresa, $cliente, [
            'protocol' => 'CH-11', 'category' => 'outra_categoria', 'priority' => 'low',
            'technician_id' => null, 'opened_at' => '2026-10-04 08:00',
        ]);

        $porTecnico = $this->abrindoTela($admin, 'chamados', ['angulo' => 'tecnico']);
        $this->assertStringContainsString('Ana Vieira', $porTecnico);
        $this->assertStringContainsString('Sem técnico apontado', $porTecnico);
        $this->assertStringNotContainsString('Refrigeração', $porTecnico);

        $porCategoria = $this->abrindoTela($admin, 'chamados', ['angulo' => 'categoria']);
        $this->assertStringContainsString('Refrigeração', $porCategoria);
        // Categoria que não está mais no cadastro se descreve pelo próprio slug.
        $this->assertStringContainsString('Outra categoria', $porCategoria);
        $this->assertStringNotContainsString('Ana Vieira', $porCategoria);

        $inventado = $this->abrindoTela($admin, 'chamados', ['angulo' => 'categorias; drop']);
        $this->assertStringContainsString('Urgente', $inventado, 'Fora das três chaves do catálogo, o ângulo é o padrão.');
        $this->assertStringNotContainsString('Outra categoria', $inventado);
    }

    public function test_o_estoque_soma_o_período_e_mostra_o_saldo_de_hoje(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $resistencia = $this->produto($empresa, 'Resistência 220V', ['sku' => 'RES-220', 'reorder_point' => 4]);
        $gasolina = $this->produto($empresa, 'Álcool isopropílico', ['sku' => 'ISO', 'unit' => 'l', 'cost' => 8, 'reorder_point' => 0]);

        // Compra dentro do período, fora dele, carga, consumo, devolução e ajuste.
        $this->movimentando($empresa, $resistencia, 'purchase', 10, 5, '2026-10-01 08:00');
        $this->movimentando($empresa, $resistencia, 'purchase', 2, 5, '2026-09-10 08:00');
        $this->movimentando($empresa, $resistencia, 'load', 3, null, '2026-10-02 09:00');
        $this->movimentando($empresa, $resistencia, 'consume', 2, 5, '2026-10-02 10:00');
        $this->movimentando($empresa, $resistencia, 'return', 1, null, '2026-10-03 11:00');

        // Ajeitamento de inventário com sinal próprio: −2 no período.
        $this->movimentando($empresa, $gasolina, 'adjustment', -2, 8, '2026-10-04 12:00');

        $tela = $this->abrindoTela($admin, 'estoque');

        $this->assertStringContainsString('Resistência 220V', $tela);
        $this->assertStringContainsString('RES-220', $tela);
        $this->assertStringContainsString('Álcool isopropílico', $tela);
        // 4 linhas do período no primeiro produto; a compra de setembro não conta.
        $this->assertStringContainsString('2 produtos movimentados', $tela);
        // O saldo central é de hoje e olha a história inteira: 10 + 2 − 3 + 1 = 10.
        $this->assertStringContainsString('10,00', $tela);
        // Consumido no período: 2 unidades a 5 = 10 de custo.
        $this->assertStringContainsString('R$ 10,00', $tela);
        // Abaixo do ponto: 10 de saldo contra 4 de ponto não está; o isopropílico, −2
        // contra 0, está — e o ajuste entra com o próprio sinal.
        $this->assertStringContainsString('abaixo do ponto', $tela);
        $this->assertStringContainsString('na medida', $tela);
        $this->assertStringContainsString('Reposição pedida por este período', $tela);
        $this->assertStringContainsString('-2,00', $tela);
    }

    public function test_o_csv_tem_o_cabeçalho_acordo_e_as_mesmas_linhas_da_tela(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $ana = $this->tecnico($empresa, 'Ana Vieira');

        $estaConta = $this->conta($empresa, ['amount' => 250, 'due_date' => '2026-10-05']);
        $this->pagando($empresa, $estaConta, 100, '2026-10-05', 'cash');
        $this->chamado($empresa, $cliente, [
            'protocol' => 'CH-01', 'priority' => 'normal', 'status' => 'resolved',
            'opened_at' => '2026-10-02 08:00', 'resolved_at' => '2026-10-02 09:00',
        ]);
        $this->ordemConcluída($empresa, $cliente, $ana, ['number' => 'OS-01'], [['servico', 'Visita', 1, 90.0]]);
        $this->produto($empresa, 'Resistência 220V', ['sku' => 'RES-220']);
        $this->movimentando($empresa, $this->recarregandoProduto('RES-220'), 'purchase', 10, 5, '2026-10-01 08:00');

        $esperados = [
            'financeiro' => [
                'Lado do caixa', 'Categoria', 'Contas com vencimento no período', 'Valor previsto',
                'Já baixado dessas contas', 'Ainda em aberto', 'Vencidas no período',
                'Dinheiro que entrou ou saiu no período', 'Pagamentos do período',
            ],
            'operacao' => [
                'Técnico', 'Estado', 'Ordens concluídas', 'Valor gerado', 'Descontos dados',
                'Tempo médio de execução', 'Visitas', 'Tempo médio no local',
                'Distância média da chegada', 'Visitas sem posição', 'Visitas ainda abertas',
            ],
            'chamados' => [
                'Grupo', 'Chamados', '% do período', 'Críticos', 'Em andamento', 'Fora do prazo agora',
                'Resolvidos', 'Resolvidos no prazo', 'Taxa no prazo', 'Tempo médio de resposta',
            ],
            'estoque' => [
                'Produto', 'Unidade', 'Movimentações', 'Compradas', 'Carregadas', 'Consumidas',
                'Devolvidas', 'Ajustadas', 'Custo do consumo', 'Saldo central hoje',
                'Ponto de reposição', 'Abaixo do ponto',
            ],
        ];

        foreach ($esperados as $chave => $cabecalho) {
            $csv = $this->exportando($admin, $chave);
            $linhas = $this->csv($csv);

            $this->assertSame($cabecalho, $linhas[0], "O cabeçalho de [{$chave}] mudou.");
            $this->assertCount(count($cabecalho), $linhas[0]);

            foreach (array_slice($linhas, 1) as $linha) {
                $this->assertCount(count($cabecalho), $linha, "Linha de [{$chave}] com número diferente de colunas.");
            }
        }

        // A linha exata do financeiro, conferida de ponta a ponta. A conta de 250 vence
        // em 05/10 e tem 100 baixados: com hoje em 08/10 ela está vencida em aberto, e
        // é isso que a coluna "Vencidas" conta.
        $financeiro = $this->csv($this->exportando($admin, 'financeiro'));
        $this->assertSame([
            'Receita', 'Visita técnica avulsa', '1', 'R$ 250,00', 'R$ 100,00', 'R$ 150,00',
            '1', 'R$ 100,00', '1',
        ], $financeiro[1]);

        // O período vai no nome do arquivo: arquivo sem janela não se assina.
        $nomes = [
            'financeiro' => 'relatorio-financeiro-2026-10-01-2026-10-08',
            'operacao' => 'relatorio-operacao-2026-10-01-2026-10-08',
        ];

        foreach ($nomes as $chave => $base) {
            $this->assertStringContainsString(
                $base,
                strval($this->exportando($admin, $chave)->headers->get('Content-Disposition')),
            );
        }
    }

    public function test_o_csv_sai_na_ordem_que_a_tela_está_mostrando(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estaConta = $this->conta($empresa, ['amount' => 100, 'due_date' => '2026-10-05', 'category' => 'visita_tecnica']);
        $this->pagando($empresa, $estaConta, 50, '2026-10-05', 'pix');
        $aquelaConta = $this->conta($empresa, ['amount' => 900, 'due_date' => '2026-10-05', 'category' => 'emergencia']);
        $this->pagando($empresa, $aquelaConta, 900, '2026-10-05', 'transfer');

        // Sem pedido: a ordem do vocabulário do catálogo, visita técnica antes de
        // emergência, porque a lista não é um alfabeto.
        $padrao = $this->csv($this->exportando($admin, 'financeiro'));
        $this->assertSame('Visita técnica avulsa', $padrao[1][1]);
        $this->assertSame('Atendimento de emergência', $padrao[2][1]);

        $ordenada = $this->csv($this->exportando($admin, 'financeiro', ['ordena' => 'previsto', 'direcao' => 'desc']));
        $this->assertSame('Atendimento de emergência', $ordenada[1][1], 'O maior previsto tem que vir primeiro.');
        $this->assertSame('R$ 900,00', $ordenada[1][3]);

        // A mesma ordem aparece na tela: a tabela e o arquivo saem da coleção única.
        $tela = $this->abrindoTela($admin, 'financeiro', ['ordena' => 'previsto', 'direcao' => 'desc']);
        $this->assertLessThan(
            strpos($tela, 'Visita técnica avulsa'),
            strpos($tela, 'Atendimento de emergência'),
        );

        // O link de ordenar pelo nome existe justamente porque a tabela abre na ordem do
        // vocabulário. Pedir `rotulo` e receber o catálogo de volta é cabeçalho clicável
        // sem efeito: a propaganda de uma ordenação que não acontece.
        $porNome = $this->csv($this->exportando($admin, 'financeiro', ['ordena' => 'rotulo', 'direcao' => 'asc']));
        $this->assertSame('Atendimento de emergência', $porNome[1][1], 'No alfabeto, emergência vem antes de visita.');

        $aoContrario = $this->csv($this->exportando($admin, 'financeiro', ['ordena' => 'rotulo', 'direcao' => 'desc']));
        $this->assertSame('Visita técnica avulsa', $aoContrario[1][1], 'Decrescente é o invertido, não o mesmo.');

        $pelaTela = $this->abrindoTela($admin, 'financeiro', ['ordena' => 'rotulo', 'direcao' => 'desc']);
        $this->assertLessThan(
            strpos($pelaTela, 'Atendimento de emergência'),
            strpos($pelaTela, 'Visita técnica avulsa'),
        );
    }

    public function test_uma_coluna_fora_da_lista_do_relatório_não_vira_ordem_nem_query(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estaConta = $this->conta($empresa, ['amount' => 100, 'due_date' => '2026-10-05', 'category' => 'visita_tecnica']);
        $aquelaConta = $this->conta($empresa, ['amount' => 900, 'due_date' => '2026-10-05', 'category' => 'emergencia']);
        $this->pagando($empresa, $estaConta, 60, '2026-10-05', 'pix');
        $this->pagando($empresa, $aquelaConta, 90, '2026-10-05', 'pix');

        $tela = $this->abrindoTela($admin, 'financeiro', [
            'ordena' => 'amount), (select 1',
            'direcao' => 'desc; drop table payments',
        ]);

        // A coluna rejeitada cai na ordem do catálogo — o contrário de um SQL montado
        // a partir do que a query string trouxe.
        $this->assertSame('Visita técnica avulsa', $this->csv($this->exportando(
            $admin,
            'financeiro',
            ['ordena' => 'amount), (select 1'],
        ))[1][1]);

        $this->assertStringContainsString('R$ 100,00', $tela);
        // A tabela continua lá, com os dois pagamentos dela: injected order é rejeitada,
        // não executada.
        $this->assertSame(2, Payment::query()->withoutGlobalScopes()->count());
    }

    public function test_quem_lê_o_relatório_mas_não_lê_o_módulo_recebe_403_e_o_cartão_som(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $this->conta($empresa, ['amount' => 700, 'due_date' => '2026-10-05']);

        // O papel existe para provar o degrau: `reports.view` sozinho abre o hub, mas
        // nenhum fechado — e a agregação do módulo nem chega a rodar.
        $usuario = $this->usuarioComPermissoes($empresa, ['reports.view', 'dashboard.view'], 'so-relatorios@test.local');

        $this->actingAs($usuario)->get(route('reports.index'))->assertOk();

        $hub = $this->abrindoFechamento($usuario);
        $this->assertStringContainsString('Nenhum módulo para resumir', $hub);
        $this->assertStringContainsString('Sem leitura de módulo', $hub);

        foreach (['financeiro', 'operacao', 'chamados', 'estoque'] as $chave) {
            $resposta = $this->actingAs($usuario)->get(route('reports.'.$chave));
            $resposta->assertForbidden();
            $this->assertStringContainsString('não está no seu papel', $resposta->getContent());
        }

        $this->actingAs($usuario)->get(route('reports.export', ['relatorio' => 'financeiro']))->assertForbidden();
    }

    public function test_o_funcionário_abre_os_fechados_mas_sem_o_degrau_de_exportar(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $estaConta = $this->conta($empresa, ['amount' => 100, 'due_date' => '2026-10-05']);
        $this->pagando($empresa, $estaConta, 100, '2026-10-05', 'pix');

        $funcionária = $this->makeUser('employee', $empresa, 'e@test.local');

        foreach (['financeiro', 'operacao', 'chamados', 'estoque'] as $chave) {
            $this->actingAs($funcionária)->get(route('reports.'.$chave))->assertOk();
        }

        // O recorte de exportação é um degrau acima: o arquivo sai da empresa.
        $this->actingAs($funcionária)->get(route('reports.export', ['relatorio' => 'financeiro']))->assertForbidden();

        $tela = $this->abrindoTela($funcionária, 'financeiro');
        $this->assertStringNotContainsString('Exportar CSV', $tela);
        $this->assertStringContainsString('Visita técnica avulsa', $tela);

        // O técnico não tem `reports.view`: a rota inteira está atrás dela.
        $tecnico = $this->makeUser('technician', $empresa, 't@test.local');
        $this->actingAs($tecnico)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($cliente = $this->makeUser('client', $empresa, 'c@test.local'))
            ->get(route('reports.index'))->assertForbidden();
    }

    public function test_o_hub_mostra_os_números_do_período_e_só_os_fechados_que_a_conta_pode_abrir(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $supervisora = $this->makeUser('supervisor', $empresa, 's@test.local');

        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $ana = $this->tecnico($empresa, 'Ana Vieira');

        $receita = $this->conta($empresa, ['amount' => 400, 'due_date' => '2026-10-05']);
        $this->pagando($empresa, $receita, 400, '2026-10-06', 'pix');
        $despesa = $this->conta($empresa, [
            'amount' => 100, 'type' => FinancialRecord::EXPENSE, 'category' => 'pessoal', 'due_date' => '2026-10-05',
        ]);
        $this->pagando($empresa, $despesa, 100, '2026-10-07', 'cash');

        $this->ordemConcluída($empresa, $cliente, $ana, ['number' => 'OS-01'], [['servico', 'Visita', 1, 120.0]]);
        $this->visita($empresa, $this->recarregandoOrdem('OS-01'), $ana, ['checkin_at' => '2026-10-06 09:00']);
        $this->chamado($empresa, $cliente, ['protocol' => 'CH-01', 'opened_at' => '2026-10-03 08:00', 'status' => 'open']);

        $resistencia = $this->produto($empresa, 'Resistência 220V', ['sku' => 'RES-220']);
        $this->movimentando($empresa, $resistencia, 'purchase', 10, 5, '2026-10-01 08:00');
        $this->movimentando($empresa, $resistencia, 'load', 3, null, '2026-10-02 09:00');

        $hub = $this->abrindoFechamento($supervisora);

        $this->assertStringContainsString('4 relatórios', $hub);
        $this->assertStringContainsString('R$ 400,00', $hub);
        $this->assertStringContainsString('R$ 100,00', $hub);
        $this->assertStringContainsString('R$ 300,00', $hub);
        // As contagens do hub saem da mesma janela: um fato por linha, escrito pelo
        // próprio <strong> da lista de fatos — é assim que a tela desenha o número.
        $this->assertStringContainsString('<span>Ordens concluídas</span> <strong class="nf-mono">1</strong>', $hub);
        $this->assertStringContainsString('<span>Visitas de campo</span> <strong class="nf-mono">1</strong>', $hub);
        $this->assertStringContainsString('<span>Chamados abertos</span> <strong class="nf-mono">1</strong>', $hub);
        $this->assertStringContainsString('<span>Movimentações de estoque</span> <strong class="nf-mono">2</strong>', $hub);
        $this->assertStringContainsString('R$ 120,00', $hub);
        $this->assertStringNotContainsString('Nenhum módulo para resumir', $hub);

        // O degrau é a leitura do módulo resumido. O papel montado à mão tem os quatro
        // fechados menos o estoque: o cartão some do hub em vez de levar a uma tela 403,
        // e a rota continua batendo na permissão que falta — não no botão escondido.
        $semEstoque = $this->usuarioComPermissoes($empresa, [
            'reports.view', 'dashboard.view', 'clients.view',
            'financial.view', 'orders.view', 'tickets.view',
        ], 'sem-estoque@test.local');

        $destePapel = $this->abrindoFechamento($semEstoque);
        $this->assertStringContainsString('3 relatórios', $destePapel);
        $this->assertStringNotContainsString('Estoque por produto', $destePapel);
        $this->actingAs($semEstoque)->get(route('reports.estoque'))->assertForbidden();
        $this->actingAs($semEstoque)->get(route('reports.financeiro'))->assertOk();
    }

    public function test_os_fatos_da_outra_empresa_não_entram_no_fechado(): void
    {
        [$alfa, $usuarioAlfa] = $this->empresaComAdmin();
        $estaConta = $this->conta($alfa, ['amount' => 111, 'due_date' => '2026-10-04']);
        $this->pagando($alfa, $estaConta, 111, '2026-10-04', 'pix');

        $beta = $this->makeCompany('beta');
        $aquelaConta = $this->conta($beta, ['amount' => 9999, 'due_date' => '2026-10-04', 'category' => 'contrato_mensal']);
        $this->pagando($beta, $aquelaConta, 9999, '2026-10-04', 'pix');

        $fechadoAlfa = $this->abrindoTela($usuarioAlfa, 'financeiro');
        $this->assertStringContainsString('R$ 111,00', $fechadoAlfa);
        $this->assertStringNotContainsString('9.999', $fechadoAlfa);
        $this->assertStringNotContainsString('R$ 9.999,00', $fechadoAlfa);
        $this->assertStringNotContainsString('Contrato mensal', $fechadoAlfa);

        $hub = $this->abrindoFechamento($usuarioAlfa);
        $this->assertStringContainsString('R$ 111,00', $hub);
        $this->assertStringNotContainsString('9.999', $hub);

        // O pagamento da outra empresa existe no banco: é a agregação crua de
        // `payments` que tem de filtrar por empresa, sem o `CompanyScope` do Eloquent.
        $this->assertSame(2, Payment::query()->withoutGlobalScopes()->count());
    }

    public function test_um_período_sem_fatos_date_a_tela_com_zero_explicado_e_não_com_tabela_vazia(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $conta = $this->conta($empresa, ['amount' => 300, 'due_date' => '2026-11-20']);

        // Dezembro: a conta vence em novembro e nenhum centavo mudou de mão dentro
        // desta janela. É o fechado de um mês parado, não a carteira vazia.
        $tela = $this->abrindoTela($admin, 'financeiro', ['inicio' => '2026-12-01', 'fim' => '2026-12-31']);
        $this->assertStringContainsString('Nenhuma conta nem nenhum pagamento nesta janela', $tela);
        $this->assertStringNotContainsString('<th scope="col">Lado do caixa</th>', $tela, 'Sem linha, não se desenha tabela.');

        $csv = $this->csv($this->exportando($admin, 'financeiro', ['inicio' => '2026-12-01', 'fim' => '2026-12-31']));
        $this->assertCount(1, $csv, 'Sem linha no período, o CSV tem só o cabeçalho.');

        // O mesmo período com dinheiro dentro dele deixa de estar vazio: a linha vem do
        // caixa, mesmo com a conta vencendo em outro mês.
        $this->pagando($empresa, $conta, 300, '2026-12-05', 'pix');
        $comDinheiro = $this->abrindoTela($admin, 'financeiro', ['inicio' => '2026-12-01', 'fim' => '2026-12-31']);
        $this->assertStringContainsString('Visita técnica avulsa', $comDinheiro);
        $this->assertStringContainsString('R$ 300,00', $comDinheiro);
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /**
     * Um papel montado pela mão, com exatamente as permissões do pedido: é assim que
     * se prova o degrau entre ler o relatório e ler o módulo que ele resume.
     *
     * @param  array<int, string>  $slugs
     */
    private function usuarioComPermissoes(Company $empresa, array $slugs, string $email): User
    {
        $papel = Role::create([
            'company_id' => $empresa->id,
            'name' => 'Somente relatórios',
            'slug' => 'so-relatorios',
            'is_system' => false,
        ]);

        $papel->permissions()->attach(Permission::query()->whereIn('slug', $slugs)->pluck('id')->all());

        $usuario = User::create([
            'company_id' => $empresa->id,
            'name' => 'Analista de fechamento',
            'email' => $email,
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);

        $usuario->roles()->attach($papel->id);

        return $usuario->fresh('roles.permissions');
    }

    private function abrindoFechamento(User $quem, array $consulta = []): string
    {
        return $this->semEspaços(
            $this->actingAs($quem)->get(route('reports.index', $consulta))->assertOk()->getContent()
        );
    }

    private function abrindoTela(User $quem, string $chave, array $consulta = []): string
    {
        return $this->semEspaços(
            $this->actingAs($quem)->get(route('reports.'.$chave, $consulta))->assertOk()->getContent()
        );
    }

    /** @param  array<string, mixed>  $consulta  */
    private function exportando(User $quem, string $chave, array $consulta = []): TestResponse
    {
        return $this->actingAs($quem)->get(route('reports.export', ['relatorio' => $chave] + $consulta))->assertOk();
    }

    /**
     * O CSV lido como o Excel lê: BOM fora, `;` como separador, uma linha por fato.
     *
     * @return array<int, array<int, string>>
     */
    private function csv(TestResponse $resposta): array
    {
        $conteúdo = str_replace("\xEF\xBB\xBF", '', $resposta->streamedContent());
        $linhas = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($conteúdo)) as $linha) {
            if ($linha !== '') {
                $linhas[] = str_getcsv($linha, ';');
            }
        }

        return $linhas;
    }

    /** @param  array<string, mixed>  $extras  */
    private function conta(Company $empresa, array $extras = []): FinancialRecord
    {
        TenantContext::set($empresa->id);
        $conta = FinancialRecord::query()->create(array_merge([
            'type' => FinancialRecord::REVENUE,
            'category' => 'visita_tecnica',
            'description' => 'Cobrança de teste',
            'amount' => 100,
            'due_date' => '2026-10-05',
            'status' => FinancialRecord::PENDING,
        ], $extras));
        TenantContext::forget();

        return $conta;
    }

    private function pagando(Company $empresa, FinancialRecord $conta, float $valor, string $dia, string $metodo): Payment
    {
        TenantContext::set($empresa->id);
        $pagamento = Payment::query()->create([
            'company_id' => $empresa->id,
            'financial_record_id' => $conta->id,
            'amount' => $valor,
            'method' => $metodo,
            'paid_at' => $dia,
        ]);
        TenantContext::forget();

        return $pagamento;
    }

    /** @param  array<string, mixed>  $extras  */
    private function cliente(Company $empresa, string $nome, array $extras = []): Client
    {
        TenantContext::set($empresa->id);
        $cliente = Client::query()->create($extras + ['name' => $nome, 'status' => 'active']);
        TenantContext::forget();

        return $cliente;
    }

    /** @param  array<string, mixed>  $extras  */
    private function tecnico(Company $empresa, string $nome, array $extras = []): Technician
    {
        TenantContext::set($empresa->id);
        $tecnico = Technician::query()->create($extras + ['name' => $nome, 'status' => 'available']);
        TenantContext::forget();

        return $tecnico;
    }

    /** @param  array<string, mixed>  $extras  */
    private function ordemConcluída(
        Company $empresa,
        Client $cliente,
        ?Technician $tecnico,
        array $extras = [],
        array $itens = [],
        float $desconto = 0.0,
    ): ServiceOrder {
        $atributos = array_merge([
            'priority' => 'normal',
            'status' => 'completed',
            'started_at' => '2026-10-02 08:00',
            'completed_at' => '2026-10-02 09:00',
        ], $extras);

        TenantContext::set($empresa->id);
        $ordem = ServiceOrder::query()->create($atributos + [
            'client_id' => $cliente->id,
            'number' => 'OS-'.Str::random(6),
            'title' => 'Ordem de teste',
            'technician_id' => $tecnico?->id,
            'discount' => $desconto,
        ]);

        foreach ($itens as [$tipo, $descrição, $quantidade, $unitário]) {
            $ehServico = $tipo === 'servico';
            $origem = $ehServico
                ? $this->servico($empresa, $descrição, ['price' => $unitário])
                : $this->produto($empresa, $descrição, ['price' => $unitário]);

            $ordem->items()->create([
                'service_id' => $ehServico ? $origem->id : null,
                'product_id' => $ehServico ? null : $origem->id,
                'description' => $descrição,
                'quantity' => $quantidade,
                'unit_price' => $unitário,
            ]);
        }

        TenantContext::forget();

        return $ordem;
    }

    /** @param  array<string, mixed>  $extras  */
    private function servico(Company $empresa, string $nome, array $extras = []): Service
    {
        TenantContext::set($empresa->id);
        $servico = Service::query()->create($extras + ['name' => $nome, 'price' => 100, 'status' => 'active']);
        TenantContext::forget();

        return $servico;
    }

    /** @param  array<string, mixed>  $campos  */
    private function visita(Company $empresa, ServiceOrder $ordem, Technician $tecnico, array $campos = []): ServiceOrderCheckin
    {
        TenantContext::set($empresa->id);
        $visita = ServiceOrderCheckin::query()->create($campos + [
            'service_order_id' => $ordem->id,
            'technician_id' => $tecnico->id,
            'checkin_at' => '2026-10-02 08:00',
            'status' => array_key_exists('checkout_at', $campos) ? 'confirmed' : 'pending',
        ]);
        TenantContext::forget();

        return $visita;
    }

    /** @param  array<string, mixed>  $extras  */
    private function chamado(Company $empresa, Client $cliente, array $extras = []): Ticket
    {
        TenantContext::set($empresa->id);
        $chamado = Ticket::query()->create(array_merge([
            'client_id' => $cliente->id,
            'subject' => 'Chamado de teste',
            'category' => 'refrigeracao',
            'priority' => 'normal',
            'status' => 'open',
            'opened_at' => '2026-10-02 08:00',
        ], $extras));
        TenantContext::forget();

        return $chamado;
    }

    private function categoria(Company $empresa, string $nome, string $slug): ServiceCategory
    {
        TenantContext::set($empresa->id);
        $categoria = ServiceCategory::query()->create(['name' => $nome, 'slug' => $slug]);
        TenantContext::forget();

        return $categoria;
    }

    /** @param  array<string, mixed>  $extras  */
    private function produto(Company $empresa, string $nome, array $extras = []): Product
    {
        TenantContext::set($empresa->id);
        $produto = Product::query()->create($extras + [
            'name' => $nome,
            'sku' => Str::slug($nome),
            'unit' => 'un',
            'cost' => 10,
            'price' => 25,
            'reorder_point' => 0,
            'status' => 'active',
        ]);
        TenantContext::forget();

        return $produto;
    }

    private function movimentando(
        Company $empresa,
        Product $produto,
        string $tipo,
        float $quantidade,
        ?float $unitário,
        string $quando,
    ): StockMovement {
        TenantContext::set($empresa->id);
        $movimento = StockMovement::query()->create([
            'product_id' => $produto->id,
            'type' => $tipo,
            'quantity' => $quantidade,
            'unit_cost' => $unitário,
            'recorded_at' => $quando,
        ]);
        TenantContext::forget();

        return $movimento;
    }

    private function recarregandoOrdem(string $numero): ServiceOrder
    {
        return ServiceOrder::query()->withoutGlobalScopes()->where('number', $numero)->firstOrFail();
    }

    private function recarregandoProduto(string $sku): Product
    {
        return Product::query()->withoutGlobalScopes()->where('sku', $sku)->firstOrFail();
    }

    private function semEspaços(string $html): string
    {
        return preg_replace('/\s+/', ' ', $html);
    }
}
