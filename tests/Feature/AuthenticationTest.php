<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\CreatesFixtures;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_guest_nao_alcanca_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_com_credenciais_validas_autentica_e_registra_o_acesso(): void
    {
        RateLimiter::clear('tecnico@test.local|127.0.0.1');

        $company = $this->makeCompany();
        $this->seedPermissions();
        $user = $this->makeUser('technician', $company, 'tecnico@test.local');

        $response = $this->post('/login', [
            'email' => 'tecnico@test.local',
            'password' => 'Senha-Forte-123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_senha_errada_nao_autentica(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();
        $this->makeUser('technician', $company, 'tecnico2@test.local');

        $this->from('/login')
            ->post('/login', [
                'email' => 'tecnico2@test.local',
                'password' => 'senha-errada',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_usuario_inativo_e_bloqueado(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();
        $user = $this->makeUser('employee', $company, 'inativo@test.local');
        $user->forceFill(['status' => 'disabled'])->save();

        $this->post('/login', [
            'email' => 'inativo@test.local',
            'password' => 'Senha-Forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_empresa_com_assinatura_expirada_perde_acesso(): void
    {
        $company = $this->makeCompany('expirada');
        $this->seedPermissions();
        $this->makeUser('supervisor', $company, 'gestor@test.local');

        $company->forceFill(['subscription_status' => 'expired'])->save();

        $this->post('/login', [
            'email' => 'gestor@test.local',
            'password' => 'Senha-Forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_quinta_tentativa_seguida_de_bloqueio_por_throttle(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();
        $this->makeUser('technician', $company, 'bruto@test.local');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'bruto@test.local', 'password' => 'errada']);
        }

        $this->post('/login', ['email' => 'bruto@test.local', 'password' => 'Senha-Forte-123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();
        $user = $this->makeUser('administrator', $company, 'admin@test.local');

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_troca_de_sessao_apos_login_impede_session_fixation(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();
        $this->makeUser('technician', $company, 'fixation@test.local');

        $this->session([]);
        $before = session()->getId();

        $this->post('/login', [
            'email' => 'fixation@test.local',
            'password' => 'Senha-Forte-123',
        ]);

        $this->assertNotSame($before, session()->getId());
        $this->assertSame(User::whereEmail('fixation@test.local')->value('id'), auth()->id());
    }
}
