<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\ReageARecusa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreEmploymentRequest;
use App\Http\Requests\Organization\UpdateEmploymentRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employment;
use App\Models\Position;
use App\Models\User;
use App\Services\Organizacao\GestorDeEstrutura;
use App\Services\Recusa;
use App\Support\Alcance;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Folha de vínculos da empresa. AQUI NÃO SE APAGA PASSAGEM: o botão de saída
 * encerra com data, e a pessoa continua aparecendo no histórico da organização.
 */
class EmploymentController extends Controller
{
    use ReageARecusa;

    private const ORDENAVEIS = ['employee_code', 'status', 'started_at', 'created_at'];

    public function __construct(private readonly GestorDeEstrutura $gestor) {}

    public function index(Request $request): View
    {
        $ids = Alcance::idsUsuarios($request->user());
        $query = Employment::query()
            ->when($ids !== null, fn ($consulta) => $consulta->whereIn('user_id', $ids));

        $query = ListFilters::igual(
            $query,
            $request,
            'situacao',
            'status',
            array_keys(StatusCatalog::options('employment')),
        );
        $query = ListFilters::igual($query, $request, 'filial', 'branch_id');
        $query = ListFilters::igual($query, $request, 'setor', 'department_id');

        $termo = trim((string) $request->query('busca'));

        if ($termo !== '') {
            $como = ListFilters::como($termo);
            $query->where(function ($consulta) use ($como): void {
                $consulta->where('employee_code', 'like', $como)
                    ->orWhereHas('user', fn ($usuarios) => $usuarios->where('name', 'like', $como));
            });
        }

        return view('organization.employments.index', [
            'vinculos' => $query
                ->with(['user:id,name', 'branch:id,name', 'department:id,name', 'position:id,name'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'employee_code'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'filiais' => Branch::query()->orderBy('name')->pluck('name', 'id')->all(),
            'setores' => Department::query()->orderBy('path')->pluck('name', 'id')->all(),
            'situacoes' => StatusCatalog::options('employment'),
            'filtrosAtivos' => ListFilters::ativos($request, ['busca', 'situacao', 'filial', 'setor']),
        ]);
    }

    public function create(): View
    {
        return view('organization.employments.form', [
            'vinculo' => new Employment(['status' => 'ativo', 'started_at' => now()->toDateString()]),
            'usuarios' => $this->usuarios(),
            'filiais' => Branch::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'setores' => $this->setores(),
            'cargos' => Position::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'gestores' => $this->contas(),
            'tipos' => StatusCatalog::options('employment_type'),
            'situacoes' => array_slice(StatusCatalog::options('employment'), 0, 2, true),
        ]);
    }

    public function store(StoreEmploymentRequest $request): RedirectResponse
    {
        try {
            $vinculo = $this->gestor->salvarVinculo($request->validated());
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.employments.show', $vinculo)
            ->with('status', "Vínculo de {$vinculo->employee_code} registrado.");
    }

    public function show(Employment $vinculo): View
    {
        $vinculo->load(['user', 'branch', 'department', 'position', 'manager']);

        return view('organization.employments.show', [
            'vinculo' => $vinculo,
            'historico' => Employment::query()
                ->where('user_id', $vinculo->user_id)
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->with(['branch:id,name', 'department:id,name', 'position:id,name'])
                ->get(),
        ]);
    }

    public function edit(Employment $vinculo): View
    {
        return view('organization.employments.form', [
            'vinculo' => $vinculo,
            'usuarios' => $this->usuarios($vinculo),
            'filiais' => Branch::query()->orderBy('name')->pluck('name', 'id')->all(),
            'setores' => $this->setores(),
            'cargos' => Position::query()->orderBy('name')->pluck('name', 'id')->all(),
            'gestores' => $this->contas(),
            'tipos' => StatusCatalog::options('employment_type'),
            'situacoes' => array_slice(StatusCatalog::options('employment'), 0, 2, true),
        ]);
    }

    public function update(UpdateEmploymentRequest $request, Employment $vinculo): RedirectResponse
    {
        try {
            $vinculo = $this->gestor->salvarVinculo($request->validated(), $vinculo);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.employments.show', $vinculo)
            ->with('status', "Vínculo de {$vinculo->employee_code} atualizado.");
    }

    /**
     * DELETE aqui é desligamento, não exclusão: a linha recebe status e data de
     * término e permanece no histórico da empresa.
     */
    public function destroy(Employment $vinculo): RedirectResponse
    {
        try {
            $this->gestor->desligarVinculo($vinculo);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.employments.index')
            ->with('status', "Passagem de {$vinculo->employee_code} encerrada. O histórico foi preservado.");
    }

    /** @return array<int, string> */
    private function usuarios(?Employment $atual = null): array
    {
        $consulta = $this->contasAtivas();

        if ($atual !== null) {
            $consulta->where(function ($sub) use ($atual): void {
                $sub->whereKey($atual->user_id)->orWhereNotExists($this->semVinculoAtivo());
            });

            return $consulta->orderBy('name')->pluck('name', 'id')->all();
        }

        return $consulta->whereNotExists($this->semVinculoAtivo())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private function contas(): array
    {
        return $this->contasAtivas()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function contasAtivas(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->where('company_id', TenantContext::id())
            ->where('status', 'active')
            ->whereNull('deleted_at');
    }

    private function semVinculoAtivo(): callable
    {
        return function ($consulta): void {
            $consulta->select(DB::raw(1))
                ->from('employments')
                ->whereColumn('employments.user_id', 'users.id')
                ->where('employments.company_id', TenantContext::id())
                ->where('employments.status', 'ativo')
                ->whereNull('employments.ended_at');
        };
    }

    /** @return array<int, string> */
    private function setores(): array
    {
        return Department::query()->active()->orderBy('path')->get()
            ->mapWithKeys(fn (Department $setor) => [
                $setor->id => str_repeat('— ', (int) $setor->depth).$setor->name,
            ])->all();
    }
}
