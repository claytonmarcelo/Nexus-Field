<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\ReageARecusa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Http\Requests\Organization\UpdateDepartmentRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Services\Organizacao\GestorDeEstrutura;
use App\Services\Recusa;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    use ReageARecusa;

    private const ORDENAVEIS = ['name', 'depth', 'created_at'];

    public function __construct(private readonly GestorDeEstrutura $gestor) {}

    public function index(Request $request): View
    {
        return view('organization.departments.index', [
            'setores' => $this->consulta($request)
                ->with(['branch:id,name', 'parent:id,name', 'manager:id,name'])
                ->withCount(['children', 'positions', 'employments'])
                ->orderBy('path')
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'filiais' => Branch::query()->orderBy('name')->pluck('name', 'id')->all(),
            'situacoes' => StatusCatalog::options('organization'),
            'filtrosAtivos' => ListFilters::ativos($request, ['busca', 'situacao', 'filial']),
        ]);
    }

    public function create(): View
    {
        return view('organization.departments.form', [
            'setor' => new Department(['type' => 'operacao', 'active' => true]),
            'filiais' => Branch::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'pais' => $this->opcoesPai(),
            'gestores' => $this->gestores(),
            'tipos' => StatusCatalog::options('department_type'),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        try {
            $setor = $this->gestor->salvarSetor($request->validated());
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.departments.show', $setor)
            ->with('status', "Setor “{$setor->name}” cadastrado.");
    }

    public function show(Department $setor): View
    {
        $setor->load(['branch', 'parent', 'manager']);

        return view('organization.departments.show', [
            'setor' => $setor,
            'subarvore' => Department::query()->naArvore($setor)->orderBy('path')->get(),
            'cargos' => $setor->positions()->orderBy('level')->get(),
            'vinculos' => $setor->employments()->with('user:id,name')->orderBy('employee_code')->get(),
        ]);
    }

    public function edit(Department $setor): View
    {
        return view('organization.departments.form', [
            'setor' => $setor,
            'filiais' => Branch::query()->orderBy('name')->pluck('name', 'id')->all(),
            'pais' => $this->opcoesPai($setor),
            'gestores' => $this->gestores(),
            'tipos' => StatusCatalog::options('department_type'),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $setor): RedirectResponse
    {
        try {
            $this->gestor->salvarSetor($request->validated(), $setor);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.departments.show', $setor)
            ->with('status', "Setor “{$setor->name}” atualizado.");
    }

    public function destroy(Department $setor): RedirectResponse
    {
        try {
            $this->gestor->excluirSetor($setor);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.departments.index')
            ->with('status', "Setor “{$setor->name}” excluído.");
    }

    private function consulta(Request $request): Builder
    {
        $query = Department::query();
        $situacao = trim((string) $request->query('situacao'));

        if (in_array($situacao, array_keys(StatusCatalog::options('organization')), true)) {
            $query->where('active', $situacao === 'active');
        }

        $query = ListFilters::igual($query, $request, 'filial', 'branch_id');
        $query = ListFilters::busca($query, $request, ['name', 'slug', 'description']);

        return $query->orderBy(...ListFilters::ordenacaoAtual(
            $request,
            self::ORDENAVEIS,
            'name',
        ));
    }

    /** @return array<int, string> */
    private function opcoesPai(?Department $atual = null): array
    {
        $prefixo = $atual === null ? null : ((string) $atual->path ?: ('/'.$atual->getKey().'/'));

        return Department::query()
            ->active()
            ->when($atual !== null, fn (Builder $query) => $query
                ->whereKeyNot($atual->getKey())
                ->where('path', 'not like', $prefixo.'%'))
            ->orderBy('path')
            ->get()
            ->mapWithKeys(fn (Department $setor) => [
                $setor->id => str_repeat('— ', (int) $setor->depth).$setor->name,
            ])
            ->all();
    }

    /** @return array<int, string> */
    private function gestores(): array
    {
        return User::query()
            ->where('company_id', TenantContext::id())
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
