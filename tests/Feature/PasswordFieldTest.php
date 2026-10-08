<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Mostrar/ocultar senha.
 *
 * O contrato provado aqui é o do campo no servidor: o `<input>` nasce `password`,
 * o botão que revela é `type="button"` (um clique nunca envia o formulário) e
 * aponta para o campo certo por `aria-controls`. Revelar é JavaScript — sem ele
 * a senha continua escondida, então a tela nunca abre exposta.
 */
class PasswordFieldTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    /**
     * @return array<int, array{button: string, fieldId: string|null, fieldType: string|null, pressed: string|null}>
     */
    private function controlesDeSenha(TestResponse $response): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $response->getContent());
        $xpath = new DOMXPath($dom);

        $controles = [];

        foreach ($xpath->query('//button[@data-nf-password-toggle]') as $botao) {
            $alvo = $botao->getAttribute('aria-controls');
            $campo = $alvo === '' ? null : $xpath->query('//input[@id="' . $alvo . '"]')->item(0);

            $controles[] = [
                'button' => $botao->getAttribute('type'),
                'fieldId' => $alvo ?: null,
                'fieldType' => $campo?->getAttribute('type'),
                'pressed' => $botao->getAttribute('aria-pressed'),
            ];
        }

        return $controles;
    }

    public function test_login_tem_um_botao_de_ver_apontando_para_o_campo_de_senha(): void
    {
        $controles = $this->controlesDeSenha($this->get('/login')->assertOk());

        $this->assertCount(1, $controles);
        $this->assertSame('button', $controles[0]['button'], 'botão de ver não pode ser type=submit');
        $this->assertSame('password', $controles[0]['fieldId']);
        $this->assertSame('password', $controles[0]['fieldType'], 'o campo nasce escondido');
        $this->assertSame('false', $controles[0]['pressed']);
    }

    public function test_campo_de_email_nao_ganha_botao_de_ver(): void
    {
        $controles = $this->controlesDeSenha($this->get('/login')->assertOk());

        $this->assertNotContains('email', array_column($controles, 'fieldId'));
    }

    public function test_senha_digitada_nao_volta_no_html_apos_falha(): void
    {
        $this->from('/login')->post('/login', [
            'email' => 'alguem@test.local',
            'password' => 'Senha-Teste-Nao-ecoar-123',
        ]);

        $html = $this->get('/login')->assertOk()->getContent();

        // `old()` ecoaria a senha na tela: é exatamente o que o componente evita.
        $this->assertStringNotContainsString('Senha-Teste-Nao-ecoar-123', $html);
        $this->assertStringNotContainsString('name="password" value=', $html);
    }

    public function test_redefinicao_tem_um_botao_por_campo_de_senha(): void
    {
        $company = $this->makeCompany();
        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Usuário Reset',
            'email' => 'ver@test.local',
            'password' => 'Senha-Anterior-123',
            'status' => 'active',
        ]);
        $token = Password::broker()->createToken($user);

        $controles = $this->controlesDeSenha($this->get(route('password.reset', $token))->assertOk());

        $this->assertCount(2, $controles);
        $this->assertSame(['password', 'password_confirmation'], array_column($controles, 'fieldId'));
        $this->assertSame(['password', 'password'], array_column($controles, 'fieldType'));
    }

    public function test_recuperacao_de_acesso_nao_tem_senha_para_revelar(): void
    {
        $this->assertEmpty($this->controlesDeSenha($this->get(route('password.request'))->assertOk()));
    }
}
