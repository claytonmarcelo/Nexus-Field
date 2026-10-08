<?php

namespace App\Http\Controllers\Technicians;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Technician;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Equipe do campo: quem está junto na mesma região, sob a liderança de um técnico
 * desta empresa. A entrada e a saída de membro têm data, porque "quem atendia
 * aqui em março" é pergunta que a operação faz e o histórico não pode apagar.
 */
class TeamController extends Controller
{
    private const ORDENAVEIS = ['name', 'region', 'status', 'created_at'];

    private const FILTROS = ['busca', 'situacao', 'regiao'];

    public function index(Request $request): View
    {
        return view('teams.index', [
            'equipes' => $this->consulta($request)
                ->with('leader')
                ->withCount(['technicians as membros_count' => fn (Builder $q) => $q->whereNull('team_members.left_at')])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'regioes' => $this->regioes(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'situacoes' => StatusCatalog::options('team'),
        ]);
    }

    public function create(): View
    {
        return view('teams.form', [
            'equipe' => new Team(['status' => 'active']),
            'situacoes' => StatusCatalog::options('team'),
            'tecnicos' => $this->tecnicosDaEmpresa(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $equipe = Team::create($this->validar($request));

        $this->sincronizarMembros($equipe, $request);

        return redirect()
            ->route('teams.show', $equipe)
            ->with('status', "Equipe “{$equipe->name}” criada.");
    }

    public function show(Request $request, Team $equipe): View
    {
        $equipe->load(['leader', 'technicians']);

        return view('teams.show', [
            'equipe' => $equipe,
            'lider' => $equipe->leader,
            'quadro' => $equipe->technicians->filter(fn (Technician $t) => $t->pivot->left_at === null),
            'saidas' => $equipe->technicians->reject(fn (Technician $t) => $t->pivot->left_at === null),
            'entradas' => $this->quemPodeEntrar($equipe),
            'ordens' => $this->ordensDaEquipe($equipe),
            'situacoes' => StatusCatalog::options('team'),
        ]);
    }

    public function edit(Team $equipe): View
    {
        $equipe->load('technicians');

        return view('teams.form', [
            'equipe' => $equipe,
            'situacoes' => StatusCatalog::options('team'),
            'tecnicos' => $this->tecnicosDaEmpresa(),
        ]);
    }

    public function update(Request $request, Team $equipe): RedirectResponse
    {
        $equipe->update($this->validar($request, $equipe));

        $this->sincronizarMembros($equipe, $request);

        return redirect()
            ->route('teams.show', $equipe)
            ->with('status', "Equipe “{$equipe->name}” atualizada.");
    }

    public function destroy(Team $equipe): RedirectResponse
    {
        $emAndamento = $equipe->technicians()->wherePivotNull('left_at')->count();

        if ($emAndamento > 0) {
            return back()->with('erro', sprintf(
                '%s ainda tem %s no quadro. Retire os membros ou inative a equipe — excluir apagara o registro de quem trabalhou junto.',
                $equipe->name,
                $emAndamento === 1 ? 'um técnico' : 'técnicos no quadro',
            ));
        }

        $nome = $equipe->name;
        $equipe->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', "Equipe “{$nome}” excluída.");
    }

    /** Entrada de um técnico no quadro: quem já saiu volta com a data de agora. */
    public function adicionarMembro(Request $request, Team $equipe): RedirectResponse
    {
        $validado = $request->validate([
            'tecnico_id' => ['required', 'integer', Rule::exists('technicians', 'id')
                ->where(fn ($query) => $query->where('company_id', TenantContext::id()))],
        ], [
            'tecnico_id.exists' => 'Este técnico não pertence à sua empresa.',
        ]);

        $equipe->technicians()->syncWithoutDetaching([
            $validado['tecnico_id'] => ['joined_at' => now(), 'left_at' => null],
        ]);

        return back()->with('status', 'Técnico adicionado à equipe.');
    }

    /** Saída não é apagamento: a linha de pivô fica com `left_at`, e o histórico sobrevive. */
    public function liberarMembro(Team $equipe, Technician $tecnico): RedirectResponse
    {
        $this->garantirQueEPessoaDaEquipe($equipe, $tecnico);

        $equipe->technicians()->updateExistingPivot($tecnico->id, ['left_at' => now()]);

        if ((int) $equipe->leader_id === (int) $tecnico->id) {
            $equipe->update(['leader_id' => null]);
        }

        return back()->with('status', "{$tecnico->name} saiu da equipe, e a entrada e a saída continuam registradas.");
    }

    private function consulta(Request $request): Builder
    {
        $query = ListFilters::igual(Team::query(), $request, 'situacao', 'status', ['active', 'inactive']);
        $regiao = trim((string) $request->query('regiao'));

        $query = ListFilters::busca($query, $request, ['name', 'region']);

        return $regiao === '' ? $query : $query->where('region', $regiao);
    }

    /** @return array<int, string> */
    private function tecnicosDaEmpresa(): array
    {
        return Technician::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Opções de entrada: técnico ativo da empresa que ainda não está no quadro. */
    private function quemPodeEntrar(Team $equipe): array
    {
        $noQuadro = $equipe->technicians->whereNull('pivot.left_at')->pluck('id')->all();

        return Technician::query()
            ->where('status', '!=', 'inactive')
            ->whereNotIn('id', $noQuadro)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** Ordens abertas das últimas semanas que passaram por esta equipe. */
    private function ordensDaEquipe(Team $equipe): array
    {
        return $equipe->technicians()
            ->whereNull('team_members.left_at')
            ->join('service_orders', 'service_orders.technician_id', '=', 'technicians.id')
            ->whereIn('service_orders.status', ['open', 'in_progress', 'on_hold'])
            ->select('service_orders.id', 'service_orders.number', 'service_orders.title', 'technicians.name as tecnico')
            ->distinct()
            ->orderByDesc('service_orders.id')
            ->limit(8)
            ->get()
            ->all();
    }

    /** @param  array<int, int>|null  $ids */
    private function sincronizarMembros(Team $equipe, Request $request): void
    {
        $ids = array_filter(array_map('intval', (array) $request->input('membros', [])));

        // Só entram técnicos desta empresa: o `sync` com id alheio criaria linha de
        // pivô cruzando dois tenants.
        $ids = Technician::query()->whereIn('id', $ids)->pluck('id')->all();

        $atuais = $equipe->technicians()->wherePivotNull('left_at')->pluck('technicians.id')->all();

        foreach (array_diff($atuais, $ids) as $saindo) {
            $equipe->technicians()->updateExistingPivot($saindo, ['left_at' => now()]);
        }

        foreach (array_diff($ids, $atuais) as $entrando) {
            $equipe->technicians()->syncWithoutDetaching([$entrando => ['joined_at' => now(), 'left_at' => null]]);
        }
    }

    private function garantirQueEPessoaDaEquipe(Team $equipe, Technician $tecnico): void
    {
        abort_unless(
            $equipe->technicians()->where('technicians.id', $tecnico->id)->wherePivotNull('left_at')->exists(),
            404,
            'Este técnico não está no quadro desta equipe.'
        );
    }

    private function regioes(): array
    {
        return Team::query()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region', 'region')
            ->all();
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Team $equipe = null): array
    {
        $empresa = TenantContext::id();

        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('teams', 'name')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))
                ->ignore($equipe?->id)],
            'leader_id' => ['nullable', Rule::exists('technicians', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa))],
            'region' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(array_keys(StatusCatalog::options('team')))],
        ], [
            'name.unique' => 'Já existe uma equipe com este nome nesta empresa.',
            'leader_id.exists' => 'O líder precisa ser um técnico da sua empresa.',
        ]);
    }
}
