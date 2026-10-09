<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\Client;
use App\Models\FinancialRecord;
use App\Models\ServiceOrder;
use App\Support\Auditor;
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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * As contas que a empresa tem a receber e a pagar.
 *
 * O que manda nesta tela é uma coisa só: o estado do lançamento não se digita.
 * `status` é derivado da soma dos pagamentos contra o valor, calculado pelo
 * servidor dentro da transação que grava o pagamento, e é por isso que não existe
 * select de "Pago" no formulário — existe o registro do dinheiro, que puxa o
 * estado junto. Um lançamento nasce em aberto e só existe pago depois de haver
 * pagamento.
 *
 * **Categoria é vocabulário, não campo de nota.** O catálogo é fechado por tipo
 * (`FinancialRecord::CATEGORIAS`) porque a soma de fase 18 vai agrupar por ela, e
 * "Peças" com inicial maiúscula ao lado de "pecas" são duas categorias que ninguém
 * soma.
 *
 * **Cliente é da receita.** Despesa tem fornecedor, e fornecedor não está no
 * schema desta casa — um `client_id` que chegar numa despesa é descartado, não
 * aplicado. Receita, ao contrário, existe para cobrar alguém: sem cliente não há
 * a quem cobrar.
 *
 * **Ordem e cliente têm de ser o mesmo cliente.** A cobrança de uma OS da Padaria
 * aberta contra o cliente de fora é o tipo de erro que só aparece quando o
 * escritório reclama do boleto.
 *
 * O valor de uma ordem não entra pelo request: `cobrar()` recomputa a conta no
 * banco (itens, descontos e o da ordem) e recusa se a ordem já tem cobrança ativa,
 * porque cobrar duas vezes a mesma OS não é duplicata, é conflito.
 */
class FinancialRecordController extends Controller
{
    use EnxergaAOrdem;

    private const ORDENAVEIS = ['due_date', 'amount', 'description', 'created_at'];

    private const FILTROS = ['busca', 'tipo', 'estado', 'categoria', 'cliente', 'ordem', 'inicio', 'fim'];

    /** Ordens que podem virar cobrança: a conta fecha quando o serviço terminou. */
    private const ORDEM_COBRAVEL = 'completed';

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
        $dados = $this->prepare($validado, $request);

        $lancamento = FinancialRecord::query()->create($dados);

        Auditor::gravar(
            'lançamento financeiro',
            $lancamento,
            [],
            sprintf(
                '%s de %s — %s, vencendo em %s.',
                $lancamento->eReceita() ? 'Receita' : 'Despesa',
                Formatters::money($lancamento->amount),
                $lancamento->description,
                Formatters::date($lancamento->due_date),
            ),
        );

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
     * Altera a conta. Valor e tipo só se mexem enquanto não houve pagamento: mudar
     * o valor de uma conta meio paga é mover a régua debaixo do dinheiro que já
     * entrou, e o estorno é o caminho honesto para isso.
     */
    public function update(Request $request, FinancialRecord $registro): RedirectResponse
    {
        $validado = $request->validate($this->regras($request, $registro), $this->mensagens());

        if ($registro->temPagamento()) {
            $this->garantirContaEstavel($registro, $validado);
        }

        $mudancas = $this->prepare($validado, $request, $registro);
        $antes = $registro->description;
        $registro->update($mudancas);

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', sprintf('Conta "%s" atualizada.', $antes));
    }

