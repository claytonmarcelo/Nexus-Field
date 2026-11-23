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
            ->assertSee('Gestão de campo sem planilhas paralelas')
            ->assertSee('Um sistema, ponta a ponta')
            ->assertSee('© 2026 NEXUS-FIELD')
            ->assertDontSee('Desenvolvido por Clayton Marcelo');
    }

    public function test_as_telas_de_acesso_abrem_para_convidado(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Seja bem‑vindo');
        $this->get(route('password.request'))->assertOk()->assertSee('Recuperar senha');

        $user = $this->makeUser('administrator', $this->makeCompany('tela-acesso'), 'tela@test.local');
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Definir nova senha')
            ->assertSee($user->email);
    }

    public function test_a_entrada_nao_devolve_credencial_preenchida_depois_do_logout(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<form\b[^>]*action="'.preg_quote(route('login'), '/').'"[^>]*>/', $html, $formulario);
        $this->assertNotSame([], $formulario);
        $this->assertStringContainsString('autocomplete="off"', $formulario[0]);

        preg_match('/<input\b[^>]*name="email"[^>]*>/', $html, $email);
        $this->assertNotSame([], $email);
        $this->assertStringContainsString('autocomplete="off"', $email[0]);
        $this->assertStringContainsString('value=""', $email[0]);

        preg_match('/<input\b[^>]*name="password"[^>]*>/', $html, $senha);
        $this->assertNotSame([], $senha);
        $this->assertStringContainsString('autocomplete="off"', $senha[0]);
        $this->assertStringNotContainsString('value=', $senha[0]);
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
