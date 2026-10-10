<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\User;
use App\Services\Stock\LancamentoDeEstoque;
use App\Support\Export;
use App\Support\Formatters;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * O estoque da empresa visto pelo que aconteceu com ele.
 *
 * Não existe tela para digitar a quantidade de um produto, e isso é decisão, não
 * falta. O saldo central é a soma das movimentações com o sinal de cada tipo
 * (`StockMovement::CENTRAL_SIGN`), calculado no MySQL — a mesma subquery da
 * listagem de produtos, da ficha e do painel. Um campo de quantidade viveria ao
 * lado desse número e alguém acabaria acreditando nele.
 *
 * Esta classe é a fachada: resolve o pedido, apresenta a resposta e responde pelo
 * recorte de leitura. A escrita em si — os dois saldos, a trava que faz a conta
 * valer, o carimbo da auditoria e o sino de reposição — mora em
 * `App\Services\Stock\LancamentoDeEstoque`, e as regras que ela impõe estão
 * documentadas lá.
 *
 * Quem resolve aqui é "de quem é o fato", não "o que o fato vale": a tela responde
 * por qual produto, de qual técnico e em qual ordem, sempre pela permissão de quem
 * está logado. O recorte de leitura é responder pelo inventário (`stock.adjust`):
 * quem pode ajustar lê e move a empresa inteira; quem só movimenta — o técnico com
 * a própria ficha — fala da própria carga e lê as linhas em que o nome dele
 * aparece.
 */
class MovementController extends Controller
{
    use EnxergaAOrdem;

    /** Ordens que aceitam consumo: rascunho ainda não saiu da mesa e cancelada não aconteceu. */
    private const ORDENS_ACEITAM_CONSUMO = ['open', 'in_progress', 'on_hold', 'completed'];

    private const QUANTIDADE_MAXIMA = 9999999999;

    private const ORDENAVEIS = ['recorded_at', 'type', 'quantity', 'created_at'];

    private const FILTROS = ['busca', 'tipo', 'produto', 'tecnico', 'ordem', 'inicio', 'fim'];

    public function __construct(private readonly LancamentoDeEstoque $estoque) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $restrito = StockMovement::alcanceRestrito($usuario);

