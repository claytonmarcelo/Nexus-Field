<?php

namespace App\Support;

use App\Models\FinancialRecord;
use App\Models\Product;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Os fechamentos de período do NEXUS-FIELD.
 *
 * Um relatório não é uma listagem com outro título: é uma pergunta sobre um
 * período, e a resposta tem de sair do MySQL com a mesma régua que a tela de
 * operação usa para gravar o fato. Por isso esta classe não inventa número nem
 * regra. As três contas que ela soma são as expressões que já mandam nos módulos:
 * `FinancialRecord::pagoSql()` (o dinheiro de uma conta), `ServiceOrder::totalPorOrdemSql()`
 * (o que uma ordem cobra) e `Ticket::prazoSql()` (o prazo que a prioridade impõe).
 * Se qualquer uma dessas mudar de tamanho, o relatório muda junto no mesmo commit —
 * e é isso que impede a tela de fechado de discordar da carteira.
 *
 * **Cada lado do período olha a data do próprio fato.** É a decisão que mais
 * desenha estas quatro telas: contas a receber têm *vencimento* e dinheiro tem
 * *data de pagamento*, e as duas janelas não coincidem numa operação real — a OS
 * concluída em 28/09 vence em 13/10 e o PIX cai em 15/10. Somar as duas coisas na
 * mesma coluna produziria um "recebido" que não bate com extrato nenhum, então o
 * relatório de financeiro mostra as duas somas lado a lado, cada uma na sua data, e
 * diz isso na tela.
 *
 * **Agregação em SQL, fatia em PHP.** Todo `groupBy` roda no MySQL; o que o PHP
 * faz é juntar as duas metades de um mesmo fechamento (ordens e visitas de um
 * técnico, conta e caixa de uma categoria) e fatiar a página. Juntar em `join`
 * com `groupBy` multiplicaria linhas e inflaria médias, e a subconsulta
 * correlacionada não resolve o outro lado. O conjunto de linhas de um fechado é
 * pequeno por natureza — categorias de vocabulário fechado, técnicos da empresa,
 * produtos movimentados — e é por isso que a página fatia a coleção materializada
 * em vez de chamar `paginate()`: o CSV da mesma tela precisa do fechado inteiro,
 * e as duas leituras têm de sair da mesma consulta.
 *
 * **Alcance é o do módulo.** Cada relatório pede `reports.view` e mais a
 * permissão de leitura do módulo que ele resume: o fechado de financeiro não sai
 * para quem não lê a carteira. Dentro da consulta, os recortes de ordem, chamado e
 * movimentação vêm dos mesmos `visiveisPara()` das listagens, então o número do
 * relatório é o que a pessoa já pode conferir linha por linha.
 */
class Relatorio
{
    /** Janela padrão quando a query string não traz datas: o mês corrente. */
    public const DIAS_PADRAO = 30;

    /**
     * Teto da janela, em dias. Existe porque `between` sobre um período pedido por
     * link pode ir de 1900 a hoje, e uma agregação dessas não é relatório: é a
     * consulta que derruba o banco da empresa pelo caminho mais fácil. O pedido
     * maior que o teto é aceito com o início trazido para dentro e um aviso na
     * tela dizendo exatamente o que foi cortado.
     */
    public const DIAS_MAXIMO = 366;

    /**
     * Os fechamentos da empresa. `rotulo` é o título da tela; `aba` é o nome curto
     * que cabe na navegação entre relatórios, e existe porque a lista de abas com o
     * título inteiro não cabe em tela nenhuma e ninguém ler "por categoria" três
     * vezes não é navegação.
     *
     * @var array<string, array{rotulo: string, aba: string, pergunta: string, icone: string, rota: string, modulo: string, modulo_rotulo: string, arquivo: string}>
     */
    public const CATALOGO = [
        'financeiro' => [
            'rotulo' => 'Financeiro por categoria',
            'aba' => 'Financeiro',
            'pergunta' => 'O que venceu no período, o que ainda falta e o que efetivamente mudou de mão.',
            'icone' => 'fa-solid fa-scale-balanced',
            'rota' => 'reports.financeiro',
            'modulo' => 'financial.view',
            'modulo_rotulo' => 'a carteira de contas a receber e a pagar',
            'arquivo' => 'relatorio-financeiro',
        ],
        'operacao' => [
            'rotulo' => 'Operação por técnico',
            'aba' => 'Operação',
            'pergunta' => 'Quem fechou ordens no período, quanto elas valeram e como foi o tempo de campo.',
            'icone' => 'fa-solid fa-helmet-safety',
            'rota' => 'reports.operacao',
            'modulo' => 'orders.view',
            'modulo_rotulo' => 'as ordens de serviço',
            'arquivo' => 'relatorio-operacao',
        ],
        'chamados' => [
            'rotulo' => 'Chamados e prazo de resposta',
            'aba' => 'Chamados',
            'pergunta' => 'Quantos protocolos abriram, quantos responderam dentro do prazo da prioridade.',
            'icone' => 'fa-regular fa-life-ring',
            'rota' => 'reports.chamados',
            'modulo' => 'tickets.view',
            'modulo_rotulo' => 'os chamados da mesa de atendimento',
            'arquivo' => 'relatorio-chamados',
        ],
        'estoque' => [
            'rotulo' => 'Estoque por produto',
            'aba' => 'Estoque',
            'pergunta' => 'O que entrou, saiu e foi consumido no período, e com que saldo cada item ficou.',
            'icone' => 'fa-solid fa-boxes-stacked',
            'rota' => 'reports.estoque',
            'modulo' => 'stock.view',
            'modulo_rotulo' => 'o livro-caixa do estoque',
            'arquivo' => 'relatorio-estoque',
        ],
    ];

