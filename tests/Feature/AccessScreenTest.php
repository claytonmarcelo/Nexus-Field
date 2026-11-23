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
            ->assertSee('Desenvolvido por Clayton Marcelo');
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

    public function test_a_entrada_deslogada_nao_traz_credencial_preenchida(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<form\b[^>]*action="'.preg_quote(route('login'), '/').'"[^>]*>/', $html, $formulario);
        $this->assertNotSame([], $formulario);
        $this->assertStringContainsString('data-nf-login', $formulario[0]);

        preg_match('/<input\b[^>]*name="email"[^>]*>/', $html, $email);
        $this->assertNotSame([], $email);
        $this->assertStringContainsString('autocomplete="username"', $email[0]);
        $this->assertStringContainsString('value=""', $email[0]);

        preg_match('/<input\b[^>]*name="password"[^>]*>/', $html, $senha);
        $this->assertNotSame([], $senha);
        $this->assertStringContainsString('autocomplete="current-password"', $senha[0]);
        $this->assertStringNotContainsString('value=', $senha[0]);

        $semente = env('SEED_ADMIN_EMAIL');

        if (is_string($semente) && $semente !== '') {
            $this->assertStringNotContainsString($semente, $html);
        }
    }

    public function test_depois_do_logout_a_entrada_volta_vazia(): void
    {
        $this->seedPermissions();
        $usuario = $this->makeUser('administrator', $this->makeCompany('saida-limpa'), 'saida@test.local');

        $this->post(route('login'), [
            'email' => $usuario->email,
            'password' => 'Senha-Forte-123',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<input\b[^>]*name="email"[^>]*>/', $html, $email);
        $this->assertStringContainsString('value=""', $email[0]);

        preg_match('/<input\b[^>]*name="password"[^>]*>/', $html, $senha);
        $this->assertStringNotContainsString('value=', $senha[0]);
        $this->assertStringNotContainsString($usuario->email, $html);
    }

    public function test_login_invalido_devolve_apenas_o_email(): void
    {
        $email = 'invalido@test.local';
        $senha = 'Senha-Incorreta-123';

        $this->from(route('login'))
            ->post(route('login'), ['email' => $email, 'password' => $senha])
            ->assertRedirect(route('login'));

        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<input\b[^>]*name="email"[^>]*>/', $html, $campoEmail);
        $this->assertStringContainsString('value="'.$email.'"', $campoEmail[0]);

        preg_match('/<input\b[^>]*name="password"[^>]*>/', $html, $campoSenha);
        $this->assertStringNotContainsString('value=', $campoSenha[0]);
        $this->assertStringNotContainsString($senha, $html);
    }

    public function test_entrada_e_logout_nao_guardam_cache_no_navegador(): void
    {
        $this->seedPermissions();
        $usuario = $this->makeUser('administrator', $this->makeCompany('sem-cache'), 'cache@test.local');

        $entrada = $this->get(route('login'))->assertOk();
        $this->assertSemCache($entrada);

        $this->post(route('login'), [
            'email' => $usuario->email,
            'password' => 'Senha-Forte-123',
        ])->assertRedirect(route('dashboard'));

        $saida = $this->post(route('logout'));
        $this->assertSemCache($saida);
    }

    public function test_todas_as_telas_de_acesso_e_as_rotas_autenticadas_nao_guardam_cache(): void
    {
        $this->seedPermissions();
        $usuario = $this->makeUser('administrator', $this->makeCompany('web-sem-cache'), 'web@test.local');

        $telas = [
            route('welcome'),
            route('login'),
            route('password.request'),
            route('password.reset', [
                'token' => Password::broker()->createToken($usuario),
                'email' => $usuario->email,
            ]),
        ];

        foreach ($telas as $url) {
            $this->assertSemCache($this->get($url)->assertOk());
        }

        $this->actingAs($usuario);

        foreach ([route('dashboard'), route('profile.show')] as $url) {
            $this->assertSemCache($this->get($url)->assertOk());
        }
    }

    public function test_convidado_em_rota_autenticada_e_mandado_para_a_entrada(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_camada_nf_trata_o_bfcache_da_entrada_e_do_painel(): void
    {
        $sessao = file_get_contents(base_path('resources/js/nexusfield/session.js'));
        $semComentario = preg_replace('/\/\*[\s\S]*?\*\//', '', $sessao);

        $this->assertStringContainsString('event.persisted !== true', $sessao);
        $this->assertStringContainsString("document.body?.dataset?.nfSessao === 'autenticada'", $sessao);
        $this->assertStringContainsString('window.location.reload()', $sessao);
        $this->assertStringContainsString("campo.value = ''", $sessao);
        $this->assertStringContainsString('lembrar.checked = false', $sessao);

        foreach (['localStorage', 'sessionStorage', 'document.cookie'] as $guardado) {
            $this->assertStringNotContainsString($guardado, $semComentario,
                'A camada de sessão não pode guardar credencial: '.$guardado);
        }

        $app = file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString("import * as sessao from './nexusfield/session';", $app);
        $this->assertStringContainsString('sessao.init();', $app);
        $this->assertStringNotContainsString('nexusfield/login', $app);
        $this->assertFileDoesNotExist(base_path('resources/js/nexusfield/login.js'));
    }

    /**
     * O Symfony normaliza a ordem das diretivas e ainda acrescenta `private`
     * às respostas com sessão; o contrato é o conteúdo, não a ordem da linha.
     */
    private function assertSemCache($response): void
    {
        $controle = (string) $response->headers->get('Cache-Control');

        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0', 'private'] as $diretiva) {
            $this->assertStringContainsString($diretiva, $controle);
        }

        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
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
