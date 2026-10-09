<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Concerns\EmEdicao;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de produtos. O saldo não é coluna de `products`: vem das
 * movimentações, somado com o sinal que cada tipo tem (`StockMovement::CENTRAL_SIGN`),
 * e é por isso que a listagem pede o saldo em subquery e a ficha mostra de onde
 * cada número veio.
 */
class ProductController extends Controller
{
    use EmEdicao;

    private const ORDENAVEIS = ['name', 'sku', 'cost', 'price', 'reorder_point', 'status', 'created_at'];

    private const FILTROS = ['busca', 'situacao', 'estoque', 'unidade'];

    public function index(Request $request): View
    {
        return view('products.index', [
            'produtos' => $this->consulta($request)
                ->withCentralBalance()
                ->withCount('movements')
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'unidades' => $this->unidadesEmUso(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'situacoes' => StatusCatalog::options('catalogo') + ['excluidos' => 'Excluídos temporariamente'],
            'estoques' => ['abaixo' => 'Abaixo do ponto de reposição', 'ok' => 'No ponto ou acima'],
        ]);
    }

    public function create(): View
    {
        return view('products.form', [
            'produto' => new Product(['status' => 'active', 'unit' => 'un']),
            'unidades' => Product::UNIDADES,
            'situacoes' => StatusCatalog::options('catalogo'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $produto = Product::create($request->validate($this->regras(), $this->mensagens()));

        return redirect()
            ->route('products.show', $produto)
            ->with('status', "Produto “{$produto->name}” cadastrado.");
    }

    public function show(Product $produto): View
    {
        // withTrashed: a rota também resolve o produto arquivado, e a ficha dele
        // precisa do saldo central pelo mesmo SQL da listagem — sem o escopo vivo,
        // a linha arquivada chegaria nula aqui.
        $carregado = $produto->newQuery()->withTrashed()
            ->whereKey($produto->id)
            ->withCentralBalance()
            ->first();

        return view('products.show', [
            'produto' => $carregado,
            'historico' => $this->historico($produto),
            'movimentacoes' => $produto->movements()
                ->with(['technician', 'serviceOrder', 'user'])
                ->latest('recorded_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function edit(Product $produto): View
    {
        return view('products.form', [
            'produto' => $produto,
            'unidades' => Product::UNIDADES,
            'situacoes' => StatusCatalog::options('catalogo'),
        ]);
    }

    public function update(Request $request, Product $produto): RedirectResponse
    {
        $produto->update($request->validate($this->regras($produto), $this->mensagens()));

        return redirect()
            ->route('products.show', $produto)
            ->with('status', "Produto “{$produto->name}” atualizado.");
    }

    /**
     * Produto com movimentação registrada é a fonte do saldo: excluir a ficha
     * deixaria o estoque central sem explicação para o número que ele mostra.
     * Enquanto houver movimento ou item cobrado, o caminho é inativar.
     */
    public function destroy(Product $produto): RedirectResponse
    {
        $historico = $this->historico($produto);

        if (array_sum(array_column($historico, 'total')) > 0) {
            $vinculos = collect($historico)
                ->filter(fn (array $linha) => $linha['total'] > 0)
                ->map(fn (array $linha) => $linha['total'].' '.($linha['total'] === 1 ? $linha['singular'] : $linha['plural']))
                ->implode(', ');

            return back()->with('erro', sprintf(
                '%s tem histórico no estoque (%s). Para tirá-lo da operação sem perder saldo, mude a situação para Inativo.',
                $produto->name,
                $vinculos,
            ));
        }

        $nome = $produto->name;
        $produto->delete();

        return redirect()
            ->route('products.index')
            ->with('status', "Produto “{$nome}” excluído.");
    }

    public function restore(int $produto): RedirectResponse
    {
        $registro = Product::withTrashed()->findOrFail($produto);
        $registro->restore();

        return redirect()
            ->route('products.show', $registro)
            ->with('status', "Produto “{$registro->name}” restaurado.");
    }

    private function consulta(Request $request): Builder
    {
        $situacao = trim((string) $request->query('situacao'));
        $estoque = trim((string) $request->query('estoque'));

        $query = $situacao === 'excluidos'
            ? Product::query()->withTrashed()->onlyTrashed()
            : ListFilters::igual(Product::query(), $request, 'situacao', 'status', ['active', 'inactive']);

        $query = match ($estoque) {
            'abaixo' => $query->belowReorderPoint(),
            'ok' => $query->atOrAboveReorderPoint(),
            default => $query,
        };

        $query = ListFilters::busca($query, $request, ['name', 'sku', 'description']);

        return ListFilters::igual($query, $request, 'unidade', 'unit', array_keys(Product::UNIDADES));
    }

    /** @return array<int, array{singular: string, plural: string, total: int}> */
    private function historico(Product $produto): array
    {
        return [
            [
                'singular' => 'movimentação de estoque',
                'plural' => 'movimentações de estoque',
                'total' => $produto->movements()->count(),
            ],
            [
                'singular' => 'item cobrado em ordem',
                'plural' => 'itens cobrados em ordem',
                'total' => $produto->items()->count(),
            ],
        ];
    }

    /** Só as unidades que este cadastro realmente usa, na ordem do catálogo. */
    private function unidadesEmUso(): array
    {
        $usadas = Product::query()
            ->whereNotNull('unit')
            ->distinct()
            ->pluck('unit')
            ->all();

        return array_intersect_key(Product::UNIDADES, array_flip($usadas));
    }

    /** @return array<string, mixed> */
    private function regras(?Product $produto = null): array
    {
        $empresa = TenantContext::id();

        return [
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('products', 'sku')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))
                ->ignore($produto?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', Rule::in(array_keys(Product::UNIDADES))],
            'cost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'reorder_point' => ['required', 'numeric', 'min:0', 'max:99999999.9999'],
            'status' => ['required', Rule::in(array_keys(StatusCatalog::options('catalogo')))],
        ];
    }

    /**
     * O SKU é único por empresa, e o schema não tem a constraint de nome — a
     * mensagem precisa dizer onde procurar o cadastro que já existe.
     *
     * @return array<string, string>
     */
    private function mensagens(): array
    {
        return [
            'sku.unique' => 'Este SKU já está cadastrado nesta empresa.',
            'unit.in' => 'Escolha uma das unidades do catálogo.',
        ];
    }
}
