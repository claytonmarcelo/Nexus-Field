<?php

namespace App\Http\Controllers\Technicians;

use App\Http\Controllers\Concerns\EmEdicao;
use App\Http\Controllers\Controller;
use App\Models\Specialty;
use App\Models\Technician;
use App\Models\User;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ficha de quem atende em campo. A listagem, o filtro e a página vêm do MySQL da
 * empresa aberta na sessão; a localização que aparece aqui é a última medida de
 * verdade (coluna `latitude`/`longitude` com `last_location_at`), gravada pelo
 * check-in da fase 15 — nunca um ponto desenhado na tela.
 */
class TechnicianController extends Controller
{
    use EmEdicao;

    private const ORDENAVEIS = ['name', 'region', 'status', 'admission_date', 'created_at'];

    private const FILTROS = ['busca', 'situacao', 'regiao', 'especialidade'];

    public function index(Request $request): View
    {
        return view('technicians.index', [
            'tecnicos' => $this->consulta($request)
                ->with('addresses')
                ->with('specialties')
                ->withCount(['serviceOrders', 'teams'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'regioes' => $this->regioes(),
            'especialidades' => Specialty::query()->orderBy('name')->pluck('name', 'id')->all(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'situacoes' => StatusCatalog::options('technician') + ['excluidos' => 'Excluídos temporariamente'],
        ]);
    }

    public function create(): View
    {
        return view('technicians.form', [
            'tecnico' => new Technician(['status' => 'available']),
            'situacoes' => StatusCatalog::options('technician'),
            'especialidades' => $this->especialidadesDisponiveis(),
            'contas' => $this->contasSemFicha(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tecnico = Technician::create($this->validar($request));

        $tecnico->specialties()->sync($this->especialidadesDaEmpresa($request));

        return redirect()
            ->route('technicians.show', $tecnico)
            ->with('status', "Técnico “{$tecnico->name}” cadastrado.");
    }

    public function show(Request $request, Technician $tecnico): View
    {
        $tecnico->load(['specialties', 'teams', 'addresses', 'user']);

        return view('technicians.show', [
            'tecnico' => $tecnico,
            'historico' => $this->historico($tecnico),
            'situacoes' => StatusCatalog::options('technician'),
            'tiposDeEndereco' => StatusCatalog::options('address'),
            'especialidades' => $tecnico->specialties->pluck('name')->all(),
            'equipes' => $tecnico->teams,
            'enderecoEmEdicao' => $this->emEdicao($request, 'editar_endereco', $tecnico->addresses),
            'localizacoes' => $tecnico->checkins()->latest('checkin_at')->limit(5)->get(),
        ]);
    }

    public function edit(Technician $tecnico): View
    {
        $tecnico->load('specialties');

        return view('technicians.form', [
            'tecnico' => $tecnico,
            'situacoes' => StatusCatalog::options('technician'),
            'especialidades' => $this->especialidadesDisponiveis(),
            'contas' => $this->contasSemFicha($tecnico),
        ]);
    }

    public function update(Request $request, Technician $tecnico): RedirectResponse
    {
        $tecnico->update($this->validar($request, $tecnico));
        $tecnico->specialties()->sync($this->especialidadesDaEmpresa($request));

        return redirect()
            ->route('technicians.show', $tecnico)
            ->with('status', "Técnico “{$tecnico->name}” atualizado.");
    }

    /**
     * Técnico com ordens, check-ins ou compromissos na agenda não some: apagar a
     * ficha apagaria a autoria do trabalho. A exclusão só passa quando o cadastro
     * ainda não gerou nada, e o caminho para quem já trabalhou é Inativo.
     */
    public function destroy(Technician $tecnico): RedirectResponse
    {
        $vinculos = collect($this->historico($tecnico))
            ->filter(fn (array $linha) => $linha['total'] > 0)
            ->map(fn (array $linha) => $linha['total'].' '.($linha['total'] === 1 ? $linha['singular'] : $linha['plural']))
            ->implode(', ');

        if ($vinculos !== '') {
            return back()->with('erro', sprintf(
                '%s já gerou trabalho registrado (%s). Para tirá-lo da escala sem perder nada, mude a situação para Inativo.',
                $tecnico->name,
                $vinculos,
            ));
        }

        $nome = $tecnico->name;
        $tecnico->delete();

        return redirect()
            ->route('technicians.index')
            ->with('status', "Técnico “{$nome}” excluído.");
    }

    public function restore(int $tecnico): RedirectResponse
    {
        $registro = Technician::withTrashed()->findOrFail($tecnico);
        $registro->restore();

        return redirect()
            ->route('technicians.show', $registro)
            ->with('status', "Técnico “{$registro->name}” restaurado.");
    }

    private function consulta(Request $request): Builder
    {
        $situacao = trim((string) $request->query('situacao'));
        $regiao = trim((string) $request->query('regiao'));

        $query = $situacao === 'excluidos'
            ? Technician::query()->withTrashed()->onlyTrashed()
            : ListFilters::igual(
                Technician::query(),
                $request,
                'situacao',
                'status',
                array_keys(StatusCatalog::options('technician')),
            );

        $query = ListFilters::busca($query, $request, ['name', 'document', 'email', 'phone', 'region']);

        if ($regiao !== '') {
            $query->where('region', $regiao);
        }

        return ListFilters::relacionado($query, $request, 'especialidade', 'specialties');
    }

    /** @return array<int, array{singular: string, plural: string, total: int}> */
    private function historico(Technician $tecnico): array
    {
        return [
            ['singular' => 'ordem de serviço', 'plural' => 'ordens de serviço', 'total' => $tecnico->serviceOrders()->count()],
            ['singular' => 'check-in', 'plural' => 'check-ins', 'total' => $tecnico->checkins()->count()],
            ['singular' => 'compromisso na agenda', 'plural' => 'compromissos na agenda', 'total' => $tecnico->appointments()->count()],
        ];
    }

    /** @return array<int, string> */
    private function especialidadesDisponiveis(): array
    {
        return Specialty::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Contas de acesso desta empresa que ainda não têm ficha de técnico: o vínculo
     * é um para um (`technicians.user_id` é único por empresa), então a lista de
     * opções já nasce sem quem está tomado.
     *
     * @return array<int, string>
     */
    private function contasSemFicha(?Technician $atual = null): array
    {
        $tomadas = Technician::query()->whereNotNull('user_id')->pluck('user_id')->all();

        $opcoes = User::query()
            ->whereNotIn('id', $tomadas)
            ->orderBy('name')
            ->pluck('name', 'id');

        // A conta já vinculada a esta ficha continua na lista, marcada como atual:
        // sem ela, salvar a ficha jogaria o vínculo fora sem o usuário pedir.
        if ($atual?->user_id && $atual->user) {
            $opcoes->put($atual->user_id, $atual->user->name.' (atual)');
        }

        return $opcoes->all();
    }

    /** Regiões que realmente aparecem nas fichas desta empresa. */
    private function regioes(): array
    {
        return Technician::query()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region', 'region')
            ->all();
    }

    /** @return array<int, int> ids de especialidade que são desta empresa */
    private function especialidadesDaEmpresa(Request $request): array
    {
        $ids = array_filter(array_map('intval', (array) $request->input('especialidades', [])));

        if ($ids === []) {
            return [];
        }

        // Filtrar pelos ids da empresa antes de sincronizar: um id de outro tenant
        // enviado no form não tem o que fazer aqui, e não pode virar linha de pivô.
        return Specialty::query()->whereIn('id', $ids)->pluck('id')->all();
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Technician $tecnico = null): array
    {
        $empresa = TenantContext::id();

        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'document' => [
                'nullable',
                'string',
                'max:25',
                Rule::unique('technicians', 'document')
                    ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))
                    ->ignore($tecnico?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'status' => ['required', Rule::in(array_keys(StatusCatalog::options('technician')))],
            'region' => ['nullable', 'string', 'max:120'],
            'admission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'user_id' => [
                'nullable',
                // A conta tem de ser desta empresa: um id chutado não pode amarrar
                // o técnico de outro tenant numa ficha que não é dele.
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at')),
                Rule::unique('technicians', 'user_id')
                    ->where(fn ($query) => $query->where('company_id', $empresa))
                    ->ignore($tecnico?->id),
            ],
        ], [
            'document.unique' => 'Este documento já está cadastrado nesta empresa.',
            'user_id.unique' => 'Esta conta de acesso já tem ficha de técnico nesta empresa.',
        ]);
    }
}
