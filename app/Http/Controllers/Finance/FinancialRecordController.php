<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\Client;
use App\Models\FinancialRecord;
use App\Models\ServiceOrder;
use App\Services\Finance\LancamentoDeConta;
use App\Services\Recusa;
use App\Support\Export;
use App\Support\Formatters;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * As contas que a empresa tem a receber e a pagar.
 *
 * Esta é a fachada: alcance de leitura, regra de campo, vocabulário de categoria e
 * a cara da resposta. A caneta é de `App\Services\Finance\LancamentoDeConta` — a
 * linha que nasce, é alterada, cancelada, reaberta, apagada, restaurada e emitida
 * da ordem — e de `App\Services\Finance\RegistroDePagamento` para o dinheiro que
 * mudou de mão. Nenhum dos dois estados digitáveis existe aqui: `status` é derivado
 * da soma dos pagamentos, e um select de "Pago" no formulário seria a conta
 * quitada sem um real no extrato.
 *
 * O que a tela ainda decide é o que só a tela sabe: quais categorias existem por
 * tipo (`FinancialRecord::CATEGORIAS`, vocabulário fechado porque o relatório soma
 * por ela), quais ordens aparecem no select de origem e qual estado está sendo
 * filtrado. Carteira, valor, data do fato e o que muda de mão saem do banco, dentro
 * do serviço.
 */
class FinancialRecordController extends Controller
{
    use EnxergaAOrdem;

    private const ORDENAVEIS = ['due_date', 'amount', 'description', 'created_at'];

    private const FILTROS = ['busca', 'tipo', 'estado', 'categoria', 'cliente', 'ordem', 'inicio', 'fim'];

    public function __construct(private readonly LancamentoDeConta $conta) {}