        return view('movements.index', [
            'movimentacoes' => $this->consulta($request, $usuario)
                ->with(['product:id,name,sku,unit', 'technician:id,name', 'serviceOrder:id,number,title', 'user:id,name'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'recorded_at', 'desc'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'tipos' => StatusCatalog::options('movement'),
            'produtos' => $restrito ? [] : $this->produtos(),
            'tecnicos' => $restrito ? [] : $this->tecnicos(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'restrito' => $restrito,
        ]);
    }

    public function create(Request $request): View
    {
        $usuario = $request->user();
        $restrito = StockMovement::alcanceRestrito($usuario);
        $tipos = $this->tiposPermitidos($usuario);

        return view('movements.create', [
            'movimento' => new StockMovement(['type' => array_key_first($tipos)]),
            'tipos' => $tipos,
            'produtos' => $this->produtosComSaldo(),
            'tecnicos' => $restrito ? [] : $this->tecnicos(),
            'ordens' => $this->ordens($usuario, $restrito),
            'sinais' => $this->sinais($tipos),
            'restrito' => $restrito,
            'usuario' => $usuario,
        ]);
    }

    /**
     * Registra um fato pedindo ao serviço, e devolve para a tela a frase que o
     * serviço formou mais o aviso de reposição — quando o saldo cruzou o ponto
     * mínimo, o mesmo texto volta como flash de alerta e já tocou o sino de quem
     * repõe.
     */
    public function store(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $restrito = StockMovement::alcanceRestrito($usuario);

        // A lista de tipos que o servidor aceita é a mesma que o select oferece, e
        // sai da permissão de quem está logado: um tipo que esta conta não registra
        // morre na validação do campo, com a mensagem de quem pode registrar outro.
        $validado = $request->validate($this->regras($request, $usuario, $restrito), $this->mensagens());

        $tipo = strval($validado['tipo']);
        $tecnico = $this->quemMoveu($tipo, $validado, $usuario, $restrito);
        $ordem = $tipo === 'consume'
            ? $this->ordemDoConsumo((int) $validado['ordem_id'], $usuario, $tecnico)
            : null;

        $lancamento = $this->estoque->registrar([
            'produto_id' => (int) $validado['produto_id'],
            'tipo' => $tipo,
            'quantidade' => (float) $validado['quantidade'],
            'tecnico' => $tecnico,
            'ordem' => $ordem,
            'usuario' => $usuario,
            'custo' => $validado['custo_unitario'] ?? null,
            'observacao' => $validado['observacao'] ?? null,
        ]);

        $redirect = redirect()
            ->route('movements.index', ['produto' => $lancamento['produto']->id])
            ->with('status', $lancamento['resumo']);

        return $lancamento['aviso'] === null
            ? $redirect
            : $redirect->with('aviso', $lancamento['aviso']);
    }

    public function export(Request $request): StreamedResponse
    {
        $usuario = $request->user();

        return Export::csv(
            'movimentacoes-de-estoque',
            ['Quando', 'Tipo', 'Produto', 'SKU', 'Unidade', 'Quantidade', 'Efeito no central',
                'Técnico', 'Carga do técnico', 'Ordem', 'Custo unitário', 'Valor', 'Registrado por', 'Observação'],
            $this->consulta($request, $usuario)
                ->with(['product:id,name,sku,unit', 'technician:id,name', 'serviceOrder:id,number', 'user:id,name'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'recorded_at', 'desc'))
                ->lazyById(200)
                ->map($this->linhaCsv(...)),
        );
    }

    /** @return array<int, mixed> */
    private function linhaCsv(StockMovement $movimento): array
    {
        $produto = $movimento->product;
        $central = $movimento->efeitoCentral();
        $carga = $movimento->efeitoTecnico();

        return [
            $movimento->recorded_at,
            StatusCatalog::label('movement', $movimento->type),
            $produto?->name,
            $produto?->sku,
            Formatters::unidade($produto?->unit),
            Formatters::decimal($movimento->quantity),
            match (true) {
                $central > 0 => '+'.Formatters::decimal($central),
                $central < 0 => '−'.Formatters::decimal(abs($central)),
                default => 'fora do central',
            },
            $movimento->technician?->name ?? 'estoque central',
            $carga === null ? '—' : ($carga > 0 ? '+' : '−').Formatters::decimal(abs($carga)),
            $movimento->serviceOrder?->number,
            $movimento->unit_cost === null ? null : Formatters::money($movimento->unit_cost),
            $movimento->unit_cost === null
                ? null
                : Formatters::money((float) $movimento->unit_cost * abs((float) $movimento->quantity)),
            $movimento->user?->name ?? 'sistema',
            $movimento->note,
        ];
    }

    /**
     * A consulta da listagem e da exportação. O alcance entra antes dos filtros: um
     * técnico que digitar `?tecnico=99` na URL não chega à carga de um colega.
     */
    private function consulta(Request $request, User $usuario): Builder
    {
        $query = StockMovement::query()->visiveisPara($usuario);

        $query = ListFilters::igual($query, $request, 'tipo', 'type', array_keys(StatusCatalog::options('movement')));
        $query = ListFilters::relacionado($query, $request, 'produto', 'product');
        $query = ListFilters::relacionado($query, $request, 'tecnico', 'technician');
        $query = ListFilters::relacionado($query, $request, 'ordem', 'serviceOrder');

        $termo = trim((string) $request->query('busca'));

        if ($termo !== '') {
            $como = ListFilters::como($termo);

            $query->where(fn (Builder $lado) => $lado
                ->where('note', 'like', $como)
                ->orWhereHas('product', fn (Builder $produto) => $produto
                    ->where('name', 'like', $como)
                    ->orWhere('sku', 'like', $como))
                ->orWhereHas('technician', fn (Builder $tecnico) => $tecnico->where('name', 'like', $como))
                ->orWhereHas('serviceOrder', fn (Builder $ordem) => $ordem->where('number', 'like', $como)));
        }

        return ListFilters::periodo($query, $request, 'recorded_at');
    }

    /**
     * De quem é a movimentação. A conta de técnico responde pela própria ficha — o
     * formulário restrito nem oferece o campo — e o escritório que responde pelo
     * inventário escolhe de quem é a carga. Compra e ajuste são da empresa: não têm
     * técnico, e um `technician_id` que chegar com eles é descartado, não aplicado.
     */
    private function quemMoveu(string $tipo, array $validado, User $usuario, bool $restrito): ?Technician
    {
        if (! in_array($tipo, StockMovement::COM_TECNICO, true)) {
            return null;
        }

        if ($restrito) {
            return $usuario->technician;
        }

        $tecnico = Technician::query()
            ->where('company_id', TenantContext::id())
            ->whereKey((int) ($validado['tecnico_id'] ?? 0))
            ->first();

        if ($tecnico === null) {
            throw ValidationException::withMessages([
                'tecnico_id' => 'Escolha um técnico da sua empresa: sem dono, a carga não existe.',
            ]);
        }

        return $tecnico;
    }

    /**
     * A ordem em que a unidade foi gasta. Além do alcance de sempre, o consumo pede
     * que o técnico tenha relação com ela — responsável ou comissionado —, porque
     * gastar na ordem do colega é a assinatura de um lançamento feito no lugar
     * errado.
     */
    private function ordemDoConsumo(int $ordemId, User $usuario, ?Technician $tecnico): ServiceOrder
    {
        $ordem = ServiceOrder::query()
            ->where('company_id', TenantContext::id())
            ->whereKey($ordemId)
            ->first();

        if ($ordem === null) {
            throw ValidationException::withMessages([
                'ordem_id' => 'Escolha uma ordem de serviço da sua empresa.',
            ]);
        }

        $this->garantirVisivel($ordem, $usuario);

        if (! in_array($ordem->status, self::ORDENS_ACEITAM_CONSUMO, true)) {
            throw ValidationException::withMessages([
                'ordem_id' => $ordem->status === 'draft'
                    ? sprintf('A ordem %s ainda é rascunho: nada foi gasto num trabalho que não começou.', $ordem->number)
                    : sprintf('A ordem %s foi cancelada: o que não aconteceu não consome estoque.', $ordem->number),
            ]);
        }

        $envolvidos = collect([$ordem->technician_id])
            ->merge($ordem->assignments()->pluck('technician_id'))
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($tecnico !== null && ! $envolvidos->contains((int) $tecnico->id)) {
            throw ValidationException::withMessages([
                'ordem_id' => sprintf(
                    '%s não é o técnico responsável nem está no quadro da ordem %s: registre o consumo na ordem em que a unidade foi gasta.',
                    $tecnico->name,
                    $ordem->number,
                ),
            ]);
        }

        return $ordem;
    }

    /**
     * Os tipos que esta conta pode registrar. Ajuste é de quem responde pelo
     * inventário, e a conta restrita fala da própria carga — a lista que o select
     * oferece é a mesma que o servidor aceita, e não o contrário.
     *
     * @return array<string, string>
     */
    private function tiposPermitidos(User $usuario): array
    {
        $todos = StatusCatalog::options('movement');

        if (StockMovement::alcanceRestrito($usuario)) {
            return array_intersect_key($todos, array_flip(StockMovement::COM_TECNICO));
        }

        if (! $usuario->hasPermission('stock.adjust')) {
            return array_diff_key($todos, ['adjustment' => null]);
        }

        return $todos;
    }

    /** O que cada tipo oferecido faz com os dois saldos, lido das constantes do model. */
    private function sinais(array $tipos): array
    {
        return collect($tipos)
            ->map(fn (string $rotulo, string $tipo) => [
                'rotulo' => $rotulo,
                'central' => StockMovement::CENTRAL_SIGN[$tipo] ?? 0,
                'tecnico' => StockMovement::TECHNICIAN_SIGN[$tipo] ?? null,
            ])
            ->all();
    }

    /** @return array<string, array<int, string>> */
    private function regras(Request $request, User $usuario, bool $restrito): array
    {
        $empresa = TenantContext::id();
        $tipo = strval($request->input('tipo'));
        $precisaDeTecnico = in_array($tipo, StockMovement::COM_TECNICO, true);

        return [
            'tipo' => ['required', Rule::in(array_keys($this->tiposPermitidos($usuario)))],
            'produto_id' => ['required', 'integer', Rule::exists('products', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->where('status', 'active'))],
            'quantidade' => [
                'required', 'numeric', 'decimal:0,4', 'max:'.self::QUANTIDADE_MAXIMA,
                $tipo === 'adjustment' ? 'regex:/[1-9]/' : 'gt:0',
            ],
            'tecnico_id' => [
                $precisaDeTecnico && ! $restrito ? 'required' : 'nullable',
                'integer',
                Rule::exists('technicians', 'id')->where(
                    fn ($query) => $query->where('company_id', $empresa)->where('status', '<>', 'inactive')
                ),
            ],
            'ordem_id' => [
                $tipo === 'consume' ? 'required' : 'nullable',
                'integer',
                Rule::exists('service_orders', 'id')->where(fn ($query) => $query->where('company_id', $empresa)),
            ],
            'custo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'tipo.in' => 'Escolha um tipo de movimentação que você pode registrar.',
            'produto_id.exists' => 'Este produto não está no catálogo ativo da sua empresa — inativar tira da escolha, e a movimentação nova segue a escolha.',
            'quantidade.gt' => 'Compra, carga, consumo e devolução contam unidades para frente: a quantidade tem de ser maior que zero.',
            'quantidade.regex' => 'O ajuste move alguma coisa: para não mexer no saldo, não registre o ajuste.',
            'quantidade.decimal' => 'A quantidade não passa de quatro casas decimais.',
            'quantidade.max' => 'Esta quantidade não cabe na coluna do estoque.',
            'tecnico_id.required' => 'Carga, consumo e devolução acontecem na mala de alguém: escolha o técnico.',
            'tecnico_id.exists' => 'O técnico escolhido não é da sua empresa ou está inativo — e inativo não carrega estoque.',
            'ordem_id.required' => 'O consumo precisa da ordem em que a unidade foi gasta: é ela que explica para onde o item saiu.',
            'ordem_id.exists' => 'Esta ordem não é da sua empresa.',
            'observacao.max' => 'A observação tem de caber em 500 caracteres.',
        ];
    }

    /** @return array<int, string> */
    private function produtos(): array
    {
        return Product::query()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * O catálogo com o saldo central à vista. Escolher produto para baixar sem saber
     * quanto tem é escolher no escuro — e a escolha errada vira recusa na volta, com
     * o número que faltava.
     *
     * @return array<int, string>
     */
    private function produtosComSaldo(): array
    {
        return Product::query()->active()->withCentralBalance()->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Product $produto) => [$produto->id => sprintf(
                '%s (%s) · %s %s no central',
                $produto->name,
                $produto->sku,
                Formatters::decimal($produto->central_balance),
                Formatters::unidade($produto->unit),
            )])
            ->all();
    }

    /** @return array<int, string> */
    private function tecnicos(): array
    {
        return Technician::query()->where('status', '<>', 'inactive')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * As ordens que aceitam consumo. Para a conta de técnico, só as dele: a lista que
     * a tela oferece é a mesma que o servidor aceita.
     *
     * @return array<int, string>
     */
    private function ordens(User $usuario, bool $restrito): array
    {
        $query = ServiceOrder::query()->whereIn('status', self::ORDENS_ACEITAM_CONSUMO);

        if ($restrito && $usuario->technician !== null) {
            $id = $usuario->technician->id;

            $query->where(fn (Builder $lado) => $lado
                ->where('technician_id', $id)
                ->orWhereHas('assignments', fn (Builder $quadro) => $quadro->where('technician_id', $id)));
        }

        return $query->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->mapWithKeys(fn (ServiceOrder $ordem) => [$ordem->id => $ordem->number.' — '.$ordem->title])
            ->all();
    }
}
