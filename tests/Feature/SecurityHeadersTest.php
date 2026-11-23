<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_toda_resposta_web_tem_os_cabecalhos_de_endurecimento(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_resposta_https_tem_hsts(): void
    {
        // Em produção o TLS é encerrado no proxy; configure explicitamente o
        // proxy confiável para que o Laravel veja a requisição como HTTPS.
        TrustProxies::at(['127.0.0.1']);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->setTrustedProxies(
            ['127.0.0.1'],
            Request::HEADER_X_FORWARDED_PROTO
        );

        $response = (new SecurityHeaders())->handle(
            $request,
            fn () => new Response()
        );

        $this->assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        Request::setTrustedProxies([], 0);

        parent::tearDown();
    }

    public function test_referer_externo_nao_vira_destino_de_redirect(): void
    {
        $this->post('/login', [], ['Referer' => 'https://evil.example/phishing'])
            ->assertRedirect(url('/'))
            ->assertSessionHasErrors('email');
    }

    public function test_referer_da_mesma_origem_ainda_serve_de_destino(): void
    {
        $origem = url('/login');

        $this->post('/login', [], ['Referer' => $origem])
            ->assertRedirect($origem)
            ->assertSessionHasErrors('email');
    }
}
