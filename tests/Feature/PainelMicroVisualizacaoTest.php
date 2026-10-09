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
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\Formatters;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A micro-visualização do painel não é adorno: cada traço é a contagem que o banco
 * devolveu dia a dia, e o anel é a proporção que as próprias ordens calcularam.
 * Estes testes amarram as duas coisas — a série ao SQL e o desenho à série — porque
 * um gráfico que existe mesmo quando não há dado é o gráfico falso proibido no
 * desenho do projeto.
 */
class PainelMicroVisualizacaoTest extends TestCase
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

    public function test_a_serie_de_conclusoes_tem_um_ponto_por_dia_e_bate_com_o_banco(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        // Catorze dias terminando hoje: 25/09 a 08/10. Duas conclusões hoje, uma em
        // 06/10, uma na abertura da janela e uma antes dela — essa última não pode
        // aparecer no traço nem na soma.
        $this->concluida($empresa, $cliente, '2026-10-08 09:00');
        $this->concluida($empresa, $cliente, '2026-10-08 09:30');
        $this->concluida($empresa, $cliente, '2026-10-06 18:00');
        $this->concluida($empresa, $cliente, '2026-09-25 18:00');
        $this->concluida($empresa, $cliente, '2026-09-24 18:00');

        $spark = $this->kpi($this->painel($usuario), 'Concluídas em 7 dias')['spark'];

        $this->assertCount(14, $spark['valores'], 'A série tem de ter um ponto por dia do período.');
        $this->assertCount(14, $spark['rotulos']);
        $this->assertSame('25/09', $spark['rotulos'][0]);
        $this->assertSame('08/10', $spark['rotulos'][13]);
        $this->assertSame(1, $spark['valores'][0], '25/09 teve uma conclusão e o traço tem de mostrar isso.');
        $this->assertSame(1, $spark['valores'][11]);
        $this->assertSame(2, $spark['valores'][13], 'Hoje é o último ponto da série.');
        $this->assertSame(4, array_sum($spark['valores']), 'O que cai fora da janela não entra no traço.');

        // O número do cartão e a série saem da mesma consulta: 7 dias são os últimos
        // sete pontos de catorze.
        $this->assertSame(
            (int) $this->kpi($this->painel($usuario), 'Concluídas em 7 dias')['value'],
            array_sum(array_slice($spark['valores'], -7)),
            'Série e indicador não podem responder janelas diferentes do mesmo fato.'
        );
    }

    public function test_o_traco_existe_quando_ha_serie_e_somente_quando_ha_serie(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        // Empresa sem nada concluído: o cartão continua existindo com o zero dele,
        // mas o traço não nasce — linha reta na base é a figura dizendo que houve
        // movimento constante, e o banco respondeu "nada aconteceu".
        $vazia = $this->abrindoPainel($usuario);
        $this->assertStringNotContainsString('<svg class="nf-spark"', $vazia);
        $this->assertStringContainsString('Concluídas em 7 dias', $vazia);

        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $this->concluida($empresa, $cliente, '2026-10-08 09:00');

        $html = $this->abrindoPainel($usuario);
        $this->assertStringContainsString('<svg class="nf-spark"', $html);

        // A figura é decorativa para quem vê e precisa ser lida por quem não vê: a
        // descrição devolve os números que o traço conta, não "gráfico bonito".
        $this->assertStringContainsString(
            'aria-label="Conclusões por dia, últimos 14 dias. Começa em 0, termina em 1, maior ponto 1."',
            $html
        );
    }

    public function test_o_medidor_so_existe_quando_a_ficha_tinha_prazo_para_cumprir(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        // Concluída sem fim previsto: sem prazo não há atraso a medir, e contá-la no
        // denominador encheria o anel com proporção emprestada.
        $this->ordem($empresa, $cliente, [
            'status' => 'completed',
            'completed_at' => '2026-10-08 09:00',
        ]);

        $painel = $this->painel($usuario);
        $this->assertNull($painel['ordens']['pontualidade']);
        // A folha de estilo é embutida na própria página, então a prova procura o
        // elemento desenhado, não o nome da classe — que existiria no CSS mesmo sem
        // medidor nenhum na tela.
        $this->assertStringNotContainsString('class="nf-gauge tone-', $this->abrindoPainel($usuario));

        $this->ordem($empresa, $cliente, [
            'status' => 'completed',
            'completed_at' => '2026-10-08 09:30',
            'scheduled_ends_at' => '2026-10-09 10:00',
        ]);

        $medidor = $this->painel($usuario)['ordens']['pontualidade'];
        $this->assertSame(100, $medidor['percentual']);
        $this->assertSame(1, $medidor['total']);
        $this->assertSame('done', $medidor['tom']);

        // Uma entrega atrasada: o anel cai para a metade, e é a metade exata do que
        // o banco tem — dois termos, um dentro do prazo.
        $this->ordem($empresa, $cliente, [
            'status' => 'completed',
            'completed_at' => '2026-10-06 18:00',
            'scheduled_ends_at' => '2026-10-05 10:00',
        ]);

        $medidor = $this->painel($usuario)['ordens']['pontualidade'];
        $this->assertSame(50, $medidor['percentual']);
        $this->assertSame(1, $medidor['no_prazo']);
        $this->assertSame(2, $medidor['total']);
        $this->assertSame('waiting', $medidor['tom']);

        $html = $this->abrindoPainel($usuario);
        $this->assertStringContainsString('class="nf-gauge tone-waiting"', $html);
        // O arco coberto é a fatia do percentual sobre a circunferência de r 15,5
        // (97,39), escrito com duas casas fixas para a tela e a prova lerem o mesmo
        // número.
        $this->assertStringContainsString('stroke-dasharray="48.70 97.39"', $html);
        $this->assertStringContainsString('1 de 2 ordens concluídas', $html);
    }

    public function test_a_serie_mensal_conta_o_dinheiro_que_mudou_de_mao_e_o_do_outro_lado(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $esteMes = $this->conta($empresa, $cliente, FinancialRecord::REVENUE, 'contrato_mensal', 120);
        $outroMes = $this->conta($empresa, $cliente, FinancialRecord::REVENUE, 'contrato_mensal', 50);
        $despesa = $this->conta($empresa, $cliente, FinancialRecord::EXPENSE, 'deslocamento', 40);

        Payment::query()->create([
            'company_id' => $empresa->id,
            'financial_record_id' => $esteMes->id,
            'amount' => 100,
            'method' => 'pix',
            'paid_at' => '2026-10-05',
        ]);
        Payment::query()->create([
            'company_id' => $empresa->id,
            'financial_record_id' => $esteMes->id,
            'amount' => 20,
            'method' => 'cartao',
            'paid_at' => '2026-10-07',
        ]);
        Payment::query()->create([
            'company_id' => $empresa->id,
            'financial_record_id' => $outroMes->id,
            'amount' => 50,
            'method' => 'dinheiro',
            'paid_at' => '2026-09-12',
        ]);
        Payment::query()->create([
            'company_id' => $empresa->id,
            'financial_record_id' => $despesa->id,
            'amount' => 40,
            'method' => 'pix',
            'paid_at' => '2026-10-02',
        ]);

        $spark = $this->kpi($this->painel($usuario), 'Recebido no mês')['spark'];

        $this->assertCount(6, $spark['valores'], 'A série mensal percorre seis meses, um ponto por mês.');
        $this->assertSame('05/26', $spark['rotulos'][0]);
        $this->assertSame('10/26', $spark['rotulos'][5]);
        $this->assertSame(120.0, $spark['valores'][5], 'Os dois pagamentos de outubro somam no mesmo ponto.');
        $this->assertSame(50.0, $spark['valores'][4]);
        $this->assertSame(40.0, $spark['comparar'][5], 'O traço pontilhado é o que saiu, não uma segunda linha do que entrou.');

        // O cartão e a série leem a mesma fonte: Payment, nunca a coluna prevista.
        $this->assertSame(
            'R$ 120,00',
            $this->kpi($this->painel($usuario), 'Recebido no mês')['value']
        );
    }

    public function test_o_traco_e_o_numero_do_cartao_contam_o_mesmo_periodo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $produto = Product::query()->create([
            'company_id' => $empresa->id,
            'sku' => 'TR-100',
            'name' => 'Termôparo de balcão',
            'unit' => 'un',
            'reorder_point' => 5,
            'status' => 'active',
        ]);

        $this->concluida($empresa, $cliente, '2026-10-08 09:00');
        $this->concluida($empresa, $cliente, '2026-10-08 09:30');

        Appointment::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'title' => 'Visita técnica',
            'starts_at' => '2026-10-08 15:00',
            'ends_at' => '2026-10-08 16:00',
        ]);

        StockMovement::query()->create([
            'company_id' => $empresa->id,
            'product_id' => $produto->id,
            'type' => 'purchase',
            'quantity' => 4,
            'recorded_at' => '2026-10-08 08:00',
        ]);
        StockMovement::query()->create([
            'company_id' => $empresa->id,
            'product_id' => $produto->id,
            'type' => 'consume',
            'quantity' => 1,
            'recorded_at' => '2026-10-07 08:00',
        ]);

        $conta = $this->conta($empresa, $cliente, FinancialRecord::REVENUE, 'visita_tecnica', 70);
        Payment::query()->create([
            'company_id' => $empresa->id,
            'financial_record_id' => $conta->id,
            'amount' => 70,
            'method' => 'pix',
            'paid_at' => '2026-10-08',
        ]);

        // A régua do desenho: traço e número têm de responder a mesma pergunta sobre o
        // mesmo período — mas nem todo cartão conta um dia. "Concluídas em 7 dias" é a
        // soma de sete pontos da série diária; "Agenda de hoje", "Movimentações de hoje"
        // e "Recebido no mês" são o ponto que fecha a série. A relação é declarada por
        // cartão, e um cartão novo que desenhe série sem declarar régua falha aqui antes
        // de enganar quem olha o painel.
        $reguas = [
            'Concluídas em 7 dias' => ['pontos' => 7, 'modo' => 'soma'],
            'Agenda de hoje' => ['pontos' => 1, 'modo' => 'ponto'],
            'Movimentações de hoje' => ['pontos' => 1, 'modo' => 'ponto'],
            'Recebido no mês' => ['pontos' => 1, 'modo' => 'ponto'],
        ];

        $desenhados = 0;

        foreach ($this->painel($usuario)['kpis'] as $kpi) {
            if (! array_key_exists('spark', $kpi)) {
                continue;
            }

            $desenhados++;
            $rotulo = (string) $kpi['label'];
            $this->assertArrayHasKey(
                $rotulo,
                $reguas,
                "A série do cartão “{$rotulo}” não declara como se compara ao número do cartão."
            );

            $regua = $reguas[$rotulo];
            $janela = array_slice($kpi['spark']['valores'], -$regua['pontos']);
            $medida = $regua['modo'] === 'soma'
                ? array_sum($janela)
                : $janela[count($janela) - 1];

            if (str_starts_with((string) $kpi['value'], 'R$ ')) {
                $this->assertSame(
                    $kpi['value'],
                    Formatters::money((float) $medida),
                    "O cartão “{$rotulo}” e o traço dele contam períodos diferentes."
                );

                continue;
            }

            $this->assertSame(
                (int) $kpi['value'],
                (int) $medida,
                "O cartão “{$rotulo}” e o traço dele contam períodos diferentes."
            );
        }

        $this->assertSame(4, $desenhados, 'Ordem, agenda, estoque e financeiro têm série no painel.');
        $this->assertStringContainsString('<svg class="nf-spark"', $this->abrindoPainel($usuario));
    }

    public function test_todo_kpi_continua_com_icone_e_o_anel_e_eco_escondido_da_leitura(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $this->ordem($empresa, $cliente, [
            'status' => 'completed',
            'completed_at' => '2026-10-08 09:00',
            'scheduled_ends_at' => '2026-10-09 10:00',
        ]);

        $html = $this->abrindoPainel($usuario);

        // O desenho pedido no módulo de dashboard exige ícone em cada KPI: o anel
        // mora no rodapé da distribuição, não no lugar do ícone de ninguém.
        $cartoes = substr_count($html, '<p class="nf-kpi-label mb-1">');
        $icones = substr_count($html, 'class="nf-icon-tile tone-');
        $this->assertGreaterThan(0, $cartoes);
        $this->assertSame($cartoes, $icones, 'Tem cartão de indicador sem ícone na tela.');

        // O anel repete o número escrito ao lado dele, então não pode ser lido duas
        // vezes por quem usa leitor de tela.
        $this->assertMatchesRegularExpression('/class="nf-gauge tone-[a-z]+" aria-hidden="true"/', $html);
    }

    public function test_a_micro_visualizacao_bebe_do_registro_de_tom_e_nao_traz_cor_nova(): void
    {
        $css = file_get_contents(base_path('resources/css/nexusfield/components.css'));
        $inicio = strpos($css, 'Micro-visualização do painel');
        $this->assertNotFalse($inicio, 'A seção da micro-visualização não está na folha de componentes.');

        $bloco = substr($css, $inicio);

        $this->assertDoesNotMatchRegularExpression(
            '/#[0-9a-fA-F]{3,8}\b/',
            $bloco,
            'Traço e anel têm de vestir o tom do cartão, não uma tinta nova fora do registro.'
        );
        $this->assertStringNotContainsString('rgb(', $bloco);
        $this->assertStringContainsString('var(--nf-tone', $bloco);
        $this->assertStringContainsString('vector-effect: non-scaling-stroke', $bloco,
            'Sem o traço de espessura fixa o mesmo desenho contaria histórias diferentes no celular e na Smart TV.');
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    private function painel(User $usuario): array
    {
        TenantContext::resolveFromUser($usuario);

        return (new DashboardMetrics($usuario))->toArray();
    }

    private function abrindoPainel(User $usuario): string
    {
        $html = $this->actingAs($usuario)->get(route('dashboard'))->assertOk()->getContent();

        return preg_replace('/\s+/', ' ', $html);
    }

    /**
     * @param  array<string, mixed>  $painel
     * @return array<string, mixed>
     */
    private function kpi(array $painel, string $rotulo): array
    {
        $kpi = collect($painel['kpis'])->firstWhere('label', $rotulo);
        $this->assertNotNull($kpi, "O painel não desenhou o KPI “{$rotulo}”.");

        return $kpi;
    }

    private function cliente(Company $empresa, string $nome): Client
    {
        return Client::query()->create([
            'company_id' => $empresa->id,
            'name' => $nome,
            'status' => 'active',
        ]);
    }

    /** @param  array<string, mixed>  $atributos */
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

    private function concluida(Company $empresa, Client $cliente, string $quando): ServiceOrder
    {
        return $this->ordem($empresa, $cliente, ['status' => 'completed', 'completed_at' => $quando]);
    }

    private function conta(
        Company $empresa,
        Client $cliente,
        string $tipo,
        string $categoria,
        float $valor
    ): FinancialRecord {
        return FinancialRecord::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'type' => $tipo,
            'category' => $categoria,
            'description' => 'Conta da série mensal',
            'amount' => $valor,
            'due_date' => '2026-10-11',
            'status' => FinancialRecord::PENDING,
        ]);
    }
}
