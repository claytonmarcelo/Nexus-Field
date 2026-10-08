<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Telas abertas de acesso: o que o convidado recebe, o que o idioma entrega e
 * para onde quem já entrou é desviado.
 */
class AccessScreenTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_pagina_de_apresentacao_abre_para_convidado(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('A operação externa inteira em um só lugar')
            ->assertSee('Gestão de operações em campo');
    }

    public function test_as_telas_de_acesso_abrem_para_convidado(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Entrar na plataforma');
        $this->get(route('password.request'))->assertOk()->assertSee('Recuperar acesso');

        $user = $this->makeUser('administrator', $this->makeCompany('tela-acesso'), 'tela@test.local');
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Definir nova senha')
            ->assertSee($user->email);
    }

    public function test_usuario_autenticado_nao_ve_o_formulario_de_entrada(): void
    {
        $user = $this->makeUser('administrator', $this->makeCompany('ja-entr'), 'dentro@test.local');

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_erro_de_campo_obrigatorio_fala_portugues(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password']);

        $mensagens = session('errors')->all();

        $this->assertContains('O campo e-mail é obrigatório.', $mensagens);
        $this->assertContains('O campo senha é obrigatório.', $mensagens);
    }

    public function test_regra_de_senha_curta_fala_portugues(): void
    {
        $user = $this->makeUser('administrator', $this->makeCompany('senha-curta'), 'curta@test.local');

        $this->post(route('password.update'), [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
            'password' => 'abc12',
            'password_confirmation' => 'abc12',
        ])->assertSessionHasErrors('password');

        $this->assertSame(
            'O campo senha deve ter pelo menos 10 caracteres.',
            session('errors')->first('password')
        );
    }

    public function test_aviso_de_link_enviado_fala_portugues(): void
    {
        $user = $this->makeUser('administrator', $this->makeCompany('link-enviado'), 'link@test.local');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', 'Enviamos o link de redefinição para o seu e-mail.');
    }
}