    public function index(Request $request): View
    {
        $consulta = $this->consulta($request);

        return view('financial.index', [
            'lancamentos' => (clone $consulta)
                ->comPagado()
                ->with(['client:id,name,trade_name', 'serviceOrder:id,number'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'due_date', 'asc'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'totais' => FinancialRecord::totais($consulta),
            'tipos' => StatusCatalog::options('financial_type'),
            'estados' => $this->estados(),
            'categorias' => FinancialRecord::categorias(),
            'clientes' => $this->clientes(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
        ]);
    }

    public function create(Request $request): View
    {
        $tipo = strval($request->query('tipo', FinancialRecord::REVENUE));

        return view('financial.form', [
            'lancamento' => new FinancialRecord([
                'type' => in_array($tipo, [FinancialRecord::REVENUE, FinancialRecord::EXPENSE], true)
                    ? $tipo
                    : FinancialRecord::REVENUE,
                'due_date' => now()->addDays(15)->startOfDay(),
            ]),
            'tipos' => StatusCatalog::options('financial_type'),
            'categorias' => FinancialRecord::CATEGORIAS,
            'clientes' => $this->clientes(),
            'ordens' => $this->ordensCobraveis(),
            'editando' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validado = $request->validate($this->regras($request), $this->mensagens());

        $lancamento = $this->conta->criar($validado);

        return redirect()
            ->route('financial.show', $lancamento)
            ->with('status', sprintf(
                '%s "%s" de %s aberta. O estado dela é a soma dos pagamentos, e ainda não há nenhum.',
                $lancamento->eReceita() ? 'Cobrança' : 'Conta a pagar',
                $lancamento->description,
                Formatters::money($lancamento->amount),
            ));
    }

    public function show(FinancialRecord $registro): View
    {
        $registro->load(['client', 'serviceOrder:id,number,title,client_id', 'payments.user']);

        return view('financial.show', [
            'lancamento' => $registro,
            'pago' => $registro->valorPago(),
            'saldo' => $registro->saldo(),
            'metodos' => StatusCatalog::options('payment_method'),
        ]);
    }

    public function edit(FinancialRecord $registro): View
    {
        return view('financial.form', [
            'lancamento' => $registro,
            'tipos' => StatusCatalog::options('financial_type'),
            'categorias' => FinancialRecord::CATEGORIAS,
            'clientes' => $this->clientes(),
            'ordens' => $this->ordensCobraveis(),
            'editando' => true,
        ]);
    }

    /**
     * O formulário de conta é o mesmo do cadastro, então as regras de campo são as
     * mesmas; o que só a alteração tem — a conta não mudar de tamanho nem de lado
     * depois de haver dinheiro dentro — é lido pelo serviço, com a linha na mão.
     */
    public function update(Request $request, FinancialRecord $registro): RedirectResponse
    {
        $validado = $request->validate($this->regras($request, $registro), $this->mensagens());
        $antes = $registro->description;

        $this->conta->alterar($registro, $validado);

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', sprintf('Conta "%s" atualizada.', $antes));
    }

    /**
     * O verbo é `LancamentoDeConta::cancelar()`; aqui só está o motivo obrigatório,
     * que é campo de formulário, e a resposta que a ficha lê.
     */
    public function cancel(Request $request, FinancialRecord $registro): RedirectResponse
    {
        $validado = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Cancelar uma conta sem dizer por quê deixa a operação sem explicação para o número que sumiu.',
            'motivo.min' => 'Diga o motivo com pelo menos cinco caracteres.',
        ]);

        try {
            $this->conta->cancelar($registro, strval($validado['motivo']));
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', sprintf('Conta "%s" cancelada.', $registro->description));
    }

    /** Reabrir devolve a conta à derivação da soma; o passo fora da ordem volta como recusa. */
    public function reopen(FinancialRecord $registro): RedirectResponse
    {
        try {
            $estado = $this->conta->reabrir($registro);
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', sprintf(
                'Conta "%s" reaberta e de novo %s.',
                $registro->description,
                $estado === FinancialRecord::PENDING
                    ? ($registro->eReceita() ? 'a receber' : 'a pagar')
                    : mb_strtolower($registro->rotuloEstado()),
            ));
    }

    /**
     * Exclusão é o verbo do cadastro que nunca existiu; a régua de que conta com
     * dinheiro dentro não se apaga é do serviço, e o motivo volta como recusa em vez
     * de sumiço silencioso.
     */
    public function destroy(FinancialRecord $registro): RedirectResponse
    {
        $descricao = $registro->description;

        try {
            $this->conta->apagar($registro);
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return redirect()
            ->route('financial.index')
            ->with('status', sprintf('Conta "%s" excluída.', $descricao));
    }

    public function restore(int $registro): RedirectResponse
    {
        $linha = $this->conta->restaurar($registro);

        return redirect()
            ->route('financial.show', $linha)
            ->with('status', sprintf('Conta "%s" restaurada.', $linha->description));
    }

    public function export(Request $request): StreamedResponse
    {
        return Export::csv(
            'lancamentos-financeiros',
            ['Vencimento', 'Ocorrido em', 'Tipo', 'Categoria', 'Descrição', 'Cliente', 'Ordem',
                'Valor', 'Pago', 'Em aberto', 'Estado', 'Dias em atraso', 'Observações'],
            $this->consulta($request)
                ->comPagado()
                ->with(['client:id,name', 'serviceOrder:id,number'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'due_date', 'asc'))
                ->lazyById(200)
                ->map($this->linhaCsv(...)),
        );
    }

    /**
     * A cobrança da ordem: o request traz a data e a categoria, e nada além. Valor,
     * cliente e total são lidos da ordem pelo serviço, porque o número que a tela
     * mostra hoje não é o que as linhas somam depois que alguém ajusta uma peça.
     */
    public function cobrar(Request $request, ServiceOrder $ordem): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        $validado = $request->validate([
            'vencimento' => ['required', 'date'],
            'categoria' => ['required', Rule::in(array_keys(FinancialRecord::categorias(FinancialRecord::REVENUE)))],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'vencimento.required' => 'A cobrança precisa do dia em que vence: é dele que sai o "vencido" da carteira.',
            'categoria.required' => 'Escolha a categoria da receita — é por ela que o relatório soma.',
        ]);

        try {
            $lancamento = $this->conta->emitirCobranca($ordem, $validado);
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return redirect()
            ->route('financial.show', $lancamento)
            ->with('status', sprintf(
                'Cobrança da %s emitida por %s — o valor veio da soma das linhas da ordem, não do formulário.',
                $ordem->number,
                Formatters::money($lancamento->amount),
            ));
    }

    /**
     * @return array<int, mixed>
     */
    private function linhaCsv(FinancialRecord $lancamento): array
    {
        $pago = $lancamento->valorPago();

        return [
            Formatters::date($lancamento->due_date),
            Formatters::date($lancamento->occurred_at),
            StatusCatalog::label('financial_type', $lancamento->type),
            FinancialRecord::rotuloCategoria($lancamento->category),
            $lancamento->description,
            $lancamento->client?->name ?? ($lancamento->eReceita() ? 'Sem cliente' : '—'),
            $lancamento->serviceOrder?->number,
            Formatters::money($lancamento->amount),
            Formatters::money($pago),
            Formatters::money(max(0, (float) $lancamento->amount - $pago)),
            $lancamento->rotuloEstado(),
            $lancamento->diasEmAtraso(),
            $lancamento->notes,
        ];
    }

    /**
     * A consulta da listagem e da exportação. Vencimento no período, e não data de
     * cadastro: quem abre a carteira por mês quer as contas que vencem ali, pagas
     * ou não — as pagas têm o próprio número na ficha.
     */
    private function consulta(Request $request): Builder
    {
        $estado = trim((string) $request->query('estado'));

        $query = match ($estado) {
            'excluidos' => FinancialRecord::query()->withTrashed()->onlyTrashed(),
            'overdue' => FinancialRecord::query()->overdue(),
            default => FinancialRecord::query()->when(
                in_array($estado, [FinancialRecord::PENDING, FinancialRecord::PARTIAL, FinancialRecord::PAID, FinancialRecord::CANCELED], true),
                fn (Builder $q) => $q->where('status', $estado),
            ),
        };

        $query = ListFilters::igual($query, $request, 'tipo', 'type', [FinancialRecord::REVENUE, FinancialRecord::EXPENSE]);
        $query = ListFilters::igual($query, $request, 'categoria', 'category', array_keys(FinancialRecord::categorias()));
        $query = ListFilters::relacionado($query, $request, 'cliente', 'client');
        $query = ListFilters::relacionado($query, $request, 'ordem', 'serviceOrder');

        $termo = trim((string) $request->query('busca'));

        if ($termo !== '') {
            $como = ListFilters::como($termo);

            $query->where(fn (Builder $lado) => $lado
                ->where('description', 'like', $como)
                ->orWhere('notes', 'like', $como)
                ->orWhereHas('client', fn (Builder $cliente) => $cliente
                    ->where('name', 'like', $como)
                    ->orWhere('trade_name', 'like', $como))
                ->orWhereHas('serviceOrder', fn (Builder $ordem) => $ordem->where('number', 'like', $como)));
        }

        return ListFilters::periodo($query, $request, 'due_date');
    }

    /** @return array<string, array<int, mixed>> */
    private function regras(Request $request, ?FinancialRecord $registro = null): array
    {
        $empresa = TenantContext::id();
        $tipo = strval($request->input('tipo'));
        $receita = $tipo === FinancialRecord::REVENUE;

        return [
            'tipo' => ['required', Rule::in([FinancialRecord::REVENUE, FinancialRecord::EXPENSE])],
            'categoria' => ['required', 'string', 'max:64', Rule::in(array_keys(FinancialRecord::categorias($tipo)))],
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'vencimento' => ['required', 'date'],
            'cliente_id' => [
                $receita ? 'required' : 'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn (QueryBuilder $q) => $this->clienteDaEmpresa($q, $empresa)),
            ],
            'ordem_id' => [
                'nullable',
                'integer',
                Rule::exists('service_orders', 'id')->where(fn ($q) => $q->where('company_id', $empresa)),
            ],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'tipo.in' => 'Escolha receita ou despesa: é o tipo que decide o lado do caixa.',
            'categoria.in' => 'Esta categoria não existe no catálogo do tipo escolhido — categoria é vocabulário, e o relatório soma por ela.',
            'descricao.required' => 'Diga o que está sendo cobrado ou pago.',
            'valor.gt' => 'Conta de zero não entra na carteira: ajuste o valor ou não emita o lançamento.',
            'valor.decimal' => 'O valor tem duas casas decimais, como o dinheiro que existe.',
            'valor.max' => 'Este valor não cabe na coluna do financeiro.',
            'vencimento.required' => 'Sem data de vencimento a conta não sabe quando vence, e o painel não tem de onde dizer que está atrasada.',
            'cliente_id.required' => 'Receita se cobra de alguém: escolha o cliente.',
            'cliente_id.exists' => 'Este cliente não é da sua empresa.',
            'ordem_id.exists' => 'Esta ordem não é da sua empresa.',
            'observacao.max' => 'A observação tem de caber em 2000 caracteres.',
        ];
    }

    /**
     * O cliente precisa ser da empresa e estar ativo. Note o tipo do parâmetro: o
     * verificador de `exists` entrega o Query Builder cru, não o Eloquent — tipar
     * Eloquent aqui é um 500 em cada conta aberta com cliente.
     */
    private function clienteDaEmpresa(QueryBuilder $query, int $empresa): void
    {
        $query->where('company_id', $empresa)->where('status', 'active');
    }

    /** @return array<string, string> */
    private function estados(): array
    {
        return [
            FinancialRecord::PENDING => 'Em aberto',
            FinancialRecord::PARTIAL => 'Com pagamento parcial',
            FinancialRecord::PAID => 'Quitado',
            'overdue' => 'Vencido',
            FinancialRecord::CANCELED => 'Cancelado',
            'excluidos' => 'Excluídos temporariamente',
        ];
    }

    /** @return array<int, string> */
    private function clientes(): array
    {
        return Client::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * As ordens que podem estar por trás de uma conta. A tela oferece as concluídas
     * — que é quando a conta fecha — e as três últimas em execução, para o
     * escritório achar a que precisa cobrar adiantada sem a lista virar catálogo.
     *
     * @return array<int, string>
     */
    private function ordensCobraveis(): array
    {
        return ServiceOrder::query()
            ->whereIn('status', ['completed', 'in_progress', 'on_hold', 'open'])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (ServiceOrder $ordem) => [$ordem->id => $ordem->number.' — '.$ordem->title])
            ->all();
    }
}
