<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Concerns\EmEdicao;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ListFilters;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Agrupamento do catálogo de serviços. É uma tela pequena porque a categoria não
 * tem vida própria: ela existe para a listagem de serviços poder ser filtrada e
 * para o operador reconhecer o serviço pelo grupo. O `slug` nasce do nome e é
 * único por empresa, que é como o schema garante a duplicidade.
 */
class ServiceCategoryController extends Controller
{
    use EmEdicao;

    public function index(Request $request): View
    {
        $categorias = ListFilters::busca(ServiceCategory::query(), $request, ['name', 'slug'])
            ->withCount('services')
            ->orderBy('name')
            ->paginate(ListFilters::porPagina($request))
            ->withQueryString();

        return view('categories.index', [
            'categorias' => $categorias,
            'totalServicos' => Service::query()->count(),
            'emEdicao' => $this->emEdicao($request, 'editar_categoria', $categorias->getCollection()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $categoria = ServiceCategory::create($this->validar($request));

        return redirect()
            ->route('categories.index')
            ->with('status', "Categoria “{$categoria->name}” criada.");
    }

    public function update(Request $request, ServiceCategory $categoria): RedirectResponse
    {
        $categoria->update($this->validar($request, $categoria));

        return redirect()
            ->route('categories.index')
            ->with('status', "Categoria “{$categoria->name}” atualizada.");
    }

    /**
     * Apagar categoria em uso deixaria os serviços sem grupo — o banco anula a
     * chave, mas o operador perde a informação que cadastrou. A tela manda primeiro
     * mover ou excluir os serviços do grupo.
     */
    public function destroy(ServiceCategory $categoria): RedirectResponse
    {
        $emUso = $categoria->services()->count();

        if ($emUso > 0) {
            return redirect()
                ->route('categories.index', ['busca' => $categoria->name])
                ->with('erro', sprintf(
                    '%s tem %s no grupo. Troque a categoria desses serviços antes de excluí-la.',
                    $categoria->name,
                    $emUso === 1 ? 'um serviço' : $emUso.' serviços',
                ));
        }

        $nome = $categoria->name;
        $categoria->delete();

        return redirect()
            ->route('categories.index')
            ->with('status', "Categoria “{$nome}” excluída.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?ServiceCategory $categoria = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [Rule::unique('service_categories', 'slug')
                ->where(fn ($query) => $query->where('company_id', TenantContext::id()))
                ->ignore($categoria?->id)],
        ], [
            'slug.unique' => 'Esta empresa já tem uma categoria com este nome.',
        ]);
    }
}
