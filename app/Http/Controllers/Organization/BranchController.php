<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\ReageARecusa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreBranchRequest;
use App\Http\Requests\Organization\UpdateBranchRequest;
use App\Models\Branch;
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

class BranchController extends Controller
{
    use ReageARecusa;

    private const ORDENAVEIS = ['name', 'type', 'created_at'];

    public function __construct(private readonly GestorDeEstrutura $gestor) {}

    public function index(Request $request): View
    {
        return view('organization.branches.index', [
            'filiais' => $this->consulta($request)
                ->with('manager')
                ->withCount(['departments', 'employments'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'situacoes' => StatusCatalog::options('organization'),
            'filtrosAtivos' => ListFilters::ativos($request, ['busca', 'situacao']),
        ]);
    }

    public function create(): View
    {
        return view('organization.branches.form', [
            'filial' => new Branch(['type' => 'filial', 'active' => true]),
            'tipos' => StatusCatalog::options('branch_type'),
            'gestores' => $this->gestores(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        try {
            $filial = $this->gestor->salvarFilial($request->validated());
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.branches.show', $filial)
            ->with('status', "Filial “{$filial->name}” cadastrada.");
    }

    public function show(Branch $filial): View
    {
        $filial->load([
            'manager',
            'departments' => fn ($query) => $query->orderBy('path'),
        ]);

        return view('organization.branches.show', [
            'filial' => $filial,
            'vinculos' => $filial->employments()
                ->with('user:id,name')
                ->orderBy('employee_code')
                ->get(),
        ]);
    }

    public function edit(Branch $filial): View
    {
        return view('organization.branches.form', [
            'filial' => $filial,
            'tipos' => StatusCatalog::options('branch_type'),
            'gestores' => $this->gestores(),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $filial): RedirectResponse
    {
        try {
            $this->gestor->salvarFilial($request->validated(), $filial);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.branches.show', $filial)
            ->with('status', "Filial “{$filial->name}” atualizada.");
    }

    public function destroy(Branch $filial): RedirectResponse
    {
        try {
            $this->gestor->excluirFilial($filial);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.branches.index')
            ->with('status', "Filial “{$filial->name}” excluída.");
    }

    private function consulta(Request $request): Builder
    {
        $query = Branch::query();
        $situacao = trim((string) $request->query('situacao'));

        if (in_array($situacao, array_keys(StatusCatalog::options('organization')), true)) {
            // A coluna é booleana; o filtro fala "ativo/inativo" na tela.
            $query->where('active', $situacao === 'active');
        }

        return ListFilters::busca($query, $request, ['name', 'slug']);
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
