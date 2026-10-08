<?php

namespace App\Http\Controllers\Technicians;

use App\Http\Controllers\Concerns\EmEdicao;
use App\Http\Controllers\Controller;
use App\Models\Specialty;
use App\Models\Technician;
use App\Support\ListFilters;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Especialidades que um técnico pode ter. É um cadastro pequeno, mas vive na
 * mesma tela de técnicos porque só faz sentido como competência de alguém: o
 * `slug` nasce do nome, e o nome é único dentro da empresa.
 */
class SpecialtyController extends Controller
{
    use EmEdicao;

    public function index(Request $request): View
    {
        $busca = trim((string) $request->query('busca'));

        $especialidades = Specialty::query()
            ->withCount('technicians')
            ->when($busca !== '', fn ($query) => ListFilters::busca($query, $request, ['name', 'description']))
            ->orderBy('name')
            ->paginate(ListFilters::porPagina($request))
            ->withQueryString();

        return view('specialties.index', [
            'especialidades' => $especialidades,
            'totalTecnicos' => Technician::query()->count(),
            'emEdicao' => $this->emEdicao($request, 'editar_especialidade', $especialidades->getCollection()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $especialidade = Specialty::create($this->validar($request));

        return redirect()
            ->route('specialties.index')
            ->with('status', "Especialidade “{$especialidade->name}” cadastrada.");
    }

    public function update(Request $request, Specialty $especialidade): RedirectResponse
    {
        $especialidade->update($this->validar($request, $especialidade));

        return redirect()
            ->route('specialties.index')
            ->with('status', "Especialidade “{$especialidade->name}” atualizada.");
    }

    /**
     * Uma especialidade em uso não pode sumir: as fichas que a têm ficariam com a
     * competência riscada sem ninguém ter pedido. A tela diz quantos técnicos a
     * usam e manda tirar a relação antes de excluir.
     */
    public function destroy(Specialty $especialidade): RedirectResponse
    {
        $emUso = $especialidade->technicians()->count();

        if ($emUso > 0) {
            return redirect()
                ->route('specialties.index', ['busca' => $especialidade->name])
                ->with('erro', sprintf(
                    '%s está em %s. Retire a especialidade das fichas antes de excluí-la.',
                    $especialidade->name,
                    $emUso === 1 ? 'um técnico' : $emUso.' técnicos',
                ));
        }

        $nome = $especialidade->name;
        $especialidade->delete();

        return redirect()
            ->route('specialties.index')
            ->with('status', "Especialidade “{$nome}” excluída.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Specialty $especialidade = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('specialties', 'name')
                ->where(fn ($query) => $query->where('company_id', TenantContext::id()))
                ->ignore($especialidade?->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'Esta empresa já tem uma especialidade com este nome.',
        ]);
    }
}
