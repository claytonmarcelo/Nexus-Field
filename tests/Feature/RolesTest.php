<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A outra metade da Fase 20: o papel de sistema é do catálogo — a tela mostra,
 * não edita — e o papel personalizado obedece a mesma lei do alcance: ninguém
 * desenha permissão que não tem, ninguém exclui papel que ainda é o acesso de
 * alguém, e o slug nasce com o nome e morre com ele. O comando de
 * sincronização fecha o ciclo: muda o catálogo, roda o comando, e as empresas
 * vivas sentem a mudança sem perder os papéis que criaram por conta própria.
 */
class RolesTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_listagem_separa_o_sistema_do_personalizado(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $this->papelPersonalizado($empresa, 'Leitor de relatórios', ['reports.view']);

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Administrator')
            ->assertSee('Leitor de relatórios')
            ->assertSee('5 de sistema');
    }

    public function test_papel_de_sistema_e_de_vitrina_editar_e_excluir_sao_403(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisor = $this->papel($empresa, 'supervisor');

        $this->actingAs($admin)->get(route('roles.edit', $supervisor))->assertForbidden();
        $this->actingAs($admin)->put(route('roles.update', $supervisor), [
            'name' => 'Supervisor dobrado',
            'permissoes' => ['dashboard.view'],
        ])->assertForbidden();
        $this->actingAs($admin)->delete(route('roles.destroy', $supervisor))->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $supervisor->id, 'name' => $supervisor->name]);
    }

    public function test_nome_reservado_pelo_catalogo_nao_vira_slug(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Supervisor',
            'permissoes' => ['dashboard.view'],
        ])->assertSessionHas('erro', fn (?string $mensagem): bool => str_contains((string) $mensagem, 'slug de papel de sistema'));

        $this->assertDatabaseMissing('roles', ['company_id' => $empresa->id, 'slug' => 'supervisor']);
    }

    public function test_papel_personalizado_nasce_com_o_que_a_marcao_desenha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $resposta = $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Leitor de relatórios',
            'descricao' => 'Só enxerga números.',
            'permissoes' => ['dashboard.view', 'reports.view', 'reports.export'],
        ]);

        $papel = Role::query()->where('company_id', $empresa->id)->where('slug', 'leitor-de-relatorios')->firstOrFail();
        $resposta->assertRedirect(route('roles.show', $papel));

        $this->assertFalse($papel->is_system);
        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'reports.export', 'reports.view'],
            $papel->permissions()->pluck('slug')->all(),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'papel criado', 'entity_id' => (string) $papel->id]);
    }

    public function test_ninguem_desenha_papel_com_permissao_que_nao_tem(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        // Um editor que não é raiz, mas tem os papéis de gerência de papel.
        $coordenador = $this->papelPersonalizado($empresa, 'Coordenador', [
            'roles.view', 'roles.create', 'users.view', 'dashboard.view',
        ]);
        $miriam = $this->contaComPapel($empresa, $coordenador, 'miriam@test.local');

        $this->actingAs($miriam)->post(route('roles.store'), [
            'name' => 'Papel abusivo',
            'permissoes' => ['dashboard.view', 'financial.delete'],
        ])->assertSessionHasErrors('permissoes');

        $this->assertDatabaseMissing('roles', ['company_id' => $empresa->id, 'slug' => 'papel-abusivo']);

        // E o que ele tem, ele desenha sem reclamar.
        $this->actingAs($miriam)->post(route('roles.store'), [
            'name' => 'Leitor de relatórios',
            'permissoes' => ['dashboard.view'],
        ])->assertSessionHasNoErrors();
    }

    public function test_papel_em_uso_nao_sai_de_cena(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $leitor = $this->papelPersonalizado($empresa, 'Leitor de relatórios', ['reports.view']);
        $this->contaComPapel($empresa, $leitor, 'leitor@test.local');

        $this->actingAs($admin)->delete(route('roles.destroy', $leitor))
            ->assertSessionHas('erro', fn (?string $mensagem): bool => str_contains((string) $mensagem, 'ainda é o acesso de 1 conta'));

        $this->assertNotNull($leitor->fresh());

        $leitor->delete();
        $this->assertDatabaseMissing('roles', ['id' => $leitor->id]);
    }

    public function test_papel_do_outro_tenant_e_personalizado_alheio_sao_404(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');
        $alheio = $this->papelPersonalizado($vizinha, 'Leitor alheio', ['reports.view']);

        $this->actingAs($admin)->get(route('roles.show', $alheio))->assertNotFound();
        $this->actingAs($admin)->delete(route('roles.destroy', $alheio))->assertNotFound();
    }

    public function test_supervisor_ve_os_papeis_mas_nao_desenha_nem_mexe(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $leitor = $this->papelPersonalizado($empresa, 'Leitor de relatórios', ['reports.view']);

        $this->actingAs($supervisora)->get(route('roles.index'))->assertOk();
        $this->actingAs($supervisora)->get(route('roles.show', $leitor))->assertOk();
        $this->actingAs($supervisora)->get(route('roles.create'))->assertForbidden();
        $this->actingAs($supervisora)->put(route('roles.update', $leitor), [
            'name' => 'Leitor maquiado',
            'permissoes' => ['reports.view'],
        ])->assertForbidden();
    }

    public function test_editar_um_papel_reajusta_o_alcance_de_quem_o_veste(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $leitor = $this->papelPersonalizado($empresa, 'Leitor de relatórios', ['reports.view']);
        $leitora = $this->contaComPapel($empresa, $leitor, 'leitora@test.local');

        $this->actingAs($admin)->put(route('roles.update', $leitor), [
            'name' => 'Leitor de relatórios e números',
            'permissoes' => ['reports.view', 'dashboard.view'],
        ])->assertRedirect(route('roles.show', $leitor));

        $this->assertTrue($leitora->fresh()->hasPermission('dashboard.view'));
        $this->assertFalse($leitora->fresh()->hasPermission('financial.view'));
    }

    public function test_o_comando_regava_o_catalogo_sem_encostar_no_personalizado(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $leitor = $this->papelPersonalizado($empresa, 'Leitor de relatórios', ['reports.view']);

        // Alguém mexeu no supervisor de uma empresa viva; o catálogo ganhou as
        // gerências de conta na Fase 20 e só a sincronização propaga isso para cá.
        $supervisor = $this->papel($empresa, 'supervisor');
        $supervisor->permissions()->detach(
            Permission::query()->whereIn('slug', ['users.view', 'users.create', 'users.update', 'roles.view'])->pluck('id')->all()
        );
        // O papel não tem `hasPermission` — quem tem alcance é a conta. Aqui a
        // pergunta é de catálogo: a linha existe no papel sim ou não.
        $this->assertFalse($supervisor->permissions()->where('slug', 'users.create')->exists());

        $this->artisan('nf:papeis:sincronizar')->assertSuccessful();

        $this->assertTrue($supervisor->permissions()->where('slug', 'users.create')->exists());
        $this->assertTrue($supervisor->permissions()->where('slug', 'users.view')->exists());
        $this->assertSame(
            $this->ordenado(PermissionCatalog::byRole('supervisor')),
            $this->ordenado($supervisor->permissions()->pluck('slug')->all()),
            'Depois do comando, o papel de sistema é exatamente o catálogo.',
        );
        $this->assertSame('Supervisor / Gestor', $supervisor->fresh()->name);

        // E o personalizado fica intacto, do jeito e com o nome de quem o criou.
        $this->assertSame(['reports.view'], $leitor->permissions()->pluck('slug')->all());
        $this->assertSame('Leitor de relatórios', $leitor->fresh()->name);

        // Rodar de novo não muda nada: é idempotente.
        $antes = Role::query()->count();
        $this->artisan('nf:papeis:sincronizar')->assertSuccessful();
        $this->assertSame($antes, Role::query()->count());
    }

    /** @return array<int, string> */
    private function ordenado(array $slugs): array
    {
        sort($slugs);

        return $slugs;
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    private function conta(Company $empresa, string $papel, string $email): User
    {
        TenantContext::set($empresa->id);
        $usuario = $this->makeUser($papel, $empresa, $email);
        TenantContext::forget();

        return $usuario;
    }

    private function papel(Company $empresa, string $slug): Role
    {
        $existente = Role::query()->where('company_id', $empresa->id)->where('slug', $slug)->first();

        return $existente ?? $this->makeRole($slug, $empresa);
    }

    /** @param array<int, string> $slugs */
    private function papelPersonalizado(Company $empresa, string $nome, array $slugs): Role
    {
        $papel = Role::query()->create([
            'company_id' => $empresa->id,
            'name' => $nome,
            'slug' => Str::slug($nome),
            'is_system' => false,
        ]);

        $papel->permissions()->sync(
            Permission::query()->whereIn('slug', $slugs)->pluck('id')->all()
        );

        return $papel;
    }

    private function contaComPapel(Company $empresa, Role $papel, string $email): User
    {
        $usuario = User::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Conta '.$papel->name,
            'email' => $email,
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);

        $usuario->roles()->attach($papel->id);

        return $usuario->fresh('roles.permissions');
    }
}