    /**
     * Cancelar é dizer que a conta nunca passou a existir para o caixa. Só acontece
     * sem pagamento registrado — desfazer dinheiro que entrou é estorno, tem outro
     * verbo e outra permissão — e exige o motivo, que é o que a auditoria e quem
     * abre a ficha depois vão ler.
     */
    public function cancel(Request $request, FinancialRecord $registro): RedirectResponse
    {
        $validado = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Cancelar uma conta sem dizer por quê deixa a operação sem explicação para o número que sumiu.',
            'motivo.min' => 'Diga o motivo com pelo menos cinco caracteres.',
        ]);

        if ($registro->temPagamento()) {
            throw ValidationException::withMessages([
                'motivo' => sprintf(
                    'Esta conta tem %s de %s registrado. Estorne o pagamento antes de cancelar: '
                    .'cancelamento apaga a expectativa de caixa, não o dinheiro que mudou de mão.',
                    $registro->payments()->count() === 1 ? 'um pagamento' : $registro->payments()->count().' pagamentos',
                    Formatters::money($registro->valorPago()),
                ),
            ]);
        }

        if ($registro->status === FinancialRecord::CANCELED) {
            return back()->with('erro', 'Esta conta já está cancelada.');
        }

        $registro->update([
            'status' => FinancialRecord::CANCELED,
            'occurred_at' => null,
            'notes' => $this->anotar(strval($registro->notes), 'Cancelamento', $validado['motivo']),
        ]);

        Auditor::gravar('cancelamento de conta', $registro, [], sprintf(
            '%s cancelada: %s',
            $registro->description,
            $validado['motivo'],
        ));

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', sprintf('Conta "%s" cancelada.', $registro->description));
    }

    /** Reabrir devolve a conta à derivação: sem pagamento, ela nasce em aberto de novo. */
    public function reopen(FinancialRecord $registro): RedirectResponse
    {
        if ($registro->status !== FinancialRecord::CANCELED) {
            return back()->with('erro', 'Só se reabre uma conta que foi cancelada.');
        }

        // A reabertura retira a decisão, e só isso: `recalcularEstado()` se recusa a
        // mexer numa linha ainda marcada como cancelada, então é aqui que o
        // cancelamento deixa de existir. O estado que vale volta da soma dos
        // pagamentos, e uma conta com dinheiro registrado não renasce em aberto.
        $registro->update([
            'status' => FinancialRecord::PENDING,
            'notes' => $this->anotar(strval($registro->notes), 'Reabertura', 'Conta devolvida à carteira.'),
        ]);

        $estado = $registro->recalcularEstado();

        Auditor::gravar('reabertura de conta', $registro, [], sprintf('%s reaberta.', $registro->description));

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
     * Excluir lançamento é caso de cadastro errado, não de operação: conta com
     * pagamento tem dinheiro registrado, e apagar a ficha deixaria o pagamento sem
     * dona na hora em que o relatório somar o mês.
     */
    public function destroy(FinancialRecord $registro): RedirectResponse
    {
        if ($registro->temPagamento()) {
            return back()->with('erro', sprintf(
                'Esta conta tem %s de pagamento registrado. Para tirá-la da carteira sem perder o dinheiro, cancele-a '
                .'depois de estornar; exclusão é para cadastro que nunca existiu.',
                Formatters::money($registro->valorPago()),
            ));
        }

        $descricao = $registro->description;
        $registro->delete();

        return redirect()
            ->route('financial.index')
            ->with('status', sprintf('Conta "%s" excluída.', $descricao));
    }

    public function restore(int $registro): RedirectResponse
    {
        $linha = FinancialRecord::withTrashed()->findOrFail($registro);
        $linha->restore();

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
     * Cobra a ordem no valor que o banco calcula. O request traz a data e a
     * categoria; valor, cliente e total são lidos da ordem, porque cobrar R$ 900 de
     * uma OS que somou R$ 1.480 é a fatura errada com a assinatura do escritório.
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

        if ($ordem->status !== self::ORDEM_COBRAVEL) {
            return back()->with('erro', $ordem->estaEncerrada()
                ? sprintf('A ordem %s foi cancelada: o que não aconteceu não se cobra.', $ordem->number)
                : sprintf('A ordem %s ainda não terminou. A cobrança fecha a conta do serviço concluído.', $ordem->number));
        }

        $existente = FinancialRecord::query()
            ->where('service_order_id', $ordem->id)
            ->where('type', FinancialRecord::REVENUE)
            ->where('status', '!=', FinancialRecord::CANCELED)
            ->orderBy('due_date')
            ->first();

        if ($existente !== null) {
            return back()->with('erro', sprintf(
                'A ordem %s já tem a cobrança "%s" em carteira. Se o valor mudou, ajuste a conta existente; '
                .'cobrança duplicada não é segunda via, é conflito.',
                $ordem->number,
                $existente->description,
            ));
        }

        $ordem->load('items');
        $total = $ordem->totais()['total'];

        if ($total <= 0) {
            return back()->with('erro', sprintf(
                'A ordem %s não tem o que cobrar: a conta fecha em %s. '
                .'Registre as linhas do serviço antes de emitir a cobrança.',
                $ordem->number,
                Formatters::money($total),
            ));
        }

        $lancamento = FinancialRecord::query()->create([
            'client_id' => $ordem->client_id,
            'service_order_id' => $ordem->id,
            'type' => FinancialRecord::REVENUE,
            'category' => strval($validado['categoria']),
            'description' => 'Cobrança da '.$ordem->number.' — '.$ordem->title,
            'amount' => $total,
            'due_date' => $validado['vencimento'],
            'status' => FinancialRecord::PENDING,
            'notes' => filled($validado['observacao'] ?? null) ? $validado['observacao'] : null,
        ]);

        Auditor::gravar('cobrança de ordem', $lancamento, [], sprintf(
            'Cobrança emitida da %s no valor calculado do banco (%s).',
            $ordem->number,
            Formatters::money($total),
        ));

        return redirect()
            ->route('financial.show', $lancamento)
            ->with('status', sprintf(
                'Cobrança da %s emitida por %s — o valor veio da soma das linhas da ordem, não do formulário.',
                $ordem->number,
                Formatters::money($total),
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

    /**
     * O que o formulário manda, convertido no que a tabela grava. `status` e
     * `occurred_at` não estão aqui porque não se digita o que se deriva, e
     * `company_id` vem do contexto do tenant, não do request.
     *
     * @return array<string, mixed>
     */
    private function prepare(array $validado, Request $request, ?FinancialRecord $registro = null): array
    {
        $tipo = strval($validado['tipo']);
        $receita = $tipo === FinancialRecord::REVENUE;

        $dados = [
            'type' => $tipo,
            'category' => strval($validado['categoria']),
            'description' => strval($validado['descricao']),
            'amount' => (float) $validado['valor'],
            'due_date' => $validado['vencimento'],
            'client_id' => $receita ? (int) $validado['cliente_id'] : null,
            'service_order_id' => filled($validado['ordem_id'] ?? null) ? (int) $validado['ordem_id'] : null,
            'notes' => filled($validado['observacao'] ?? null) ? strval($validado['observacao']) : null,
        ];

        if ($registro === null) {
            $dados['status'] = FinancialRecord::PENDING;
            $dados['occurred_at'] = null;
        }

        $this->garantirMesmaCarteira($dados);

        return $dados;
    }

    /**
     * Ordem e cliente apontam para a mesma carteira. A validação de campo confere
     * cada um isoladamente e não enxerga o par: é aqui que a cobrança da OS do
     * Restaurante sai contra a Padaria, que é o erro que só aparece quando o
     * cliente liga reclamando do boleto.
     *
     * @param  array<string, mixed>  $dados
     */
    private function garantirMesmaCarteira(array $dados): void
    {
        if ($dados['service_order_id'] === null || $dados['client_id'] === null) {
            return;
        }

        $ordem = ServiceOrder::query()->whereKey($dados['service_order_id'])->first();

        if ($ordem !== null && (int) $ordem->client_id === (int) $dados['client_id']) {
            return;
        }

        throw ValidationException::withMessages([
            'ordem_id' => sprintf(
                'A ordem %s pertence a %s, e esta conta está aberta contra %s: escolha o cliente da ordem '
                .'ou a ordem do cliente.',
                $ordem?->number ?? 'inexistente',
                $ordem?->client?->name ?? 'carteira nenhuma',
                Client::query()->whereKey($dados['client_id'])->value('name') ?? 'carteira nenhuma',
            ),
        ]);
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

    /**
     * Receita e ordem têm de apontar para o mesmo cliente. A validação de campo não
     * enxerga o par — é aqui que a combinação é conferida, porque a mensagem precisa
     * dizer qual dos dois está errado.
     */
    private function garantirContaEstavel(FinancialRecord $registro, array $validado): void
    {
        $mudouValor = abs((float) $validado['valor'] - (float) $registro->amount) >= 0.005;
        $mudouTipo = strval($validado['tipo']) !== $registro->type;

        if (! $mudouValor && ! $mudouTipo) {
            return;
        }

        throw ValidationException::withMessages([
            $mudouValor ? 'valor' : 'tipo' => sprintf(
                'Esta conta já tem %s registrado. Mexer no %s depois disso é mover a régua debaixo do dinheiro: '
                .'estorne o pagamento e ajuste, ou cancele e reabra.',
                Formatters::money($registro->valorPago()),
                $mudouValor ? 'valor' : 'o tipo',
            ),
        ]);
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

    private function anotar(?string $atual, string $titulo, string $texto): string
    {
        $linha = '['.Formatters::date(now()).'] '.$titulo.': '.$texto;

        return trim((string) $atual) === '' ? $linha : $atual."\n".$linha;
    }
}
