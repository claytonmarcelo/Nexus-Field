<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Support\Auditor;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * As contas desta empresa. A listagem é a folha de ponto do acesso: quem entra,
 * com que papéis, pela última vez quando. A regra que manda aqui não é a
 * permissão da rota, é o alcance: ninguém concede papel com mais poder que o
 * seu, ninguém mexe em conta que enxerga mais que você, e a conta raiz não se
 * edita por tela nenhuma — ela é a identidade do sistema, não um registro.
 *
 * O supervisor gerencia o time que consegue alcançá-lo (funcionário, técnico,
 * cliente e os papéis personalizados que couberem no alcance dele); o
 * administrador alcança tudo. Excluir é degrau de administrador, e macio:
 * a conta some do acesso, o histórico fica onde está.
 */
class UserController extends Controller
{
    private const ORDENAVEIS = ['name', 'email', 'status', 'last_login_at', 'created_at'];

    private const FILTROS = ['busca', 'papel', 'situacao', 'inicio', 'fim'];

    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('users.index', [
            'contas' => $this->consulta($request)
                ->with('roles')
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name', 'asc'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'papeis' => $this->papeisDaEmpresa(),
            'situacoes' => StatusCatalog::options('user'),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'usadas' => $this->contasDaEmpresa()->count(),
            'teto' => $usuario->company?->plan?->max_users,
        ]);
    }

    public function create(Request $request): View
    {
        $editor = $request->user();

        return view('users.form', [
            'conta' => new User(['status' => 'active']),
            'papeis' => $this->papeisConcediveis($editor),
            'clientes' => $this->clientesDaEmpresa(),
            'situacoes' => StatusCatalog::options('user'),
            'usadas' => $this->contasDaEmpresa()->count(),
            'teto' => $editor->company?->plan?->max_users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validado = $request->validate($this->regras(), $this->mensagens());

        if ($recusa = $this->recusaVinculoCarteira($request, $validado, null) ?? $this->recusaPorLimite()) {
            return back()->with('erro', $recusa)->withInput();
        }

        $conta = DB::transaction(function () use ($validado): User {
            $conta = User::query()->create([
                'company_id' => TenantContext::id(),
                'name' => $validado['name'],
                'email' => $validado['email'],
                'phone' => $validado['phone'] ?? null,
                'status' => $validado['status'],
                'password' => $validado['password'],
                'client_id' => $this->clienteEfetivo($validado),
            ]);

            $conta->roles()->sync($validado['papeis']);

            return $conta;
        });

        Auditor::gravar(
            'conta criada',
            $conta,
            [],
            sprintf('%s (%s) entrou com %s.', $conta->name, $conta->email, $this->descreverPapeis($conta)),
        );

        return redirect()
            ->route('users.show', $conta)
            ->with('status', "Conta de {$conta->name} criada.");
    }

    public function show(Request $request, User $conta): View
    {
        $this->garantirDaEmpresa($conta);

        return view('users.show', [
            'conta' => $conta->load(['roles.permissions', 'client', 'technician.team', 'company.plan']),
        ]);
    }

    public function edit(Request $request, User $conta): View
    {
        $editor = $request->user();
        $this->garantirDaEmpresa($conta);
        $this->garantirAlcance($conta, $editor);

        return view('users.form', [
            'conta' => $conta->load('roles'),
            'papeis' => $this->papeisConcediveis($editor),
            'clientes' => $this->clientesDaEmpresa(),
            'situacoes' => StatusCatalog::options('user'),
            'usadas' => $this->contasDaEmpresa()->count(),
            'teto' => $editor->company?->plan?->max_users,
        ]);
    }

    public function update(Request $request, User $conta): RedirectResponse
    {
        $editor = $request->user();
        $this->garantirDaEmpresa($conta);
        $this->garantirAlcance($conta, $editor);

        $validado = $request->validate($this->regras($conta), $this->mensagens());

        if ($recusa = $this->recusaVinculoCarteira($request, $validado, $conta)) {
            return back()->with('erro', $recusa)->withInput();
        }

        if ($conta->is($editor)) {
            // Desligar a própria conta ou largar o último papel administrativo é
            // a porta de sair do prédio com a chave no bolso por dentro.
            if ($validado['status'] !== 'active') {
                return back()->with('erro', 'Você não pode desativar a própria conta.');
            }

            $administrador = $this->papelAdministrador();

            if ($administrador !== 0
                && $conta->hasRole('administrator')
                && ! in_array($administrador, array_map('intval', (array) $validado['papeis']), true)) {
                return back()->with('erro', 'Você não pode tirar de si mesmo o último papel de administrador.');
            }
        }

        $mudou = [];

        DB::transaction(function () use ($conta, $validado, &$mudou): void {
            $antes = $conta->roles()->pluck('roles.id')->all();

            $conta->update([
                'name' => $validado['name'],
                'email' => $validado['email'],
                'phone' => $validado['phone'] ?? null,
                'status' => $validado['status'],
                'client_id' => $this->clienteEfetivo($validado),
            ]);

            if (filled($validado['password'] ?? null)) {
                $conta->update(['password' => $validado['password']]);
                $mudou[] = 'senha redefinida';
            }

            $conta->roles()->sync($validado['papeis']);
            $conta->unsetRelation('roles');

            if ($antes !== $conta->roles()->pluck('roles.id')->all()) {
                $mudou[] = 'papéis agora: '.$this->descreverPapeis($conta);
            }

            if ($conta->wasChanged('status')) {
                $mudou[] = sprintf('situação: %s', StatusCatalog::label('user', $conta->status));
            }

            if ($conta->wasChanged('email')) {
                $mudou[] = sprintf('e-mail: %s', $conta->email);
            }
        });

        Auditor::gravar(
            'conta atualizada',
            $conta,
            [],
            $mudou === []
                ? sprintf('%s revisou os dados de %s.', $editor->name, $conta->name)
                : sprintf('%s · %s.', $conta->name, implode(' · ', $mudou)),
        );

        return redirect()
            ->route('users.show', $conta)
            ->with('status', "Cadastro de {$conta->name} atualizado.");
    }

    public function destroy(Request $request, User $conta): RedirectResponse
    {
        $editor = $request->user();
        $this->garantirDaEmpresa($conta);

        if ($conta->isRoot()) {
            abort(403, 'A conta raiz não se exclui por tela: ela é a identidade administrativa do sistema.');
        }

        if ($conta->is($editor)) {
            return back()->with('erro', 'Você não pode excluir a própria conta — desative outra e transfira o que for dela.');
        }

        $this->garantirAlcance($conta, $editor);

        if ($conta->technician !== null) {
            return back()->with('erro', sprintf(
                'Esta conta ainda é o acesso da ficha de técnico "%s". Desvincule a ficha antes de excluir.',
                $conta->technician->name,
            ));
        }

        $conta->delete();

        Auditor::gravar('conta excluída', $conta, [], sprintf('%s saiu do acesso; o histórico dela fica onde está.', $conta->name));

        return redirect()
            ->route('users.index')
            ->with('status', "Conta de {$conta->name} excluída.");
    }

    /** @return Builder<User> */
    private function consulta(Request $request): Builder
    {
        $query = ListFilters::busca($this->contasDaEmpresa(), $request, ['name', 'email']);
        $query = ListFilters::igual($query, $request, 'situacao', 'status', array_keys(StatusCatalog::options('user')));
        $query = ListFilters::relacionado($query, $request, 'papel', 'roles', 'id');

        return ListFilters::periodo($query, $request, 'created_at');
    }

    /**
     * O modelo User não tem escopo global de empresa — de propósito, porque o
     * login resolve conta antes de existir tenant. Toda leitura desta tela
     * filtra a coluna na mão: é ela que mantém a folha de ponto dentro do
     * próprio tenant.
     *
     * @return Builder<User>
     */
    private function contasDaEmpresa(): Builder
    {
        return User::query()->where('company_id', TenantContext::id());
    }

    /** @return array<int, string> id => nome, de todo papel desta empresa */
    private function papeisDaEmpresa(): array
    {
        return Role::query()
            ->where('company_id', TenantContext::id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> apenas os que o editor pode conceder */
    private function papeisConcediveis(User $editor): array
    {
        return Role::query()
            ->where('company_id', TenantContext::id())
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $papel) => $this->podeConceder($papel, $editor))
            ->mapWithKeys(fn (Role $papel) => [$papel->id => $papel->name])
            ->all();
    }

    /** @return array<int, string> id => nome, dos clientes ativos da carteira */
    private function clientesDaEmpresa(): array
    {
        return Client::query()
            ->where('company_id', TenantContext::id())
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * A regra do "não conceda o que não tem": um papel só pode ser entregue por
     * quem já tem, pessoalmente, todas as permissões dele. O administrador tem o
     * catálogo inteiro, então concede qualquer coisa; o supervisor fica nos papéis
     * que cabem dentro do alcance dele — e o papel de administrador fecha a porta
     * sem precisar de lista nominal.
     */
    private function podeConceder(Role $papel, User $editor): bool
    {
        return $papel->permissions()->pluck('slug')->diff($editor->permissionSlugs())->isEmpty();
    }

    /** @return array<string, mixed> */
    private function regras(?User $conta = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:180',
                Rule::unique('users', 'email')->ignore($conta?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(StatusCatalog::options('user')))],
            'password' => [
                $conta === null ? 'required' : 'nullable',
                'confirmed',
                Password::min(10)->letters()->numbers(),
            ],
            'papeis' => [
                'required', 'array', 'min:1',
                function (string $atributo, $valor, callable $falha): void {
                    $ids = array_values(array_unique(array_map('intval', (array) $valor)));
                    $papeis = Role::query()
                        ->where('company_id', TenantContext::id())
                        ->whereIn('id', $ids)
                        ->get();

                    if ($papeis->count() !== count($ids)) {
                        $falha('Um dos papéis escolhidos não é desta empresa.');

                        return;
                    }

                    if ($papeis->contains(fn (Role $papel) => ! $this->podeConceder($papel, request()->user()))) {
                        $falha('Ninguém concede um papel com mais alcance do que o seu.');
                    }
                },
            ],
            // O par papel Cliente ↔ carteira não mora aqui de propósito: regra
            // de campo ausente não roda no validador (null pula o closure), e a
            // carteira pode ser justamente o que falta. Quem casa os dois é
            // `recusaVinculoCarteira`, logo depois do validate.
            'cliente' => [
                'nullable', 'integer',
                Rule::exists('clients', 'id')->where('company_id', TenantContext::id()),
            ],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'papeis.required' => 'Escolha pelo menos um papel — sem papel, a conta entra e não enxerga nada.',
            'password.min' => 'A senha precisa de pelo menos 10 caracteres.',
        ];
    }

    /** O vínculo de carteira só existe junto do papel Cliente; fora dele, a coluna volta a nulo. */
    private function clienteEfetivo(array $validado): ?int
    {
        if (blank($validado['cliente'] ?? null)) {
            return null;
        }

        $temPapelCliente = Role::query()
            ->where('company_id', TenantContext::id())
            ->whereIn('id', (array) $validado['papeis'])
            ->where('slug', 'client')
            ->exists();

        return $temPapelCliente ? (int) $validado['cliente'] : null;
    }

    /**
     * Papel Cliente sem carteira não existe, carteira emprestada para outro
     * papel não existe, e duas contas abertas para a mesma carteira também
     * não. É o tipo de regra que o validador de campo único não alcança, então
     * mora logo depois dele — e a recusa volta como flash, com o formulário
     * recheado do que a pessoa digitou.
     */
    private function recusaVinculoCarteira(Request $request, array $validado, ?User $conta): ?string
    {
        $valor = $request->input('cliente');

        $temCliente = Role::query()
            ->where('company_id', TenantContext::id())
            ->whereIn('id', array_map('intval', (array) $validado['papeis']))
            ->where('slug', 'client')
            ->exists();

        if ($temCliente && blank($valor)) {
            return 'A conta do papel Cliente precisa apontar para a carteira que ela vai abrir.';
        }

        if (! $temCliente && filled($valor)) {
            return 'Só a conta do papel Cliente se vincula a uma carteira.';
        }

        if (filled($valor)) {
            $ocupada = User::query()
                ->where('client_id', (int) $valor)
                ->when($conta !== null, fn (Builder $q) => $q->where('id', '!=', $conta->id))
                ->exists();

            if ($ocupada) {
                return 'Esta carteira já tem uma conta de acesso.';
            }
        }

        return null;
    }

    private function recusaPorLimite(): ?string
    {
        $plano = request()->user()->company?->plan;

        if ($plano?->max_users !== null && $this->contasDaEmpresa()->count() >= $plano->max_users) {
            return sprintf(
                'O plano %s permite %d conta%s e a empresa já tem %d. A conta não foi criada.',
                $plano->name,
                $plano->max_users,
                $plano->max_users === 1 ? '' : 's',
                $this->contasDaEmpresa()->count(),
            );
        }

        return null;
    }

    private function papelAdministrador(): int
    {
        return (int) Role::query()
            ->where('company_id', TenantContext::id())
            ->where('slug', 'administrator')
            ->value('id');
    }

    /** A conta do outro tenant não existe para esta tela — nem como 403 com explicação. */
    private function garantirDaEmpresa(User $conta): void
    {
        abort_unless((int) $conta->company_id === (int) TenantContext::id(), 404);
    }

    /**
     * Só se mexe em conta que enxerga menos ou igual a você, e a raiz fica fora
     * de qualquer alcance, inclusive o do administrador: a regra dela vale no
     * modelo e vale aqui, e tela nenhuma negocia com ela.
     */
    private function garantirAlcance(User $conta, User $editor): void
    {
        if ($conta->isRoot()) {
            abort(403, 'A conta raiz não se edita por tela: ela é a identidade administrativa do sistema.');
        }

        abort_unless(
            $editor->isRoot() || collect($conta->permissionSlugs())->diff($editor->permissionSlugs())->isEmpty(),
            403,
            'Você não pode mexer numa conta que enxerga mais do que você.',
        );
    }

    private function descreverPapeis(User $conta): string
    {
        $nomes = $conta->roles()->orderBy('name')->pluck('name')->all();

        return $nomes === [] ? 'nenhum papel' : implode(' + ', $nomes);
    }
}
