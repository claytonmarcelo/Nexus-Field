<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\TechnicianStock;
use App\Models\User;
use App\Support\Auditor;
use App\Support\Export;
use App\Support\Formatters;
use App\Support\ListFilters;
use App\Support\Notifier;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 * As regras que mandam aqui são três, e nenhuma mora no formulário.
 *
 * **A data é de cá.** `recorded_at` é o relógio do servidor; um pedido com data
 * escolhida no navegador reescreveria a ordem dos fatos e o saldo de um dia que já
 * passou. Quem precisa explicar um fato de ontem diz isso na observação.
 *
 * **Nenhum saldo fica negativo.** Carga não pode passar do que há no central, e
 * consumo e devolução não podem passar do que o técnico carrega. A conta é feita
 * dentro de uma transação com o produto e a linha de carga travados por
 * `lockForUpdate`, porque dois registros que cada um confere "tem bastante" na
 * própria leitura é exatamente como um estoque chega a −3 unidades.
 *
 * **Movimentação não se edita nem se apaga.** É livro-caixa: o que estava errado se
 * responde com outra linha que diz o que corrigiu, e a auditoria mostra quem fez as
 * duas. Riscar a linha riscaria a explicação do saldo que ela formou.
 *
 * O recorte de leitura é responder pelo inventário (`stock.adjust`): quem pode
 * ajustar lê e move a empresa inteira; quem só movimenta — o técnico com a própria
 * ficha — fala da própria carga e lê as linhas em que o nome dele aparece.
 */
class MovementController extends Controller
{
    use EnxergaAOrdem;

    /** Ordens que aceitam consumo: rascunho ainda não saiu da mesa e cancelada não aconteceu. */
    private const ORDENS_ACEITAM_CONSUMO = ['open', 'in_progress', 'on_hold', 'completed'];

    private const QUANTIDADE_MAXIMA = 9999999999;

    private const ORDENAVEIS = ['recorded_at', 'type', 'quantity', 'created_at'];

    private const FILTROS = ['busca', 'tipo', 'produto', 'tecnico', 'ordem', 'inicio', 'fim'];

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
     * Registra um fato. A ordem dentro da transação é o que faz a trava valer:
     * primeiro o produto, depois a linha de carga, e só então os saldos são lidos do
     * que o banco devolve — antes de a linha existir.
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
        $quantidade = (float) $validado['quantidade'];
        $tecnico = $this->quemMoveu($tipo, $validado, $usuario, $restrito);
        $ordem = $tipo === 'consume'
            ? $this->ordemDoConsumo((int) $validado['ordem_id'], $usuario, $tecnico)
            : null;

        [$movimento, $produto, $depoisCentral] = DB::transaction(function () use (
            $validado,
            $tipo,
            $quantidade,
            $tecnico,
            $ordem,
            $usuario
        ): array {
            return $this->gravar([
                'produto_id' => (int) $validado['produto_id'],
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'tecnico' => $tecnico,
                'ordem' => $ordem,
                'usuario' => $usuario,
                'custo' => $validado['custo_unitario'] ?? null,
                'observacao' => $validado['observacao'] ?? null,
            ]);
        });

        Auditor::gravar('movimentação de estoque', $movimento, [], $this->descrição($movimento, $produto, $depoisCentral));

        $redirect = redirect()
            ->route('movements.index', ['produto' => $produto->id])
            ->with('status', $this->mensagem($movimento, $produto, $depoisCentral));

        $aviso = $this->avisoDeReposicao($produto, $depoisCentral);

        if ($aviso !== null) {
            $this->avisarEstoqueBaixo($produto, $aviso);
        }

        return $aviso === null ? $redirect : $redirect->with('aviso', $aviso);
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