    /** @var array<string, string> */
    public const ANGULOS = [
        'prioridade' => 'Por prioridade',
        'categoria' => 'Por categoria do serviço',
        'tecnico' => 'Por técnico',
    ];

    /** Ordem de leitura do fechado: o que mais aperta primeiro vem primeiro. */
    private const PRIORIDADES = ['urgent', 'high', 'normal', 'low'];

    private const ORDENAVEIS = [
        'financeiro' => ['rotulo', 'contas', 'previsto', 'pago', 'em_aberto', 'dinheiro'],
        'operacao' => ['rotulo', 'ordens', 'valor', 'tempo_execucao', 'visitas', 'tempo_local', 'distancia'],
        'chamados' => ['rotulo', 'chamados', 'em_andamento', 'resolvidos', 'no_prazo', 'tempo_resposta'],
        'estoque' => ['rotulo', 'linhas', 'compradas', 'consumidas', 'custo_consumido', 'saldo_central'],
    ];

    public function __construct(
        private readonly Request $request,
        private readonly User $usuario,
    ) {}

    /**
     * Os relatórios que esta conta pode abrir: `reports.view` já veio na rota, e
     * aqui entra a leitura do módulo resumido. Sem `financial.view` o cartão de
     * financeiro não aparece na lista e a rota dele responde 403 — não é botão
     * escondido, é a agregação que não roda.
     *
     * @return array<string, array<string, string>>
     */
    public function disponiveis(): array
    {
        return collect(self::CATALOGO)
            ->filter(fn (array $relatorio) => $this->usuario->hasPermission($relatorio['modulo']))
            ->all();
    }

    public static function chaveValida(string $chave): bool
    {
        return array_key_exists($chave, self::CATALOGO);
    }

