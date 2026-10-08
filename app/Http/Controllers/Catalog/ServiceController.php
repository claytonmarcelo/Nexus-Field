<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de serviços da empresa: o que ela vende e por quanto. A listagem, o
 * filtro e a página saem do MySQL desta empresa; a ficha mostra onde o serviço
 * já foi usado, contado nas ordens e nos itens de ordem, e é isso que segura a
 * exclusão de um serviço que já cobrou algo.
 */
class ServiceController extends Controller
{
    private const ORDENAVEIS = ['name', 'code', 'price', 'estimated_minutes', 'status', 'created_at'];

    private const FILTROS = ['busca', 'situacao', 'categoria'];

    public function index(Request $request): View
    {
        return view('services.index', [
            'servicos' => $this->consulta($request)
                ->with('category')
                ->withCount(['serviceOrders', 'items'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'categorias' => $this->categorias(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'situacoes' => StatusCatalog::options('catalogo') + ['excluidos' => 'Excluídos temporariamente'],
        ]);
    }

    public function create(): View
    {
        return view('services.form', [
            'servico' => new Service(['status' => 'active']),
            'categorias' => $this->categorias(),
            'situacoes' => StatusCatalog::options('catalogo'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $servico = Service::create($request->validate($this->regras(), $this->mensagens()));

        return redirect()
            ->route('services.show', $servico)
            ->with('status', "Serviço “{$servico->name}” cadastrado.");
    }

    public function show(Service $servico): View
    {
        return view('services.show', [
            'servico' => $servico->load('category'),
            'historico' => $this->historico($servico),
            'ultimasOrdens' => $servico->serviceOrders()
                ->with('client')
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }

    public function edit(Service $servico): View
    {
        return view('services.form', [
            'servico' => $servico,
            'categorias' => $this->categorias(),
            'situacoes' => StatusCatalog::options('catalogo'),
        ]);
    }

    public function update(Request $request, Service $servico): RedirectResponse
    {
        $servico->update($request->validate($this->regras($servico), $this->mensagens()));

        return redirect()
            ->route('services.show', $servico)
            ->with('status', "Serviço “{$servico->name}” atualizado.");
    }

    /**
     * Serviço que já apareceu em ordem ou foi cobrado em item não some: o preço
     * praticado naquele trabalho deixaria de ter explicação. Inativar tira o
     * serviço da escolha sem riscar o histórico.
     */
    public function destroy(Service $servico): RedirectResponse
    {
        $historico = $this->historico($servico);

        if (array_sum(array_column($historico, 'total')) > 0) {
            $vinculos = collect($historico)
                ->filter(fn (array $linha) => $linha['total'] > 0)
                ->map(fn (array $linha) => $linha['total'].' '.($linha['total'] === 1 ? $linha['singular'] : $linha['plural']))
                ->implode(', ');

            return back()->with('erro', sprintf(
                '%s já apareceu em histórico (%s). Para tirá-lo do catálogo sem perder nada, mude a situação para Inativo.',
                $servico->name,
                $vinculos,
            ));
        }

        $nome = $servico->name;
        $servico->delete();

        return redirect()
            ->route('services.index')
            ->with('status', "Serviço “{$nome}” excluído.");
    }

    public function restore(int $servico): RedirectResponse
    {
        $registro = Service::withTrashed()->findOrFail($servico);
        $registro->restore();

        return redirect()
            ->route('services.show', $registro)
            ->with('status', "Serviço “{$registro->name}” restaurado.");
    }

    private function consulta(Request $request): Builder
    {
        $situacao = trim((string) $request->query('situacao'));

        $query = $situacao === 'excluidos'
            ? Service::query()->withTrashed()->onlyTrashed()
            : ListFilters::igual(Service::query(), $request, 'situacao', 'status', ['active', 'inactive']);

        $query = ListFilters::busca($query, $request, ['name', 'code', 'description']);

        return ListFilters::relacionado($query, $request, 'categoria', 'category');
    }

    /** @return array<int, array{singular: string, plural: string, total: int}> */
    private function historico(Service $servico): array
    {
        return [
            [
                'singular' => 'ordem de serviço aberta com ele',
                'plural' => 'ordens de serviço abertas com ele',
                'total' => $servico->serviceOrders()->count(),
            ],
            [
                'singular' => 'item cobrado em ordem',
                'plural' => 'itens cobrados em ordem',
                'total' => $servico->items()->count(),
            ],
        ];
    }

    /** @return array<int, string> */
    private function categorias(): array
    {
        return ServiceCategory::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, mixed> */
    private function regras(?Service $servico = null): array
    {
        $empresa = TenantContext::id();

        return [
            'name' => ['required', 'string', 'max:180', Rule::unique('services', 'name')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))
                ->ignore($servico?->id)],
            'code' => ['nullable', 'string', 'max:40'],
            'service_category_id' => ['nullable', Rule::exists('service_categories', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa))],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:14400'],
            'status' => ['required', Rule::in(array_keys(StatusCatalog::options('catalogo')))],
        ];
    }

    /**
     * O nome vale apenas dentro da empresa, e a mensagem precisa dizer isso:
     * "já existe" solto faria o usuário procurar um cadastro que não é dele.
     *
     * @return array<string, string>
     */
    private function mensagens(): array
    {
        return [
            'name.unique' => 'Esta empresa já cadastrou um serviço com este nome.',
            'service_category_id.exists' => 'A categoria precisa ser uma categoria da sua empresa.',
        ];
    }
}