    /**
     * @param  array{produto_id: int, tipo: string, quantidade: float, tecnico: ?Technician, ordem: ?ServiceOrder, usuario: User, custo: mixed, observacao: mixed}  $par
     * @return array{0: StockMovement, 1: Product, 2: float}
     */
    private function gravar(array $par): array
    {
        $produto = Product::query()->whereKey($par['produto_id'])->lockForUpdate()->firstOrFail();
        $tipo = $par['tipo'];
        $quantidade = $par['quantidade'];

        $deltaCentral = round((StockMovement::CENTRAL_SIGN[$tipo] ?? 0) * $quantidade, 4);
        $deltaTecnico = $par['tecnico'] === null
            ? null
            : round((StockMovement::TECHNICIAN_SIGN[$tipo] ?? 0) * $quantidade, 4);

        $carga = $par['tecnico'] === null ? null : TechnicianStock::travar($par['tecnico'], $produto);

        $antes = $produto->saldoCentralAtual();

        if ($deltaCentral < 0 && $antes + $deltaCentral < 0) {
            throw ValidationException::withMessages([
                'quantidade' => sprintf(
                    'O estoque central de %s tem %s %s: %s de %s não cabe aí.',
                    $produto->name,
                    Formatters::decimal($antes),
                    $this->unidade($produto),
                    StatusCatalog::label('movement', $tipo),
                    Formatters::decimal(abs($deltaCentral)),
                ),
            ]);
        }

        if ($deltaTecnico !== null && $deltaTecnico < 0 && (float) $carga->quantity + $deltaTecnico < 0) {
            throw ValidationException::withMessages([
                'tecnico_id' => sprintf(
                    '%s carrega %s %s de %s: %s de %s passa do que há na mala.',
                    $par['tecnico']->name,
                    Formatters::decimal($carga->quantity),
                    $this->unidade($produto),
                    $produto->name,
                    StatusCatalog::label('movement', $tipo),
                    Formatters::decimal(abs($deltaTecnico)),
                ),
            ]);
        }

        $movimento = StockMovement::query()->create([
            'product_id' => $produto->id,
            'technician_id' => $par['tecnico']?->id,
            'service_order_id' => $par['ordem']?->id,
            'user_id' => $par['usuario']->id,
            'type' => $tipo,
            'quantity' => $quantidade,
            'unit_cost' => $par['custo'],
            'note' => filled($par['observacao']) ? $par['observacao'] : null,
            'recorded_at' => now(),
        ]);

        if ($deltaTecnico !== null) {
            $carga->aplicar($deltaTecnico);
        }

        return [$movimento, $produto, $produto->saldoCentralAtual()];
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
            $produto === null ? null : $this->unidade($produto),
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

    private function descrição(StockMovement $movimento, Product $produto, float $depois): string
    {
        $unidade = $this->unidade($produto);
        $carga = $movimento->efeitoTecnico();

        $texto = sprintf(
            '%s: %s %s de %s (%s). Estoque central agora: %s %s.',
            StatusCatalog::label('movement', $movimento->type),
            Formatters::decimal(abs((float) $movimento->quantity)),
            $unidade,
            $produto->name,
            $produto->sku,
            Formatters::decimal($depois),
            $unidade,
        );

        if ($carga === null) {
            return $texto;
        }

        return $texto.sprintf(
            ' %s %s %s na carga de %s.',
            $carga > 0 ? 'Entraram' : 'Saíram',
            Formatters::decimal(abs($carga)),
            $unidade,
            $movimento->technician?->name ?? 'técnico',
        );
    }

    private function mensagem(StockMovement $movimento, Product $produto, float $depois): string
    {
        $unidade = $this->unidade($produto);
        $central = $movimento->efeitoCentral();
        $carga = $movimento->efeitoTecnico();

        $texto = sprintf(
            '%s de %s %s de %s (%s). Estoque central: %s%s %s.',
            StatusCatalog::label('movement', $movimento->type),
            Formatters::decimal(abs((float) $movimento->quantity)),
            $unidade,
            $produto->name,
            $produto->sku,
            $central > 0 ? '+' : ($central < 0 ? '−' : ''),
            Formatters::decimal($central === 0 ? 0 : abs($central)),
            $unidade,
        );

        if ($carga === null) {
            return $texto;
        }

        return rtrim($texto, '.').sprintf(
            ' %s %s %s na carga de %s.',
            $carga > 0 ? 'Entraram' : 'Saíram',
            Formatters::decimal(abs($carga)),
            $unidade,
            $movimento->technician?->name ?? 'técnico',
        );
    }

    /**
     * O saldo cruzou a linha e a operação precisa saber disso no momento em que
     * cruzou, não na semana em que alguém abrir o painel.
     */
    private function avisoDeReposicao(Product $produto, float $depois): ?string
    {
        if ($produto->reorder_point === null || $depois >= (float) $produto->reorder_point) {
            return null;
        }

        return sprintf(
            '%s está abaixo do ponto de reposição: %s contra o mínimo de %s %s.',
            $produto->name,
            Formatters::decimal($depois),
            Formatters::decimal($produto->reorder_point),
            $this->unidade($produto),
        );
    }

    /**
     * A falta no central é o único flash de tela que também toca sino: quem vê
     * o aviso verde é quem digitou a baixa; quem repõe precisa ser chamado à
     * parte. O toque é um por conta sem leitura — a mesma peça voltando a
     * faltar não martela quem ainda não leu a falta anterior, e volta a soar
     * para quem já leu.
     */
    private function avisarEstoqueBaixo(Product $produto, string $aviso): void
    {
        $link = route('movements.index', ['produto' => $produto->id]);

        foreach (Notifier::quemPode('stock.adjust') as $conta) {
            if (Notifier::jaAvisaram((int) $conta->id, 'estoque.baixo', $link)) {
                continue;
            }

            Notifier::para(
                $conta,
                'estoque.baixo',
                sprintf('%s abaixo do ponto de reposição', $produto->name),
                $aviso,
                $link,
                ['produto_id' => $produto->id],
            );
        }
    }

    /** O código da unidade, que é como a operação fala no corredor: "un", "kg", "m". */
    private function unidade(Product $produto): string
    {
        return strval($produto->unit ?? 'un');
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
                $produto->unit,
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
