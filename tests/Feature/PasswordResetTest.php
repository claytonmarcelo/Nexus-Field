<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\CreatesFixtures;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private function makeUser(string $email): User
    {
        $company = $this->makeCompany();

        return User::create([
            'company_id' => $company->id,
            'name' => 'Usuário Reset',
            'email' => $email,
            'password' => 'Senha-Anterior-123',
            'status' => 'active',
        ]);
    }

    public function test_solicitacao_de_redefinicao_guarda_um_token_valido(): void
    {
        $user = $this->makeUser('reset@test.local');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_email_desconhecido_nao_dispara_link(): void
    {
        $this->post(route('password.email'), ['email' => 'ninguem@test.local'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_token_valido_troca_a_senha_e_consome_o_token(): void
    {
        $user = $this->makeUser('reset2@test.local');
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Senha-Nova-Forte-123',
            'password_confirmation' => 'Senha-Nova-Forte-123',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Senha-Nova-Forte-123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_token_invalido_e_rejeitado(): void
    {
        $user = $this->makeUser('reset3@test.local');

        $this->post(route('password.update'), [
            'token' => 'token-forjado',
            'email' => $user->email,
            'password' => 'Senha-Nova-Forte-123',
            'password_confirmation' => 'Senha-Nova-Forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_senha_fraca_ou_nao_confirmada_e_rejeitada(): void
    {
        $user = $this->makeUser('reset4@test.local');
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Senha-Nova-Forte-123',
            'password_confirmation' => 'Outra-Confirmacao-123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(password_verify('Senha-Anterior-123', $user->fresh()->password));
    }
}
