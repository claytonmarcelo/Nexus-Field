<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\FinancialRecord;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores do painel, todos consultados no MySQL da empresa do contexto.
 *
 * Cada bloco só roda se o papel do usuário tem a permissão do módulo: sem
 * `financial.view` a consulta de financeiro não chega a ser feita — não é botão
 * escondido no HTML, é dado que não sai do banco para aquela sessão.
 */
class DashboardMetrics
{
    public function __construct(private readonly User $usuario) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $painel = [
            'kpis' => [],
            'ordens' => [],
            'agenda' => [],
            'chamados' => [],
            'financeiro' => [],
            'estoque' => [],
            'equipe' => [],
            'notificacoes' => [],
            'base' => [],
        ];

        if ($bloco = $this->ordens()) {
            $painel['ordens'] = $bloco['dados'];
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        if ($bloco = $this->agenda()) {
            $painel['agenda'] = $bloco['dados'];
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        if ($bloco = $this->chamados()) {
            $painel['chamados'] = $bloco['dados'];
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        if ($bloco = $this->equipe()) {
            $painel['equipe'] = $bloco['dados'];
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        if ($bloco = $this->clientes()) {
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        if ($bloco = $this->estoque()) {
            $painel['estoque'] = $bloco['dados'];
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        if ($bloco = $this->financeiro()) {
            $painel['financeiro'] = $bloco['dados'];
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        if ($bloco = $this->notificacoes()) {
            $painel['notificacoes'] = $bloco['dados'];
            $painel['kpis'] = array_merge($painel['kpis'], $bloco['kpis']);
        }

        $painel['base'] = $this->base();

        return $painel;
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function ordens(): array
    {
        if (! $this->pode('orders.view')) {
            return [];
        }

        $abertas = $this->ordensDoUsuario()->open()->count();
        $execucao = $this->ordensDoUsuario()->inProgress()->count();
        $atrasadas = $this->ordensDoUsuario()->overdue()->count();

        // Janela em dias de calendário, não em voltas de relógio: é o que deixa o
        // número do cartão igual à soma dos últimos sete pontos do traço. Com [7×24h]
        // a conta cortaria o dia pela metade e o desenho contaria o dia inteiro.
        $concluidasSete = $this->ordensDoUsuario()
            ->completed()
            ->whereBetween('completed_at', [now()->startOfDay()->subDays(6), now()])
            ->count();
        $concluidasAnteriores = $this->ordensDoUsuario()
            ->completed()
            ->whereBetween('completed_at', [
                now()->startOfDay()->subDays(13),
                now()->startOfDay()->subDays(7)->endOfDay(),
            ])
            ->count();

        // O traço embaixo do KPI é a mesma consulta do número, aberta dia a dia:
        // série e indicador têm de bater com o que o banco respondeu, nunca um
        // enfeite de forma parecida.
        $concluidasPorDia = $this->serieDiaria(
            $this->ordensDoUsuario()->completed(),
            'completed_at',
            14,
        );
        $pontualidade = $this->pontualidade();

        $distribuicao = $this->ordensDoUsuario()
            ->selectRaw('status, count(*) total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $totalDistribuido = array_sum($distribuicao);

        return [
            'kpis' => [
                [
                    'label' => 'Ordens abertas',
                    'value' => $abertas,
                    'hint' => $execucao.' em execução',
                    'tone' => 'open',
                    'icon' => 'fa-solid fa-clipboard-list',
                ],
                [
                    'label' => 'Concluídas em 7 dias',
                    'value' => $concluidasSete,
                    'tone' => 'done',
                    'icon' => 'fa-solid fa-circle-check',
                    'delta' => $this->deltaInt($concluidasSete - $concluidasAnteriores, 'vs. 7 dias anteriores'),
                    'spark' => $concluidasPorDia + ['legenda' => 'Conclusões por dia, últimos 14 dias'],
                ],
                [
                    'label' => 'Com prazo vencido',
                    'value' => $atrasadas,
                    'hint' => 'Agendadas e ainda não concluídas',
                    'tone' => 'canceled',
                    'icon' => 'fa-solid fa-triangle-exclamation',
                ],
            ],
            'dados' => [
                'distribuicao' => $this->distribuicao($distribuicao, $totalDistribuido),
                'pontualidade' => $pontualidade,
                'proximas' => $this->ordensDoUsuario()
                    ->with(['client', 'technician'])
                    ->open()
                    ->scheduledBetween(now()->startOfDay(), now()->addDays(7)->endOfDay())
                    ->orderBy('scheduled_starts_at')
                    ->limit(6)
                    ->get(),
                'fila_atrasada' => $this->ordensDoUsuario()
                    ->with(['client'])
                    ->overdue()
                    ->orderBy('scheduled_ends_at')
                    ->limit(4)
                    ->get(),
            ],
        ];
    }

    /**
     * O painel conta ordem por ordem o que o usuário alcança: o técnico vê a
     * própria fila, a conta de cliente vê a carteira dela, e o escritório vê a
     * empresa. Sem este filtro, o KPI de quem está no campo responderia pela
     * operação inteira.
     */
    private function ordensDoUsuario(): Builder
    {
        return ServiceOrder::query()->visiveisPara($this->usuario);
    }

    /**
     * Mesma regra de alcance das ordens, aplicada aos chamados: o KPI "abertos" de
     * um técnico é a fila dele, não a fila da empresa.
     */
    private function chamadosDoUsuario(): Builder
    {
        return Ticket::query()->visiveisPara($this->usuario);
    }

    /**
     * Mesma regra de alcance da tela de agenda. O painel e o calendário precisam
     * responder o mesmo número para o mesmo usuário: um técnico que abre a agenda
     * e vê três janelas não pode ter lido "12" no painel.
     */
    private function compromissosDoUsuario(): Builder
    {
        return Appointment::query()->visiveisPara($this->usuario);
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function agenda(): array
    {
        if (! $this->pode('agenda.view')) {
            return [];
        }

        $hoje = $this->compromissosDoUsuario()
            ->with(['technician', 'client'])
            ->between(now()->startOfDay(), now()->endOfDay())
            ->orderBy('starts_at')
            ->get();

        $proximos = $this->compromissosDoUsuario()
            ->between(now()->startOfDay(), now()->addDays(7)->endOfDay())
            ->count();

        $compromissosPorDia = $this->serieJanelas($this->compromissosDoUsuario(), 14);

        return [
            'kpis' => [
                [
                    'label' => 'Agenda de hoje',
                    'value' => $hoje->count(),
                    'hint' => $proximos.' compromissos em 7 dias',
                    'tone' => 'waiting',
                    'icon' => 'fa-regular fa-calendar-check',
                    'spark' => $compromissosPorDia + ['legenda' => 'Janelas agendadas por dia, últimos 14 dias'],
                ],
            ],
            'dados' => [
                'hoje' => $hoje,
                'proximos' => $proximos,
            ],
        ];
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function chamados(): array
    {
        if (! $this->pode('tickets.view')) {
            return [];
        }

        $abertos = $this->chamadosDoUsuario()->open()->count();
        $criticos = $this->chamadosDoUsuario()->open()->urgent()->count();

        $resolvidos = $this->chamadosDoUsuario()
            ->resolvedBetween(now()->subDays(7), now())
            ->count();

        // Média aritmética das resoluções da janela; sem resolução no período o
        // painel diz "sem resolução registrada" em vez de mostrar zero.
        $tempoMedio = $this->chamadosDoUsuario()
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [now()->subDays(30), now()])
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, opened_at, resolved_at)'));

        return [
            'kpis' => [
                [
                    'label' => 'Chamados abertos',
                    'value' => $abertos,
                    'hint' => $criticos.' de prioridade alta ou urgente',
                    'tone' => $abertos > 0 ? 'progress' : 'draft',
                    'icon' => 'fa-regular fa-life-ring',
                ],
            ],
            'dados' => [
                'resolvidos' => $resolvidos,
                'tempo_medio' => $tempoMedio === null ? null : (float) $tempoMedio,
                'fila' => $this->chamadosDoUsuario()
                    ->with(['client'])
                    ->open()
                    ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")
                    ->orderBy('opened_at')
                    ->limit(6)
                    ->get(),
            ],
        ];
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function equipe(): array
    {
        if (! $this->pode('technicians.view')) {
            return [];
        }

        $total = Technician::query()->active()->count();
        $disponiveis = Technician::query()->available()->count();
        // O mesmo alcance da tela de visitas: quem lê a própria escala conta a
        // própria presença em campo, não a empresa inteira num cartão que parece
        // ser dele.
        $emCampo = ServiceOrderCheckin::query()
            ->visiveisPara($this->usuario)
            ->open()
            ->distinct()
            ->count('technician_id');

        return [
            'kpis' => [
                [
                    'label' => 'Técnicos em campo agora',
                    'value' => $emCampo,
                    'hint' => $disponiveis.' disponíveis de '.$total,
                    'tone' => 'progress',
                    'icon' => 'fa-solid fa-helmet-safety',
                ],
            ],
            'dados' => [
                'total' => $total,
                'disponiveis' => $disponiveis,
                'em_campo' => $emCampo,
                'lista' => Technician::query()
                    ->withCount(['serviceOrders' => fn ($q) => $q->whereIn('status', ['open', 'in_progress'])])
                    ->active()
                    ->orderByDesc('service_orders_count')
                    ->limit(5)
                    ->get(),
            ],
        ];
    }

    /** @return array{kpis: array<int, array<string, mixed>>}|[] */
    private function clientes(): array
    {
        if (! $this->pode('clients.view')) {
            return [];
        }

        $ativos = Client::query()->where('status', 'active')->count();
        $novos = Client::query()->where('created_at', '>=', now()->subDays(30))->count();

        return [
            'kpis' => [
                [
                    'label' => 'Clientes ativos',
                    'value' => $ativos,
                    'hint' => $novos.' novos nos últimos 30 dias',
                    'tone' => 'done',
                    'icon' => 'fa-solid fa-building',
                ],
            ],
        ];
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function estoque(): array
    {
        if (! $this->pode('stock.view')) {
            return [];
        }

        $abaixo = Product::query()->active()->belowReorderPoint()->get();

        // O livro-caixa do painel obedece ao mesmo alcance da listagem: quem tem ficha
        // e não responde pelo inventário vê as próprias cargas, não o movimento da empresa.
        $hoje = StockMovement::query()
            ->visiveisPara($this->usuario)
            ->whereDate('recorded_at', today())
            ->count();

        $movimentosPorDia = $this->serieDiaria(
            StockMovement::query()->visiveisPara($this->usuario),
            'recorded_at',
            14,
        );

        return [
            'kpis' => [
                [
                    'label' => 'Itens abaixo do ponto de reposição',
                    'value' => $abaixo->count(),
                    'hint' => 'Saldo central derivado das movimentações',
                    'tone' => $abaixo->isEmpty() ? 'done' : 'canceled',
                    'icon' => 'fa-solid fa-boxes-stacked',
                ],
                [
                    'label' => 'Movimentações de hoje',
                    'value' => $hoje,
                    'hint' => $hoje === 0
                        ? 'Nenhuma unidade entrou ou saiu do estoque hoje'
                        : 'Contado no servidor, no horário de cá',
                    'tone' => $hoje === 0 ? 'waiting' : 'progress',
                    'icon' => 'fa-solid fa-right-left',
                    'spark' => $movimentosPorDia + ['legenda' => 'Movimentações por dia, últimos 14 dias'],
                ],
            ],
            'dados' => [
                'reposicao' => $abaixo->sortBy('central_balance')->take(5)->values(),
                'movimentacoes' => StockMovement::query()
                    ->visiveisPara($this->usuario)
                    ->with(['product:id,name,unit', 'technician:id,name', 'serviceOrder:id,number'])
                    ->latest('recorded_at')
                    ->take(6)
                    ->get(),
            ],
        ];
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function financeiro(): array
    {
        if (! $this->pode('financial.view')) {
            return [];
        }

        // "A receber" e "a pagar" são o que falta, não o valor previsto: numa conta
        // meio paga o dinheiro que já mudou de mão não pode ser contado de novo. A
        // soma é a mesma expressão SQL da carteira — o painel e a listagem respondem
        // pelo mesmo número.
        $receitaEmAberto = FinancialRecord::totais(
            FinancialRecord::query()->revenue()->emAberto()
        );
        $despesaEmAberto = FinancialRecord::totais(
            FinancialRecord::query()->expense()->emAberto()
        );

        $aReceber = $receitaEmAberto['em_aberto'];
        $aPagar = $despesaEmAberto['em_aberto'];
        $vencido = $receitaEmAberto['vencido_valor'];

        $receitaMes = $this->dinheiroRealizado(FinancialRecord::REVENUE, now()->startOfMonth(), now()->endOfMonth());
        $receitaAnterior = $this->dinheiroRealizado(
            FinancialRecord::REVENUE,
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        );
        $despesaMes = $this->dinheiroRealizado(FinancialRecord::EXPENSE, now()->startOfMonth(), now()->endOfMonth());

        $receitaMensal = $this->serieMensal(FinancialRecord::REVENUE, 6);
        $despesaMensal = $this->serieMensal(FinancialRecord::EXPENSE, 6);

        return [
            'kpis' => [
                [
                    'label' => 'A receber',
                    'value' => Formatters::money($aReceber),
                    'hint' => Formatters::money($vencido).' vencidos',
                    'tone' => $vencido > 0 ? 'canceled' : 'open',
                    'icon' => 'fa-solid fa-wallet',
                ],
                [
                    'label' => 'A pagar',
                    'value' => Formatters::money($aPagar),
                    'hint' => $despesaEmAberto['vencido'].' '.($despesaEmAberto['vencido'] === 1 ? 'conta vencida' : 'contas vencidas'),
                    'tone' => $despesaEmAberto['vencido'] > 0 ? 'canceled' : 'waiting',
                    'icon' => 'fa-solid fa-file-invoice-dollar',
                ],
                [
                    'label' => 'Recebido no mês',
                    'value' => Formatters::money($receitaMes),
                    'tone' => 'done',
                    'icon' => 'fa-solid fa-arrow-trend-up',
                    'delta' => $this->deltaMoney($receitaMes - $receitaAnterior, 'vs. mês anterior'),
                    'spark' => $receitaMensal + [
                        'comparar' => $despesaMensal['valores'],
                        'legenda' => 'Dinheiro realizado por mês, últimos 6 meses',
                        'rotulo_a' => 'recebido',
                        'rotulo_b' => 'pago',
                        'prefixo' => 'R$ ',
                        'decimais' => 2,
                    ],
                ],
                [
                    'label' => 'Despesa do mês',
                    'value' => Formatters::money($despesaMes),
                    'hint' => 'Saldo do mês '.Formatters::money($receitaMes - $despesaMes),
                    'tone' => 'progress',
                    'icon' => 'fa-solid fa-arrow-trend-down',
                ],
            ],
            'dados' => [
                'a_receber' => $aReceber,
                'a_pagar' => $aPagar,
                'vencido' => $vencido,
                'vencido_pagar' => $despesaEmAberto['vencido_valor'],
                'receita_mes' => $receitaMes,
                'receita_anterior' => $receitaAnterior,
                'despesa_mes' => $despesaMes,
                'venceram' => FinancialRecord::query()
                    ->with('client')
                    ->revenue()
                    ->emAberto()
                    ->comPagado()
                    ->whereBetween('due_date', [today(), today()->addDays(15)])
                    ->orderBy('due_date')
                    ->limit(5)
                    ->get(),
                'pagamentos' => Payment::query()
                    ->with(['financialRecord:id,description,type', 'user:id,name'])
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(),
            ],
        ];
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function notificacoes(): array
    {
        if (! $this->pode('notifications.view')) {
            return [];
        }

        $naoLidas = Notification::query()->unread()->where('user_id', $this->usuario->id)->count();

        return [
            'kpis' => [
                [
                    'label' => 'Notificações sem leitura',
                    'value' => $naoLidas,
                    'tone' => $naoLidas > 0 ? 'waiting' : 'draft',
                    'icon' => 'fa-regular fa-bell',
                ],
            ],
            'dados' => [
                'total' => $naoLidas,
                'lista' => Notification::query()
                    ->where('user_id', $this->usuario->id)
                    ->latest()
                    ->limit(5)
                    ->get(),
            ],
        ];
    }

    /** @return array<int, array{label: string, value: int}> */
    private function base(): array
    {
        $base = [];

        if ($this->pode('users.view')) {
            $base[] = ['label' => 'Usuários com acesso', 'value' => User::query()->count()];
        }

        if ($this->pode('roles.view')) {
            $base[] = ['label' => 'Papéis da empresa', 'value' => Role::query()->count()];
        }

        if ($this->pode('roles.view')) {
            $base[] = ['label' => 'Permissões no catálogo', 'value' => Permission::query()->count()];
        }

        return $base;
    }

    /** @return array<int, array<string, mixed>> */
    private function distribuicao(array $distribuicao, int $total): array
    {
        if ($total === 0) {
            return [];
        }

        $ordem = array_keys(StatusCatalog::options('order'));
        $linhas = [];

        foreach ($ordem as $status) {
            $quantidade = (int) ($distribuicao[$status] ?? 0);

            if ($quantidade === 0) {
                continue;
            }

            $linhas[] = [
                'status' => $status,
                'label' => StatusCatalog::label('order', $status),
                'tone' => StatusCatalog::tone('order', $status),
                'total' => $quantidade,
                'porcentagem' => (int) round($quantidade / $total * 100),
            ];
        }

        return $linhas;
    }

    /** @return array{direction: string, text: string} */
    private function deltaInt(int $diferenca, string $rotulo): array
    {
        if ($diferenca === 0) {
            return ['direction' => 'flat', 'text' => 'Mesmo volume '.$rotulo];
        }

        return [
            'direction' => $diferenca > 0 ? 'up' : 'down',
            'text' => ($diferenca > 0 ? '+' : '-').abs($diferenca).' '.$rotulo,
        ];
    }

    /** @return array{direction: string, text: string} */
    private function deltaMoney(float $diferenca, string $rotulo): array
    {
        if (abs($diferenca) < 0.01) {
            return ['direction' => 'flat', 'text' => 'Mesmo valor '.$rotulo];
        }

        return [
            'direction' => $diferenca > 0 ? 'up' : 'down',
            'text' => ($diferenca > 0 ? '+' : '-').Formatters::money(abs($diferenca)).' '.$rotulo,
        ];
    }

    /**
     * Dinheiro que efetivamente mudou de mão no período, do lado que se pergunta.
     * É o pagamento que responde, nunca a coluna `amount` do lançamento: o valor
     * previsto entra na carteira no vencimento, e o que aconteceu no caixa é a soma
     * das linhas de pagamento — receita de despesa não se mistura porque o tipo está
     * no lançamento.
     */
    private function dinheiroRealizado(string $tipo, $inicio, $fim): float
    {
        return (float) Payment::query()
            ->whereHas('financialRecord', fn ($q) => $q->where('type', $tipo))
            ->whereBetween('paid_at', [$inicio, $fim])
            ->sum('amount');
    }

    /**
     * Um ponto por dia, do mais antigo ao mais recente, contado no banco com o
     * alcance de quem olha. Dia sem fato é zero, e zero é o que o traço mostra:
     * buraco no meio da linha seria mentira, assim como linha desenhada quando o
     * período inteiro está vazio. A coluna vem do chamador — nunca do pedido.
     *
     * @param  Builder  $consulta  já alcançada pelo usuário
     * @return array{valores: array<int, int>, rotulos: array<int, string>}
     */
    private function serieDiaria(Builder $consulta, string $coluna, int $dias): array
    {
        $inicio = now()->startOfDay()->subDays($dias - 1);

        $contados = $consulta
            ->where($coluna, '>=', $inicio)
            ->selectRaw('DATE('.$coluna.') as dia, count(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia')
            ->all();

        $valores = [];
        $rotulos = [];

        for ($i = 0; $i < $dias; $i++) {
            $dia = $inicio->copy()->addDays($i);
            $valores[] = (int) ($contados[$dia->toDateString()] ?? 0);
            $rotulos[] = $dia->format('d/m');
        }

        return ['valores' => $valores, 'rotulos' => $rotulos];
    }

    /**
     * Série de janelas agendadas. Diferente da contagem por data de um fato: o
     * calendário e o cartão "Agenda de hoje" chamam uma janela de "hoje" quando ela
     * toca no dia — quem começa antes e termina depois continua sendo hoje. A série
     * lê as janelas do período uma vez e conta por sobreposição, com a mesma regra
     * do escopo between, para o último ponto do traço ser o número do cartão e
     * não um número parecido.
     *
     * @param  Builder  $consulta  já alcançada pelo usuário
     * @return array{valores: array<int, int>, rotulos: array<int, string>}
     */
    private function serieJanelas(Builder $consulta, int $dias): array
    {
        $inicio = now()->startOfDay()->subDays($dias - 1);
        $fim = now()->endOfDay();

        $janelas = $consulta
            ->select('starts_at', 'ends_at')
            ->where('starts_at', '<=', $fim)
            ->where('ends_at', '>=', $inicio)
            ->get();

        $valores = [];
        $rotulos = [];

        for ($i = 0; $i < $dias; $i++) {
            $dia = $inicio->copy()->addDays($i);
            $termino = $dia->copy()->endOfDay();

            $valores[] = $janelas->filter(fn ($janela) => $janela->starts_at <= $termino
                && ($janela->ends_at ?? $janela->starts_at) >= $dia)->count();
            $rotulos[] = $dia->format('d/m');
        }

        return ['valores' => $valores, 'rotulos' => $rotulos];
    }

    /**
     * Dinheiro que mudou de mão por mês, do lado que se pergunta. É a mesma fonte
     * do KPI — `Payment`, nunca a coluna prevista do lançamento — só aberta em
     * janelas mensais para o traço ter o que contar.
     *
     * @return array{valores: array<int, float>, rotulos: array<int, string>}
     */
    private function serieMensal(string $tipo, int $meses): array
    {
        $inicio = now()->startOfMonth()->subMonthsNoOverflow($meses - 1);

        $contados = Payment::query()
            ->whereHas('financialRecord', fn (Builder $q) => $q->where('type', $tipo))
            ->where('paid_at', '>=', (clone $inicio)->startOfDay())
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as mes, sum(amount) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->all();

        $valores = [];
        $rotulos = [];

        for ($i = 0; $i < $meses; $i++) {
            $mes = (clone $inicio)->addMonthsNoOverflow($i);
            $valores[] = round((float) ($contados[$mes->format('Y-m')] ?? 0), 2);
            $rotulos[] = $mes->format('m/y');
        }

        return ['valores' => $valores, 'rotulos' => $rotulos];
    }

    /**
     * Dos serviços que terminaram nos últimos trinta dias de calendário — a mesma
     * régua dos cartões de cima —, quantos terminaram dentro do fim que a própria
     * ordem carregou. Ordem sem prazo previsto fica fora da
     * conta: sem prazo não há atraso a medir, e contá-la como pontual encheria o
     * anel de proporção emprestada. Sem conclusão na janela não há anel nenhum —
     * proporção de quê?
     *
     * @return array{percentual: int, no_prazo: int, total: int, tom: string}|null
     */
    private function pontualidade(): ?array
    {
        $base = fn () => $this->ordensDoUsuario()
            ->completed()
            ->whereNotNull('completed_at')
            ->whereNotNull('scheduled_ends_at')
            ->whereBetween('completed_at', [now()->startOfDay()->subDays(29), now()->endOfDay()]);

        $total = $base()->count();

        if ($total === 0) {
            return null;
        }

        $noPrazo = $base()->whereColumn('completed_at', '<=', 'scheduled_ends_at')->count();
        $percentual = (int) round($noPrazo / $total * 100);

        return [
            'percentual' => $percentual,
            'no_prazo' => $noPrazo,
            'total' => $total,
            'tom' => $percentual >= 80 ? 'done' : ($percentual >= 50 ? 'waiting' : 'canceled'),
        ];
    }

    private function pode(string $permissao): bool
    {
        return $this->usuario->hasPermission($permissao);
    }
}
