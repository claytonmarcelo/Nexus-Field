<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Technician;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A Fase 20 não é o CRUD de conta — é o alcance. O que se prende aqui: ninguém
 * concede papel maior que o próprio, ninguém mexe em conta que enxerga mais, a
 * raiz não se edita por tela nem para o administrador, a própria conta não se
 * desliga nem se rebaixa, o plano fecha a porta da cota de contas, papel Cliente
 * sem carteira não existe, e excluir é macio: some do acesso, fica no histórico.
 */
class UsersTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_folha_de_ponto_so_mostra_contas_da_propria_empresa(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');
        $alheia = $this->conta($vizinha, 'administrator', 'la-fora@test.local');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('a@test.local')
            ->assertDontSee($alheia->email);
    }

    public function test_a_conta_do_outro_tenant_e_um_404_sem_conversar(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');
        $alheia = $this->conta($vizinha, 'employee', 'la-fora@test.local');

        $this->actingAs($admin)->get(route('users.show', $alheia))->assertNotFound();
        $this->actingAs($admin)->delete(route('users.destroy', $alheia))->assertNotFound();
    }

    public function test_supervisor_ve_a_folha_mas_excluir_e_criar_papel_sao_de_administrador(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $alvo = $this->conta($empresa, 'employee', 'func@test.local');

        $this->actingAs($supervisora)->get(route('users.index'))->assertOk();
        $this->actingAs($supervisora)->get(route('users.create'))->assertOk();
        $this->actingAs($supervisora)->delete(route('users.destroy', $alvo))->assertForbidden();
        $this->actingAs($supervisora)->get(route('roles.create'))->assertForbidden();
    }

    public function test_ninguem_concede_papel_com_mais_alcance_que_o_seu(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $administrador = $this->papel($empresa, 'administrator');

        $this->actingAs($supervisora)->post(route('users.store'), $this->dados([
            'email' => 'invasora@test.local',
            'papeis' => [$administrador->id],
        ]))->assertSessionHasErrors('papeis');

        $this->assertDatabaseMissing('users', ['email' => 'invasora@test.local']);
    }

    public function test_supervisor_cria_funcionaria_e_a_folha_fica_na_mao_dele(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $funcionaria = $this->papel($empresa, 'employee');

        $resposta = $this->actingAs($supervisora)->post(route('users.store'), $this->dados([
            'name' => 'Renata Funcionária',
            'email' => 'renata@test.local',
            'papeis' => [$funcionaria->id],
        ]));

        $nova = User::query()->where('email', 'renata@test.local')->firstOrFail();
        $resposta->assertRedirect(route('users.show', $nova));

        $this->assertSame(['Employee'], $nova->roles()->pluck('name')->all());
        $this->assertSame($empresa->id, $nova->company_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conta criada',
            'entity_type' => 'User',
            'entity_id' => (string) $nova->id,
        ]);
    }

    public function test_papel_cliente_sem_carteira_nao_entra_e_carteiraocupada_tambem_nao(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $cliente = $this->papel($empresa, 'client');
        $carteira = $this->cliente($empresa, 'Padaria Central');

        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'semcarteira@test.local',
            'papeis' => [$cliente->id],
        ]))->assertSessionHas('erro', fn (?string $m): bool => str_contains((string) $m, 'precisa apontar para a carteira'));

        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'primeiro@test.local',
            'papeis' => [$cliente->id],
            'cliente' => $carteira->id,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'segundo@test.local',
            'papeis' => [$cliente->id],
            'cliente' => $carteira->id,
        ]))->assertSessionHas('erro', fn (?string $m): bool => str_contains((string) $m, 'já tem uma conta de acesso'));

        $link = User::query()->where('email', 'primeiro@test.local')->firstOrFail();
        $this->assertSame($carteira->id, $link->client_id);
    }

    public function test_carteira_fora_do_tenant_e_cola_de_papel_fora_do_alcance_sao_recusadas(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');
        $carteiraAlheia = $this->cliente($vizinha, 'Mercado do Seu Bento');
        $papelAlheio = $this->papel($vizinha, 'employee');

        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'tortular@test.local',
            'papeis' => [$papelAlheio->id],
            'cliente' => $carteiraAlheia->id,
        ]))->assertSessionHasErrors(['papeis', 'cliente']);

        // Funcionário não é papel de carteira: o vínculo é só do Cliente.
        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'carteiraerrada@test.local',
            'papeis' => [$this->papel($empresa, 'employee')->id],
            'cliente' => $this->cliente($empresa, 'Quitanda Nova')->id,
        ]))->assertSessionHas('erro', fn (?string $m): bool => str_contains((string) $m, 'Só a conta do papel Cliente'));

        $this->assertDatabaseMissing('users', ['email' => 'carteiraerrada@test.local']);
    }

    public function test_o_plano_fecha_a_porta_da_cota_de_contas(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        // Duas contas vestem o MESMO papel — a cota conta contas, não papéis.
        $funcionaria = $this->papel($empresa, 'employee');
        $this->contaComPapel($empresa, $funcionaria, 'func@test.local');
        $this->contaComPapel($empresa, $funcionaria, 'outra@test.local');

        $plano = $empresa->plan;
        $plano->forceFill(['max_users' => 3])->save();

        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'foradacota@test.local',
            'papeis' => [$this->papel($empresa, 'employee')->id],
        ]))->assertSessionHas('erro', fn (?string $mensagem): bool => str_contains((string) $mensagem, 'permite 3 contas'));

        $this->assertDatabaseMissing('users', ['email' => 'foradacota@test.local']);
    }

    public function test_a_raiz_nao_se_edita_nem_se_exclui_por_tela(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $raiz = $this->conta($empresa, 'employee', 'raiz@test.local');
        $raiz->forceFill(['is_root' => true])->save();

        $this->actingAs($admin)->get(route('users.edit', $raiz))->assertForbidden();
        $this->actingAs($admin)->put(route('users.update', $raiz), $this->dados([
            'email' => 'raiz@test.local',
            'papeis' => [$this->papel($empresa, 'employee')->id],
        ]))->assertForbidden();
        $this->actingAs($admin)->delete(route('users.destroy', $raiz))->assertForbidden();

        $this->assertNotNull($raiz->fresh());
    }

    public function test_ninguem_mexe_em_conta_que_enxerga_mais_que_voce(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');

        $this->actingAs($supervisora)->get(route('users.edit', $admin))->assertForbidden();
        // E o supervisor alcança a funcionária, que enxerga menos que ele.
        $funcionaria = $this->conta($empresa, 'employee', 'func@test.local');
        $this->actingAs($supervisora)->get(route('users.edit', $funcionaria))->assertOk();
    }

    public function test_a_propria_conta_nao_se_desliga_nem_se_rebaixa(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->put(route('users.update', $admin), $this->dados([
            'email' => $admin->email,
            'papeis' => [$this->papel($empresa, 'administrator')->id],
            'status' => 'inactive',
        ]))->assertSessionHas('erro');

        $this->assertSame('active', $admin->fresh()->status);

        $funcionaria = $this->papel($empresa, 'employee');
        $this->actingAs($admin)->put(route('users.update', $admin), $this->dados([
            'email' => $admin->email,
            'papeis' => [$funcionaria->id],
        ]))->assertSessionHas('erro', fn (?string $mensagem): bool => str_contains((string) $mensagem, 'administrador'));

        $this->assertTrue($admin->fresh()->hasRole('administrator'));
    }

    public function test_excluir_e_macio_e_a_ficha_de_técnico_segura_a_porta(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $alvo = $this->conta($empresa, 'employee', 'func@test.local');

        TenantContext::set($empresa->id);
        $ficha = Technician::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Ricardo Prado',
            'status' => 'available',
            'user_id' => $alvo->id,
        ]);
        TenantContext::forget();

        $this->actingAs($admin)->delete(route('users.destroy', $alvo))->assertSessionHas('erro');
        $this->assertNotNull($alvo->fresh());

        $ficha->update(['user_id' => null]);
        $this->actingAs($admin)->delete(route('users.destroy', $alvo))->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
        $this->actingAs($admin)->get(route('users.index'))->assertDontSee('func@test.local');
    }

    public function test_senha_fraca_situacao_inventada_e_email_alheio_nao_passam(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $funcionaria = $this->papel($empresa, 'employee');

        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'senhafraque@test.local',
            'password' => 'curta1',
            'password_confirmation' => 'curta1',
            'papeis' => [$funcionaria->id],
        ]))->assertSessionHasErrors('password');

        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'estadoerrado@test.local',
            'papeis' => [$funcionaria->id],
            'status' => 'desativado',
        ]))->assertSessionHasErrors('status');

        // E-mail é único no sistema inteiro, não por empresa.
        $this->actingAs($admin)->post(route('users.store'), $this->dados([
            'email' => 'a@test.local',
            'papeis' => [$funcionaria->id],
        ]))->assertSessionHasErrors('email');
    }

    /** @return array<string, mixed> */
    private function dados(array $extras = []): array
    {
        static $sequencia = 0;
        $sequencia += 1;

        return $extras + [
            'name' => 'Conta '.$sequencia,
            'email' => "conta{$sequencia}@test.local",
            'password' => 'Senha-Forte-123',
            'password_confirmation' => 'Senha-Forte-123',
            'status' => 'active',
            'papeis' => [],
        ];
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

    /** Conta nova vestindo um papel que já existe — sem criar papel repetido. */
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

        return $usuario;
    }

    private function papel(Company $empresa, string $slug): Role
    {
        $existente = Role::query()->where('company_id', $empresa->id)->where('slug', $slug)->first();

        if ($existente !== null) {
            return $existente;
        }

        return $this->makeRole($slug, $empresa);
    }

    private function cliente(Company $empresa, string $nome): Client
    {
        TenantContext::set($empresa->id);
        $cliente = Client::query()->create(['name' => $nome, 'status' => 'active']);
        TenantContext::forget();

        return $cliente;
    }
}
