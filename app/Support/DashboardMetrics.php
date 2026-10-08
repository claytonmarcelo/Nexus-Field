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

        $concluidasSete = $this->ordensDoUsuario()
            ->completed()
            ->whereBetween('completed_at', [now()->subDays(7), now()])
            ->count();
        $concluidasAnteriores = $this->ordensDoUsuario()
            ->completed()
            ->whereBetween('completed_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

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

        return [
            'kpis' => [
                [
                    'label' => 'Agenda de hoje',
                    'value' => $hoje->count(),
                    'hint' => $proximos.' compromissos em 7 dias',
                    'tone' => 'waiting',
                    'icon' => 'fa-regular fa-calendar-check',
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

        return [
            'kpis' => [
                [
                    'label' => 'Itens abaixo do ponto de reposição',
                    'value' => $abaixo->count(),
                    'hint' => 'Saldo central derivado das movimentações',
                    'tone' => $abaixo->isEmpty() ? 'done' : 'canceled',
                    'icon' => 'fa-solid fa-boxes-stacked',
                ],
            ],
            'dados' => [
                'reposicao' => $abaixo->sortBy('central_balance')->take(5)->values(),
            ],
        ];
    }

    /** @return array{kpis: array<int, array<string, mixed>>, dados: array<string, mixed>}|[] */
    private function financeiro(): array
    {
        if (! $this->pode('financial.view')) {
            return [];
        }

        $aReceber = (float) FinancialRecord::query()->revenue()->pending()->sum('amount');
        $vencido = (float) FinancialRecord::query()->revenue()->overdue()->sum('amount');

        $receitaMes = $this->receitaRealizada(now()->startOfMonth(), now()->endOfMonth());
        $receitaAnterior = $this->receitaRealizada(
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        );
        $despesaMes = (float) FinancialRecord::query()->expense()->paid()
            ->occurredBetween(now()->startOfMonth(), now()->endOfMonth())
            ->sum('amount');

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
                    'label' => 'Recebido no mês',
                    'value' => Formatters::money($receitaMes),
                    'tone' => 'done',
                    'icon' => 'fa-solid fa-arrow-trend-up',
                    'delta' => $this->deltaMoney($receitaMes - $receitaAnterior, 'vs. mês anterior'),
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
                'vencido' => $vencido,
                'receita_mes' => $receitaMes,
                'receita_anterior' => $receitaAnterior,
                'despesa_mes' => $despesaMes,
                'venceram' => FinancialRecord::query()
                    ->with('client')
                    ->revenue()
                    ->pending()
                    ->whereBetween('due_date', [today(), today()->addDays(15)])
                    ->orderBy('due_date')
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
     * Receita realizada é o pagamento efetivo, e só contam os pagamentos de
     * lançamentos de receita: pagamento de despesa não entra no caixa de vendas.
     */
    private function receitaRealizada($inicio, $fim): float
    {
        return (float) Payment::query()
            ->whereHas('financialRecord', fn ($q) => $q->revenue())
            ->whereBetween('paid_at', [$inicio, $fim])
            ->sum('amount');
    }

    private function pode(string $permissao): bool
    {
        return $this->usuario->hasPermission($permissao);
    }
}