    /**
     * A janela fechada do período, resolvida antes de qualquer consulta.
     *
     * As duas datas vêm como vieram: sem uma delas, a outra ganha o lado que falta
     * (de um início até hoje; até um fim, um mês para trás). Invertida, a janela é a
     * mesma e o intervalo se troca sozinho. Acima do teto, o início entra para
     * dentro e a tela avisa quanto foi cortado — relatório que corta dado em
     * silêncio é relatório que mente.
     *
     * @return array{inicio: Carbon, fim: Carbon, dias: int, cortado: int, aviso: ?string, etiqueta: string}
     */
    public function janela(): array
    {
        $inicio = $this->data($this->request->query('inicio'));
        $fim = $this->data($this->request->query('fim'));

        if ($inicio === null && $fim === null) {
            $inicio = now()->startOfMonth();
            $fim = today();
        } elseif ($inicio === null) {
            $inicio = $fim->copy()->subDays(self::DIAS_PADRAO - 1);
        } elseif ($fim === null) {
            $fim = $inicio->gt(today()) ? $inicio->copy()->addDays(self::DIAS_PADRAO - 1) : today();
        }

        if ($inicio->gt($fim)) {
            [$inicio, $fim] = [$fim->copy()->startOfDay(), $inicio->copy()->endOfDay()];
        }

        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->endOfDay();
        $dias = intval($inicio->diffInDays($fim)) + 1;
        $cortado = 0;

        if ($dias > self::DIAS_MAXIMO) {
            $cortado = $dias - self::DIAS_MAXIMO;
            $inicio = $fim->copy()->subDays(self::DIAS_MAXIMO - 1)->startOfDay();
            $dias = self::DIAS_MAXIMO;
        }

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'dias' => $dias,
            'cortado' => $cortado,
            'aviso' => $cortado === 0 ? null : sprintf(
                'A janela pedida tinha %s dias e o relatório fecha no máximo %s: o início foi trazido para %s. '
                .'Exporte em duas janelas para ver o período inteiro.',
                Formatters::decimal($dias + $cortado, 0),
                Formatters::decimal(self::DIAS_MAXIMO, 0),
                Formatters::date($inicio),
            ),
            'etiqueta' => Formatters::date($inicio).' a '.Formatters::date($fim).' · '.
                $dias.($dias === 1 ? ' dia' : ' dias'),
        ];
    }

    /**
     * Financeiro por categoria: as duas metades do período, uma por data.
     *
     * A primeira soma é de `financial_records` pelo *vencimento* na janela — o
     * fechado do que a empresa tinha a receber ou a pagar naquele mês, com o que já
     * foi baixado delas (qualquer data, porque `pagoSql()` responde pela conta, não
     * pelo calendário). A segunda é de `payments` pela *data do caixa*: o dinheiro
     * que mudou de mão dentro da janela, vindo de contas que venceram quando
     * venceram. As duas ficam lado a lado porque as duas perguntas são legítimas e
     * nenhuma delas se responde com a coluna da outra.
     *
     * @return array{linhas: Collection, metodos: Collection, totais: array<string, float|int>}
     */
    public function financeiro(): array
    {
        ['inicio' => $inicio, 'fim' => $fim] = $this->janela();
        $tipo = $this->tipo();

        $contas = FinancialRecord::query()
            ->selectRaw('financial_records.type, financial_records.category, '
                .'count(*) contas, coalesce(sum(financial_records.amount), 0) previsto, '
                .'coalesce(sum('.FinancialRecord::pagoSql().'), 0) pago, '
                .'sum(case when financial_records.status in (?, ?) '
                .'and financial_records.due_date < ? then 1 else 0 end) vencidas', [
                    FinancialRecord::PENDING,
                    FinancialRecord::PARTIAL,
                    today(),
                ])
            ->whereBetween('due_date', [$inicio, $fim])
            ->when($tipo !== null, fn (Builder $q) => $q->where('type', $tipo))
            ->groupBy('financial_records.type', 'financial_records.category')
            ->get();

        // O caixa lido cru, porque a linha do dinheiro mora em `payments` e o lado
        // (receita ou despesa) está no lançamento. O filtro de empresa é explícito:
        // `DB::table` não passa pelo CompanyScope, e uma agregação entre duas
        // tabelas sem a empresa na where somaria o país inteiro.
        $caixa = DB::table('payments')
            ->join('financial_records', 'financial_records.id', '=', 'payments.financial_record_id')
            ->selectRaw('financial_records.type, financial_records.category, '
                .'count(*) pagamentos, coalesce(sum(payments.amount), 0) realizado')
            ->where('payments.company_id', TenantContext::id())
            ->whereNull('financial_records.deleted_at')
            ->whereBetween('payments.paid_at', [$inicio, $fim])
            ->when($tipo !== null, fn ($q) => $q->where('financial_records.type', $tipo))
            ->groupBy('financial_records.type', 'financial_records.category')
            ->get();

        $metodos = DB::table('payments')
            ->selectRaw('payments.method, count(*) pagamentos, coalesce(sum(payments.amount), 0) total')
            ->where('payments.company_id', TenantContext::id())
            ->whereBetween('payments.paid_at', [$inicio, $fim])
            ->groupBy('payments.method')
            ->orderByRaw('coalesce(sum(payments.amount), 0) desc')
            ->get()
            ->map(fn (object $linha) => [
                'metodo' => StatusCatalog::label('payment_method', $linha->method),
                'pagamentos' => intval($linha->pagamentos),
                'total' => round((float) $linha->total, 2),
            ]);

        $linhas = $this->linhasFinanceiro($contas, $caixa, $tipo);

        return [
            'linhas' => $this->ordenar($linhas, 'financeiro', 'catalogo'),
            'metodos' => $metodos,
            'totais' => [
                'contas' => intval($linhas->sum('contas')),
                'previsto' => round(floatval($linhas->sum('previsto')), 2),
                'pago' => round(floatval($linhas->sum('pago')), 2),
                'em_aberto' => round(floatval($linhas->sum('em_aberto')), 2),
                'dinheiro' => round(floatval($linhas->sum('dinheiro')), 2),
                'vencidas' => intval($linhas->sum('vencidas')),
            ],
        ];
    }

    /**
     * Operação por técnico: a produção que fechou no período e a presença medida
     * em campo no mesmo período.
     *
     * São duas agregações porque são duas tabelas de fatos, e juntá-las num `join`
     * multiplicaria ordem por visita: um técnico com três ordens e doze passagens
     * teria o tempo médio de execução pesado doze vezes. Cada linha da coleção é o
     * técnico, e as médias saem de soma e contagem — nunca da média das médias, que
     * é a conta que ninguém consegue explicar quando discorda do painel.
     *
     * @return array{linhas: Collection, totais: array<string, float|int>}
     */
    public function operacao(): array
    {
        ['inicio' => $inicio, 'fim' => $fim] = $this->janela();

        $ordens = ServiceOrder::query()
            ->selectRaw('technician_id, count(*) ordens, '
                .'coalesce(sum('.ServiceOrder::totalPorOrdemSql().'), 0) valor, '
                .'coalesce(sum(case when started_at is not null and completed_at is not null '
                .'then timestampdiff(minute, started_at, completed_at) else 0 end), 0) minutos, '
                .'count(case when started_at is not null and completed_at is not null then 1 end) com_tempo, '
                .'coalesce(sum(discount), 0) descontos')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->groupBy('technician_id')
            ->get();

        $visitas = ServiceOrderCheckin::query()
            ->selectRaw('technician_id, count(*) visitas, '
                .'coalesce(sum(case when checkout_at is not null '
                .'then timestampdiff(minute, checkin_at, checkout_at) else 0 end), 0) minutos, '
                .'count(case when checkout_at is not null then 1 end) encerradas, '
                .'coalesce(sum(checkin_distance), 0) distancia, '
                .'count(checkin_distance) com_distancia, '
                .'sum(case when checkin_latitude is null then 1 else 0 end) sem_posicao, '
                .'sum(case when checkout_at is null then 1 else 0 end) abertas')
            ->whereBetween('checkin_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->groupBy('technician_id')
            ->get();

        $nomes = Technician::withTrashed()
            ->get(['id', 'name', 'status'])
            ->keyBy('id');

        // As duas metades são indexadas pelo mesmo número: zero é a chave de quem não
        // tem técnico apontado, porque `null` não serve de chave de coleção e uma
        // ordem sem dono precisa continuar dentro do fechado.
        $porOrdem = $ordens->keyBy(fn (object $linha) => intval($linha->technician_id ?? 0));
        $porVisita = $visitas->keyBy(fn (object $linha) => intval($linha->technician_id ?? 0));

        $chaves = $porOrdem->keys()->merge($porVisita->keys())->unique()->sort()->values();

        $linhas = $chaves->map(function (int $id) use ($porOrdem, $porVisita, $nomes): array {
            $ordem = $porOrdem->get($id);
            $visita = $porVisita->get($id);
            $tecnico = $id === 0 ? null : $nomes->get($id);

            $encerradas = intval($visita?->encerradas ?? 0);
            $comTempo = intval($ordem?->com_tempo ?? 0);
            $comDistancia = intval($visita?->com_distancia ?? 0);

            return [
                'chave' => strval($id),
                'rotulo' => $tecnico?->name ?? 'Sem técnico apontado',
                'estado' => $tecnico === null
                    ? null
                    : StatusCatalog::label('technician', $tecnico->status),
                'ordens' => intval($ordem?->ordens ?? 0),
                'valor' => round(floatval($ordem?->valor ?? 0), 2),
                'descontos' => round(floatval($ordem?->descontos ?? 0), 2),
                'tempo_execucao' => $comTempo === 0 ? null : (int) round(floatval($ordem->minutos) / $comTempo),
                'visitas' => intval($visita?->visitas ?? 0),
                'tempo_local' => $encerradas === 0 ? null : (int) round(floatval($visita->minutos) / $encerradas),
                'distancia' => $comDistancia === 0 ? null : round(floatval($visita->distancia) / $comDistancia),
                'sem_posicao' => intval($visita?->sem_posicao ?? 0),
                'visitas_abertas' => intval($visita?->abertas ?? 0),
            ];
        });

        return [
            'linhas' => $this->ordenar($linhas, 'operacao'),
            'totais' => [
                'tecnicos' => $linhas->count(),
                'ordens' => intval($linhas->sum('ordens')),
                'valor' => round(floatval($linhas->sum('valor')), 2),
                'descontos' => round(floatval($linhas->sum('descontos')), 2),
                'visitas' => intval($linhas->sum('visitas')),
                'sem_posicao' => intval($linhas->sum('sem_posicao')),
                'visitas_abertas' => intval($linhas->sum('visitas_abertas')),
            ],
        ];
    }

    /**
     * Chamados pelo ângulo escolhido, sempre abertos na janela.
     *
     * O período pergunta por `opened_at`: o fechado de um mês responde pelos
     * protocolos que nasceram ali, e é sobre eles que se mede a resposta — resolver
     * um chamado de agosto em setembro é resposta de setembro contada no histórico
     * de agosto. O prazo que decide "dentro do prazo" é o de `Ticket::PRAZO_HORAS`,
     * lido da mesma expressão SQL que a listagem usa para marcar atrasado, então a
     * taxa do relatório e a alerta da tela respondem pelo mesmo minuto.
     *
     * @return array{linhas: Collection, angulo: string, totais: array<string, float|int>}
     */
    public function chamados(): array
    {
        ['inicio' => $inicio, 'fim' => $fim] = $this->janela();
        $angulo = $this->angulo();

        $prazo = Ticket::prazoSql();
        $andamento = Ticket::EM_ANDAMENTO;

        $consulta = Ticket::query()
            ->selectRaw($this->colunaDoAngulo($angulo).' as chave, '
                .'count(*) chamados, '
                .'sum(case when priority in (?, ?) then 1 else 0 end) criticos, '
                .'sum(case when status in ('.$this->lista($andamento).') then 1 else 0 end) em_andamento, '
                .'sum(case when status in ('.$this->lista($andamento).') '
                .'and opened_at < now() - interval ('.$prazo.') hour then 1 else 0 end) vencendo, '
                .'count(case when resolved_at is not null then 1 end) resolvidos, '
                .'sum(case when resolved_at is not null '
                .'and resolved_at <= opened_at + interval ('.$prazo.') hour then 1 else 0 end) no_prazo, '
                .'coalesce(sum(case when resolved_at is not null '
                .'then timestampdiff(minute, opened_at, resolved_at) else 0 end), 0) minutos', [
                    'high',
                    'urgent',
                ])
            ->whereBetween('opened_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->groupBy('chave');

        $grupos = $consulta->get()->keyBy('chave');

        $nomes = match ($angulo) {
            'categoria' => ServiceCategory::query()->orderBy('name')->pluck('name', 'slug'),
            'tecnico' => Technician::withTrashed()->pluck('name', 'id'),
            default => collect(),
        };

        $chaves = $angulo === 'prioridade'
            ? collect(self::PRIORIDADES)
            : $grupos->keys();

        $linhas = $chaves->values()->map(function ($chave) use ($grupos, $nomes, $angulo): ?array {
            $grupo = $grupos->get(strval($chave));

            // No ângulo de prioridade o vocabulário é fechado: linha sem chamado no
            // período continua na tela com zero, porque "a prioridade urgente não
            // apareceu este mês" é resposta, e sumi-la faria a tabela parecer curta.
            if ($grupo === null && $angulo !== 'prioridade') {
                return null;
            }

            $resolvidos = intval($grupo->resolvidos ?? 0);
            $rotulo = $angulo === 'prioridade'
                ? StatusCatalog::label('priority', strval($chave))
                : $this->rotuloDoGrupo($angulo, $chave, $nomes);

            return [
                'chave' => strval($chave),
                'rotulo' => $rotulo,
                'prazo' => $angulo === 'prioridade'
                    ? (Ticket::PRAZO_HORAS[strval($chave)] ?? Ticket::PRAZO_PADRAO)
                    : null,
                'chamados' => intval($grupo->chamados ?? 0),
                'criticos' => intval($grupo->criticos ?? 0),
                'em_andamento' => intval($grupo->em_andamento ?? 0),
                'vencendo' => intval($grupo->vencendo ?? 0),
                'resolvidos' => $resolvidos,
                'no_prazo' => intval($grupo->no_prazo ?? 0),
                'taxa' => $resolvidos === 0 ? null : round(intval($grupo->no_prazo) / $resolvidos * 100, 1),
                'tempo_resposta' => $resolvidos === 0 ? null : round(floatval($grupo->minutos) / $resolvidos / 60, 1),
                'porcentagem' => 0,
            ];
        })->filter()->values();

        $total = intval($linhas->sum('chamados'));

        $linhas = $this->ordenar(
            $linhas->map(fn (array $linha) => [
                ...$linha,
                'porcentagem' => $total === 0 ? 0 : round($linha['chamados'] / $total * 100, 1),
            ]),
            'chamados',
            $angulo === 'prioridade' ? 'catalogo' : '',
        );

        return [
            'linhas' => $linhas,
            'angulo' => $angulo,
            'totais' => [
                'grupos' => $linhas->count(),
                'chamados' => $total,
                'criticos' => intval($linhas->sum('criticos')),
                'em_andamento' => intval($linhas->sum('em_andamento')),
                'vencendo' => intval($linhas->sum('vencendo')),
                'resolvidos' => intval($linhas->sum('resolvidos')),
                'no_prazo' => intval($linhas->sum('no_prazo')),
                'taxa' => $total === 0 || intval($linhas->sum('resolvidos')) === 0
                    ? null
                    : round(intval($linhas->sum('no_prazo')) / intval($linhas->sum('resolvidos')) * 100, 1),
            ],
        ];
    }

    /**
     * Estoque por produto no período.
     *
     * Só entram na tabela os produtos com pelo menos uma movimentação na janela:
     * item que ninguém tocou não é fechado de período, é catálogo. O saldo da
     * última coluna, ao contrário, é de hoje e vem do `CASE` de
     * `Product::centralBalanceQuery()` — a régua da fase 16, com todos os sinais,
     * e não uma soma do período, que daria um saldo que não existe em lugar nenhum.
     *
     * @return array{linhas: Collection, reposicao: Collection, totais: array<string, float|int>}
     */
    public function estoque(): array
    {
        ['inicio' => $inicio, 'fim' => $fim] = $this->janela();

        $movimentos = StockMovement::query()
            ->selectRaw('product_id, count(*) linhas, '
                .'coalesce(sum(case when type = ? then quantity else 0 end), 0) compradas, '
                .'coalesce(sum(case when type = ? then quantity else 0 end), 0) carregadas, '
                .'coalesce(sum(case when type = ? then quantity else 0 end), 0) consumidas, '
                .'coalesce(sum(case when type = ? then quantity else 0 end), 0) devolvidas, '
                .'coalesce(sum(case when type = ? then quantity else 0 end), 0) ajustadas, '
                .'coalesce(sum(case when type = ? then quantity * unit_cost else 0 end), 0) custo', [
                    'purchase',
                    'load',
                    'consume',
                    'return',
                    'adjustment',
                    'consume',
                ])
            ->whereBetween('recorded_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->groupBy('product_id')
            ->get();

        $produtos = Product::query()
            ->withCentralBalance()
            ->whereIn('id', $movimentos->pluck('product_id')->all())
            ->get()
            ->keyBy('id');

        $linhas = $movimentos->map(function (object $movimento) use ($produtos): ?array {
            $produto = $produtos->get($movimento->product_id);

            if ($produto === null) {
                return null;
            }

            $saldo = round(floatval($produto->central_balance ?? 0), 4);
            $ponto = round(floatval($produto->reorder_point ?? 0), 4);

            return [
                'chave' => strval($produto->id),
                'rotulo' => $produto->name.' ('.$produto->sku.')',
                // Nome e SKU separados para a tela desenhar no padrão das outras
                // listagens (nome em destaque, código em mono), enquanto `rotulo`
                // continua sendo o que a ordenação e o CSV usam.
                'nome' => $produto->name,
                'sku' => $produto->sku,
                'unidade' => Product::UNIDADES[$produto->unit] ?? $produto->unit,
                'linhas' => intval($movimento->linhas),
                'compradas' => round(floatval($movimento->compradas), 4),
                'carregadas' => round(floatval($movimento->carregadas), 4),
                'consumidas' => round(floatval($movimento->consumidas), 4),
                'devolvidas' => round(floatval($movimento->devolvidas), 4),
                'ajustadas' => round(floatval($movimento->ajustadas), 4),
                'custo_consumido' => round(floatval($movimento->custo), 2),
                'saldo_central' => $saldo,
                'ponto_reposicao' => $ponto,
                // A mesma comparação do escopo `belowReorderPoint()` da listagem de
                // produtos: saldo abaixo do ponto é alerta no dois lados, inclusive
                // quando o ponto é zero e o saldo está negativo.
                'abaixo' => $saldo < $ponto,
            ];
        })->filter()->values();

        return [
            'linhas' => $this->ordenar($linhas, 'estoque'),
            'reposicao' => $linhas->where('abaixo', true)->sortBy('saldo_central')->values(),
            'totais' => [
                'produtos' => $linhas->count(),
                'linhas' => intval($linhas->sum('linhas')),
                'compradas' => round(floatval($linhas->sum('compradas')), 4),
                'consumidas' => round(floatval($linhas->sum('consumidas')), 4),
                'custo_consumido' => round(floatval($linhas->sum('custo_consumido')), 2),
                'abaixo' => $linhas->where('abaixo', true)->count(),
            ],
        ];
    }

    /**
     * O fechamento curto do hub: seis números que respondem pelo período antes de
     * a pessoa escolher qual relatório abrir. Cada um é uma agregação de uma
     * coluna, nenhuma delas repete a conta das telas — o saldo do período é
     * receita menos despesa realizada, lida no mesmo `payments` do relatório.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fechamento(): array
    {
        ['inicio' => $inicio, 'fim' => $fim] = $this->janela();

        $caixa = DB::table('payments')
            ->join('financial_records', 'financial_records.id', '=', 'payments.financial_record_id')
            ->selectRaw('financial_records.type, coalesce(sum(payments.amount), 0) total')
            ->where('payments.company_id', TenantContext::id())
            ->whereNull('financial_records.deleted_at')
            ->whereBetween('payments.paid_at', [$inicio, $fim])
            ->groupBy('financial_records.type')
            ->pluck('total', 'type');

        $receita = round(floatval($caixa[FinancialRecord::REVENUE] ?? 0), 2);
        $despesa = round(floatval($caixa[FinancialRecord::EXPENSE] ?? 0), 2);

        $ordens = ServiceOrder::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->selectRaw('count(*) q, coalesce(sum('.ServiceOrder::totalPorOrdemSql().'), 0) valor')
            ->first();

        $chamados = Ticket::query()
            ->whereBetween('opened_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->selectRaw('count(*) q, '
                .'sum(case when status in ('.$this->lista(Ticket::EM_ANDAMENTO).') then 1 else 0 end) andamento')
            ->first();

        $visitas = ServiceOrderCheckin::query()
            ->whereBetween('checkin_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->count();

        $estoque = StockMovement::query()
            ->whereBetween('recorded_at', [$inicio, $fim])
            ->visiveisPara($this->usuario)
            ->selectRaw('count(*) q, count(distinct product_id) produtos')
            ->first();

        return [
            'receita' => $receita,
            'despesa' => $despesa,
            'saldo' => round($receita - $despesa, 2),
            'ordens' => intval($ordens->q ?? 0),
            'valor' => round(floatval($ordens->valor ?? 0), 2),
            'chamados' => intval($chamados->q ?? 0),
            'chamados_andamento' => intval($chamados->andamento ?? 0),
            'visitas' => $visitas,
            'movimentacoes' => intval($estoque->q ?? 0),
            'produtos' => intval($estoque->produtos ?? 0),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $linhas
     * @return Collection<int, array<string, mixed>>
     */
    private function linhasFinanceiro(Collection $contas, Collection $caixa, ?string $tipo): Collection
    {
        $porCategoria = $contas->keyBy(fn (object $linha) => $linha->type.'|'.($linha->category ?? ''));
        $porCaixa = $caixa->keyBy(fn (object $linha) => $linha->type.'|'.($linha->category ?? ''));

        $esperadas = collect(FinancialRecord::categorias($tipo))
            ->keys()
            ->map(fn (string $categoria) => $this->ladoDaCategoria($categoria).'|'.$categoria);

        // O vocabulário define a ordem das linhas, não o tamanho da tabela: categoria
        // sem fato no período fica de fora, porque onze linhas com oito zeros não são
        // fechado, são o cadastro copiado. Par fora do vocabulário (dado importado,
        // lançamento anterior à fase 17) entra depois das conhecidas: linha que
        // ninguém catalogou é justamente a que o escritório precisa ver.
        $chaves = $esperadas
            ->merge($porCategoria->keys())
            ->merge($porCaixa->keys())
            ->unique();

        return $chaves->map(function (string $chave) use ($porCategoria, $porCaixa, $tipo): ?array {
            [$tipoLinha, $categoria] = explode('|', $chave, 2);

            if ($tipo !== null && $tipo !== $tipoLinha) {
                return null;
            }

            $conta = $porCategoria->get($chave);
            $dinheiro = $porCaixa->get($chave);

            if ($conta === null && $dinheiro === null) {
                return null;
            }

            $previsto = round(floatval($conta->previsto ?? 0), 2);
            $pago = round(floatval($conta->pago ?? 0), 2);

            return [
                'chave' => $chave,
                'tipo' => $tipoLinha,
                'rotulo' => FinancialRecord::rotuloCategoria($categoria),
                'contas' => intval($conta->contas ?? 0),
                'previsto' => $previsto,
                'pago' => $pago,
                'em_aberto' => round(max(0, $previsto - $pago), 2),
                'vencidas' => intval($conta->vencidas ?? 0),
                'dinheiro' => round(floatval($dinheiro->realizado ?? 0), 2),
                'pagamentos' => intval($dinheiro->pagamentos ?? 0),
            ];
        })->filter()->values();
    }

    /** O catálogo de receitas e despesas é um só vocabulário; o lado vem da chave. */
    private function ladoDaCategoria(string $categoria): string
    {
        return array_key_exists($categoria, FinancialRecord::CATEGORIAS[FinancialRecord::REVENUE])
            ? FinancialRecord::REVENUE
            : FinancialRecord::EXPENSE;
    }

    /**
     * Fatiar o fechado para a tela. O `LengthAwarePaginator` recebe a coleção
     * inteira porque o total da página é o total do relatório, não o de uma
     * consulta com `limit` — e o CSV da mesma tela baixa estas mesmas linhas.
     *
     * @param  Collection<int, array<string, mixed>>  $linhas
     */
    public function paginar(Collection $linhas, string $rota): LengthAwarePaginator
    {
        $porPagina = ListFilters::porPagina($this->request);
        $pagina = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $linhas->forPage($pagina, $porPagina)->values(),
            $linhas->count(),
            $porPagina,
            $pagina,
            [
                'path' => $rota,
                'query' => $this->request->query(),
            ],
        );
    }

    /**
     * Ordena a coleção materializada pela coluna pedida na query string, com a
     * lista fechada do relatório. A coluna nunca chega ao SQL, então não há
     * superfície de injeção aqui; o que se garante é que a ordem pedida é a mesma
     * da tela e do CSV, e que média nenhuma se ordena como texto.
     *
     * `$ordemDoCatalogo` é o marcador de quem tem ordem própria — as categorias do
     * vocabulário financeiro, as prioridades do chamado — e vale quando nada foi
     * pedido: ordenar por nome uma tabela que o escritório lê de cima para baixo em
     * ordem de criticidade é trocar a resposta por um alfabeto.
     *
     * Nulo é "sem medida" e vai para o fim da fila nos dois sentidos, porque ordem
     * crescente por tempo de execução com quem não tem carimbo na frente é ler a
     * tabela inteira para nada.
     *
     * @param  Collection<int, array<string, mixed>>  $linhas
     * @return Collection<int, array<string, mixed>>
     */
    private function ordenar(Collection $linhas, string $relatorio, string $ordemDoCatalogo = ''): Collection
    {
        $pedida = strval($this->request->query('ordena'));

        // "Nada pedido" e "pediu o nome" são perguntas diferentes, e a tela depende
        // dessa diferença: o link de ordenar por nome existe justamente na tabela que
        // abre em ordem de vocabulário, e engolir um `ordena=rotulo` explícito dentro
        // do padrão deixaria o cabeçalho clicável sem efeito nenhum — botão que não
        // ordena é a propaganda de uma função que não existe.
        if ($pedida === '' || ! in_array($pedida, self::ORDENAVEIS[$relatorio], true)) {
            $coluna = $ordemDoCatalogo === '' ? 'rotulo' : '';
        } else {
            $coluna = $pedida;
        }

        if ($coluna === '') {
            return $linhas;
        }

        $descendente = strtolower(strval($this->request->query('direcao'))) === 'desc';

        // Duas passadas estáveis: primeiro pelo nome, que desempata, depois pela
        // coluna pedida. A ordenação do PHP 8 é estável, então o empate sobrevive.
        $ordenadas = $linhas
            ->sortBy(fn (array $linha) => mb_strtolower(strval($linha['rotulo'] ?? '')))
            ->sortBy(
                fn (array $linha) => $this->valorDaOrdem($linha, $coluna),
                SORT_REGULAR,
                $descendente,
            );

        // O fim da fila é o mesmo nos dois sentidos: ordem crescente por tempo de
        // execução com quem não tem carimbo na frente é ler a tabela inteira para
        // nada, e decrescente com ele no topo é a mesma leitura invertida.
        return $ordenadas
            ->filter(fn (array $linha) => ($linha[$coluna] ?? null) !== null)
            ->concat($ordenadas->filter(fn (array $linha) => ($linha[$coluna] ?? null) === null))
            ->values();
    }

    /**
     * A chave com que a coluna pedida compara: texto como texto (minúsculo, porque
     * acento e maiúscula não podem embaralhar a ordem de um nome) e número como
     * número, porque ordenar `valor` na ordem do alfabeto é ter 9 acima de 10. Sem
     * medida vira `INF` só para não quebrar a comparação de tipos — quem não tem
     * número é separado depois, no fim da fila.
     */
    private function valorDaOrdem(array $linha, string $coluna): string|float
    {
        $valor = $linha[$coluna] ?? null;

        if (is_string($valor)) {
            return mb_strtolower($valor);
        }

        return $valor === null ? INF : (float) $valor;
    }

    private function tipo(): ?string
    {
        $valor = trim(strval($this->request->query('tipo')));

        return in_array($valor, [FinancialRecord::REVENUE, FinancialRecord::EXPENSE], true)
            ? $valor
            : null;
    }

    private function angulo(): string
    {
        $valor = trim(strval($this->request->query('angulo')));

        return array_key_exists($valor, self::ANGULOS) ? $valor : 'prioridade';
    }

    /**
     * A coluna do grupo, escolhida dentro de um `match` fechado: o ângulo vem da
     * query string e o nome da coluna entra em `selectRaw`, então uma coluna livre
     * seria a superfície de injeção mais barata deste projeto. Fora das três chaves
     * de `ANGULOS` nem se chega aqui, e as três não têm valor de usuário.
     */
    private function colunaDoAngulo(string $angulo): string
    {
        return match ($angulo) {
            'categoria' => "ifnull(category, '')",
            // Zero é o chamado que ninguém foi apontado para atender: o grupo
            // existe, o nome não, e a linha continua no fechado.
            'tecnico' => 'ifnull(technician_id, 0)',
            default => 'priority',
        };
    }

    /**
     * O nome do grupo, lido do cadastro que lhe dá nome. Categoria e técnico têm
     * tabela própria; um slug órfão (categoria apagada depois do chamado aberto) ou
     * um chamado sem técnico se descreve sozinho em vez de sumir do fechado, porque
     * é assim que uma linha perdida vira investigação de dado.
     *
     * @param  Collection<array-key, string>  $nomes
     */
    private function rotuloDoGrupo(string $angulo, mixed $chave, Collection $nomes): string
    {
        $valor = strval($chave);

        return match ($angulo) {
            'tecnico' => $valor === '0'
                ? 'Sem técnico apontado'
                : strval($nomes->get($valor) ?? Str::ucfirst(str_replace('_', ' ', $valor))),
            'categoria' => $valor === ''
                ? 'Sem categoria'
                : strval($nomes->get($valor) ?? Str::ucfirst(str_replace('_', ' ', $valor))),
            default => StatusCatalog::label('priority', $valor),
        };
    }

    /**
     * Lista de valores para dentro de um `in (...)` cru, sempre de constante de
     * classe — nunca de request. Os placeholders não cabem numa expressão de
     * `case` reutilizada, e aqui não há nada do usuário para escapar.
     *
     * @param  array<int, string>  $valores
     */
    private function lista(array $valores): string
    {
        return "'".implode("', '", $valores)."'";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cabecalho(string $relatorio): array
    {
        return match ($relatorio) {
            'operacao' => ['Técnico', 'Estado', 'Ordens concluídas', 'Valor gerado', 'Descontos dados',
                'Tempo médio de execução', 'Visitas', 'Tempo médio no local', 'Distância média da chegada',
                'Visitas sem posição', 'Visitas ainda abertas'],
            'chamados' => ['Grupo', 'Chamados', '% do período', 'Críticos', 'Em andamento', 'Fora do prazo agora',
                'Resolvidos', 'Resolvidos no prazo', 'Taxa no prazo', 'Tempo médio de resposta'],
            'estoque' => ['Produto', 'Unidade', 'Movimentações', 'Compradas', 'Carregadas', 'Consumidas',
                'Devolvidas', 'Ajustadas', 'Custo do consumo', 'Saldo central hoje', 'Ponto de reposição',
                'Abaixo do ponto'],
            default => ['Lado do caixa', 'Categoria', 'Contas com vencimento no período', 'Valor previsto',
                'Já baixado dessas contas', 'Ainda em aberto', 'Vencidas no período',
                'Dinheiro que entrou ou saiu no período', 'Pagamentos do período'],
        };
    }

    /**
     * As linhas do CSV, na mesma ordem da tela e a partir da mesma coleção. Os
     * números saem com `Formatters`, porque exportado que discorda da tela é
     * exportado que ninguém assina.
     *
     * @return array<int, array<int, mixed>>
     */
    public function linhasParaExportar(Collection $linhas, string $relatorio): array
    {
        return $linhas->map(fn (array $linha) => match ($relatorio) {
            'operacao' => [
                $linha['rotulo'],
                $linha['estado'] ?? 'Sem ficha de técnico',
                $linha['ordens'],
                Formatters::money($linha['valor']),
                Formatters::money($linha['descontos']),
                $linha['tempo_execucao'] === null ? Formatters::TIME_NULL : Formatters::duration($linha['tempo_execucao']),
                $linha['visitas'],
                $linha['tempo_local'] === null ? Formatters::TIME_NULL : Formatters::duration($linha['tempo_local']),
                $linha['distancia'] === null ? Formatters::TIME_NULL : Formatters::decimal($linha['distancia'], 0).' m',
                $linha['sem_posicao'],
                $linha['visitas_abertas'],
            ],
            'chamados' => [
                $linha['rotulo'],
                $linha['chamados'],
                $linha['chamados'] === 0 ? Formatters::TIME_NULL : Formatters::decimal($linha['porcentagem'], 1).'%',
                $linha['criticos'],
                $linha['em_andamento'],
                $linha['vencendo'],
                $linha['resolvidos'],
                $linha['no_prazo'],
                $linha['taxa'] === null ? Formatters::TIME_NULL : Formatters::decimal($linha['taxa'], 1).'%',
                $linha['tempo_resposta'] === null ? Formatters::TIME_NULL : Formatters::decimal($linha['tempo_resposta'], 1).' h',
            ],
            'estoque' => [
                $linha['rotulo'],
                $linha['unidade'],
                $linha['linhas'],
                Formatters::decimal($linha['compradas'], 2),
                Formatters::decimal($linha['carregadas'], 2),
                Formatters::decimal($linha['consumidas'], 2),
                Formatters::decimal($linha['devolvidas'], 2),
                Formatters::decimal($linha['ajustadas'], 2),
                Formatters::money($linha['custo_consumido']),
                Formatters::decimal($linha['saldo_central'], 2),
                Formatters::decimal($linha['ponto_reposicao'], 2),
                $linha['abaixo'] ? 'abaixo do ponto' : 'no ponto',
            ],
            default => [
                StatusCatalog::label('financial_type', $linha['tipo']),
                $linha['rotulo'],
                $linha['contas'],
                Formatters::money($linha['previsto']),
                Formatters::money($linha['pago']),
                Formatters::money($linha['em_aberto']),
                $linha['vencidas'],
                Formatters::money($linha['dinheiro']),
                $linha['pagamentos'],
            ],
        })->all();
    }

    private function data(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
