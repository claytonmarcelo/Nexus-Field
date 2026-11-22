<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesFixtures;
use Tests\TestCase;

class RegistroUsuarioTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    /** @return array<string, string> */
    private function dados(string $email = 'cliente@exemplo.local'): array
    {
        return [
            'name' => 'Marina Cliente',
            'email' => $email,
            'password' => 'Senha-Forte-123',
            'password_confirmation' => 'Senha-Forte-123',
        ];
    }

    public function test_cadastro_publico_de_usuario_nao_exibe_cadastro_de_empresa(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Criar conta')
            ->assertSee('Confirmar senha')
            ->assertDontSee('Cadastro de empresa')
            ->assertDontSee('cadastros/empresas', false);
    }

    public function test_cadastro_cria_conta_ambiente_inicial_e_entra_no_painel(): void
    {
        $this->seedPermissions();

        $this->post(route('register.store'), $this->dados())
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertAuthenticated();
        $usuario = User::where('email', 'cliente@exemplo.local')->firstOrFail();

        $this->assertTrue(Hash::check('Senha-Forte-123', $usuario->password));
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertSame('active', $usuario->status);
        $this->assertSame('active', $usuario->company->status);
        $this->assertTrue($usuario->company->isActive());
        $this->assertTrue($usuario->hasRole('administrator'));
        $this->assertTrue($usuario->hasPermission('organization.create'));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_usuario_criado_ve_empresas_no_menu_de_cadastros(): void
    {
        $this->seedPermissions();
        $this->post(route('register.store'), $this->dados());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cadastros')
            ->assertSee('Empresas')
            ->assertSee(route('cadastro.create'), false);
    }

    public function test_login_com_a_conta_recem_criada(): void
    {
        $this->seedPermissions();
        $this->post(route('register.store'), $this->dados());
        $this->post(route('logout'));

        $this->post(route('login'), [
            'email' => 'cliente@exemplo.local',
            'password' => 'Senha-Forte-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::where('email', 'cliente@exemplo.local')->firstOrFail());
    }

    public function test_email_duplicado_e_recusado(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany();
        $this->makeUser('administrator', $empresa, 'cliente@exemplo.local');

        $this->post(route('register.store'), $this->dados())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::withTrashed()->where('email', 'cliente@exemplo.local')->count());
    }

    public function test_senha_fraca_ou_sem_confirmacao_e_recusada(): void
    {
        $this->seedPermissions();
        $dados = $this->dados();
        $dados['password'] = $dados['password_confirmation'] = 'curta';

        $this->post(route('register.store'), $dados)->assertSessionHasErrors('password');

        $dados = $this->dados();
        $dados['password_confirmation'] = 'Outra-Senha-123';

        $this->post(route('register.store'), $dados)->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }
}
