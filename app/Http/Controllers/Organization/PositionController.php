<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\ReageARecusa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StorePositionRequest;
use App\Http\Requests\Organization\UpdatePositionRequest;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Services\Organizacao\GestorDeEstrutura;
use App\Services\Recusa;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cargo descreve a função na estrutura. O papel sugerido não concede acesso:
 * quem concede é o papel gravado na conta, e esta tela só o aponta como
 * orientação para quem for conceder.
 */
class PositionController extends Controller
{
    use ReageARecusa;

    private const ORDENAVEIS = ['name', 'level', 'created_at'];

    public function __construct(private readonly GestorDeEstrutura $gestor) {}

    public function index(Request $request): View
    {
        $query = Position::query();
        $situacao = trim((string) $request->query('situacao'));

        if (in_array($situacao, array_keys(StatusCatalog::options('organization')), true)) {
            $query->where('active', $situacao === 'active');
        }

        $query = ListFilters::relacionado($query, $request, 'setor', 'department');
        $query = ListFilters::busca($query, $request, ['name', 'description']);

        return view('organization.positions.index', [
            'cargos' => $query
                ->with(['department:id,name', 'suggestedRole:id,name'])
                ->withCount('employments')
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'setores' => Department::query()->orderBy('path')->get()
                ->mapWithKeys(fn (Department $setor) => [
                    $setor->id => str_repeat('— ', (int) $setor->depth).$setor->name,
                ])->all(),
            'situacoes' => StatusCatalog::options('organization'),
            'filtrosAtivos' => ListFilters::ativos($request, ['busca', 'situacao', 'setor']),
        ]);
    }

    public function create(): View
    {
        return view('organization.positions.form', [
            'cargo' => new Position(['active' => true, 'level' => 5]),
            'setores' => $this->setores(),
            'papeis' => Role::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(StorePositionRequest $request): RedirectResponse
    {
        try {
            $cargo = $this->gestor->salvarCargo($request->validated());
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.positions.show', $cargo)
            ->with('status', "Cargo “{$cargo->name}” cadastrado.");
    }

    public function show(Position $cargo): View
    {
        $cargo->load(['department', 'suggestedRole']);

        return view('organization.positions.show', [
            'cargo' => $cargo,
            'vinculos' => $cargo->employments()->with('user:id,name')->orderBy('employee_code')->get(),
        ]);
    }

    public function edit(Position $cargo): View
    {
        return view('organization.positions.form', [
            'cargo' => $cargo,
            'setores' => $this->setores(),
            'papeis' => Role::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function update(UpdatePositionRequest $request, Position $cargo): RedirectResponse
    {
        try {
            $this->gestor->salvarCargo($request->validated(), $cargo);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.positions.show', $cargo)
            ->with('status', "Cargo “{$cargo->name}” atualizado.");
    }

    public function destroy(Position $cargo): RedirectResponse
    {
        try {
            $this->gestor->excluirCargo($cargo);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return redirect()
            ->route('organization.positions.index')
            ->with('status', "Cargo “{$cargo->name}” excluído.");
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
