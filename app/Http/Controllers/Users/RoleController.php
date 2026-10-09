<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Auditor;
use App\Support\ListFilters;
use App\Support\PermissionCatalog;
use App\Support\Roles;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Os papéis desta empresa. Os cinco de sistema vêm do catálogo e são regravados
 * pelo comando de sincronização — por isso a tela os mostra, mas não os edita:
 * mexer neles por aqui seria divergir do catálogo na primeira re-sincronização.
 * Papel personalizado é outra coisa: nasce aqui, vive do que aqui se marca, e só
 * morre quando nenhuma conta o usa.
 *
 * O slug é carimbo, não rótulo: escolhido no nascimento, não se troca depois —
 * é ele que o catálogo, o seeder e o comando reconhecem. E a regra do alcance
 * vale também aqui: ninguém desenha um papel com permissão que o próprio editor
 * não tem.
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        return view('roles.index', [
            'papeis' => $this->consulta($request)
                ->withCount('permissions')
                ->withCount('users')
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(),
            'sistema' => count(Roles::SLUGS),
        ]);
    }

    public function show(Request $request, Role $papel): View
    {
        $this->garantirDaEmpresa($papel);

        return view('roles.show', [
            'papel' => $papel->load('permissions'),
            'contas' => $papel->users()
                ->where('users.company_id', TenantContext::id())
                ->whereNull('users.deleted_at')
                ->orderBy('name')
                ->get(),
            'matriz' => $this->matriz($papel->permissions->pluck('slug')->all()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('roles.form', [
            'papel' => new Role,
            'matriz' => $this->matriz([], $request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validado = $request->validate($this->regras(), $this->mensagens());

        $slug = Str::slug($validado['name']);

        if (in_array($slug, Roles::SLUGS, true)) {
            return back()
                ->with('erro', 'Este nome reserva um slug de papel de sistema. Escolha outro nome.')
                ->withInput();
        }

        $papel = DB::transaction(function () use ($validado, $slug): Role {
            $papel = Role::query()->create([
                'company_id' => TenantContext::id(),
                'name' => $validado['name'],
                'slug' => $slug,
                'description' => $validado['descricao'] ?? null,
                'is_system' => false,
            ]);

            $papel->permissions()->sync($this->idsDasPermissoes($validado['permissoes']));

            return $papel;
        });

        Auditor::gravar(
            'papel criado',
            $papel,
            [],
            sprintf('%s (%s) nasceu com %d permissão%s.', $papel->name, $papel->slug, $papel->permissions->count(), $papel->permissions->count() === 1 ? '' : 's'),
        );

        return redirect()
            ->route('roles.show', $papel)
            ->with('status', "Papel {$papel->name} criado.");
    }

    public function edit(Request $request, Role $papel): View
    {
        $this->garantirDaEmpresa($papel);
        $this->garantirNaoSistema($papel);

        return view('roles.form', [
            'papel' => $papel->load('permissions'),
            'matriz' => $this->matriz($papel->permissions->pluck('slug')->all(), $request->user()),
        ]);
    }

    public function update(Request $request, Role $papel): RedirectResponse
    {
        $this->garantirDaEmpresa($papel);
        $this->garantirNaoSistema($papel);

        $validado = $request->validate($this->regras($papel), $this->mensagens());

        $antes = $papel->permissions()->pluck('slug')->sort()->values()->all();

        DB::transaction(function () use ($papel, $validado): void {
            $papel->update([
                'name' => $validado['name'],
                'description' => $validado['descricao'] ?? null,
            ]);

            $papel->permissions()->sync($this->idsDasPermissoes($validado['permissoes']));
        });

        $depois = $papel->permissions()->pluck('slug')->sort()->values()->all();

        $mudancas = [];
        if ($papel->wasChanged('name')) {
            $mudancas[] = sprintf('nome: %s', $papel->name);
        }
        if ($antes !== $depois) {
            $entrantes = array_diff($depois, $antes);
            $saindo = array_diff($antes, $depois);
            $mudancas[] = sprintf(
                'permissões: +%d, −%d (agora %d)',
                count($entrantes),
                count($saindo),
                count($depois),
            );
        }

        Auditor::gravar(
            'papel atualizado',
            $papel,
            [],
            $mudancas === []
                ? sprintf('%s revisou o papel %s.', $request->user()->name, $papel->name)
                : sprintf('%s · %s.', $papel->name, implode(' · ', $mudancas)),
        );

        return redirect()
            ->route('roles.show', $papel)
            ->with('status', "Papel {$papel->name} atualizado.");
    }

    public function destroy(Request $request, Role $papel): RedirectResponse
    {
        $this->garantirDaEmpresa($papel);

        if ($papel->is_system) {
            abort(403, 'Papel de sistema não se exclui por tela: ele é regravado pelo catálogo a cada sincronização.');
        }

        $emUso = $papel->users()
            ->where('users.company_id', TenantContext::id())
            ->whereNull('users.deleted_at')
            ->count();

        if ($emUso > 0) {
            return back()->with('erro', sprintf(
                'O papel %s ainda é o acesso de %d conta%s. Troque o papel delas antes de excluí-lo.',
                $papel->name,
                $emUso,
                $emUso === 1 ? '' : 's',
            ));
        }

        $nome = $papel->name;
        $papel->delete();

        Auditor::gravar('papel excluído', null, [], sprintf('%s saiu do catálogo da empresa sem deixar conta órfã.', $nome));

        return redirect()
            ->route('roles.index')
            ->with('status', "Papel {$nome} excluído.");
    }

    /**
     * A matriz de permissões: cada módulo do catálogo com suas ações em
     * português, e a marcação de quem já tem. É a mesma ordem do catálogo,
     * então a tela e o seeder nunca divergem. No formulário entra um editor:
     * a matriz filtra pelas permissões que ele tem (mais as já marcadas, para
     * o papel existente não perder alcance escondido do desenhista).
     *
     * @param  array<int, string>  $concedidas
     * @return array<int, array{modulo: string, rotulo: string, acoes: array<int, array{slug: string, rotulo: string, marcada: bool}>}>
     */
    private function matriz(array $concedidas, ?User $editor = null): array
    {
        $alcance = $editor === null ? null : $editor->permissionSlugs();
        $matriz = [];

        foreach (PermissionCatalog::MODULES as $modulo => $acoes) {
            $linhas = [];

            foreach ($acoes as $acao) {
                $slug = "{$modulo}.{$acao}";
                $marcada = in_array($slug, $concedidas, true);

                if ($alcance !== null && ! $marcada && ! $editor->isRoot() && ! in_array($slug, $alcance, true)) {
                    continue;
                }

                $linhas[] = [
                    'slug' => $slug,
                    'rotulo' => PermissionCatalog::acaoRotulo($acao),
                    'marcada' => $marcada,
                ];
            }

            if ($linhas !== []) {
                $matriz[] = [
                    'modulo' => $modulo,
                    'rotulo' => PermissionCatalog::moduloRotulo($modulo),
                    'acoes' => $linhas,
                ];
            }
        }

        return $matriz;
    }

    /** @return Builder<Role> */
    private function consulta(Request $request): Builder
    {
        return ListFilters::busca(
            Role::query()->where('company_id', TenantContext::id()),
            $request,
            ['name', 'slug'],
        );
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array<int, int>
     */
    private function idsDasPermissoes(array $slugs): array
    {
        return Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();
    }

    /** @return array<string, mixed> */
    private function regras(?Role $papel = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('company_id', TenantContext::id())->ignore($papel?->id),
            ],
            'descricao' => ['nullable', 'string', 'max:500'],
            'permissoes' => [
                'required', 'array', 'min:1',
                function (string $atributo, $valor, callable $falha): void {
                    $slugs = array_values(array_unique((array) $valor));
                    $existentes = Permission::query()->whereIn('slug', $slugs)->pluck('slug')->all();

                    if (count($existentes) !== count($slugs)) {
                        $falha('Uma das permissões marcadas não existe no catálogo.');

                        return;
                    }

                    $editor = request()->user();

                    if (! $editor->isRoot() && collect($slugs)->diff($editor->permissionSlugs())->isNotEmpty()) {
                        $falha('Ninguém desenha um papel com permissão que ele mesmo não tem.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'name.unique' => 'Já existe um papel com este nome nesta empresa.',
            'permissoes.required' => 'Marque pelo menos uma permissão — papel vazio é conta que entra e não enxerga nada.',
        ];
    }

    private function garantirDaEmpresa(Role $papel): void
    {
        abort_unless((int) $papel->company_id === (int) TenantContext::id(), 404);
    }

    private function garantirNaoSistema(Role $papel): void
    {
        abort_if(
            $papel->is_system,
            403,
            'Papel de sistema se ajusta no catálogo e se regravado pelo comando de sincronização, não por tela.'
        );
    }
}
