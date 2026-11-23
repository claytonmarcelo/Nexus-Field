<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();
        Request::setTrustedProxies([], 0);

        parent::tearDown();
    }

    public function test_x_forwarded_for_forjado_nao_burla_o_throttle_de_login(): void
    {
        $statuses = [];

        foreach (range(1, 12) as $tentativa) {
            $statuses[] = $this->withHeader('X-Forwarded-For', "203.0.113.{$tentativa}")
                ->post('/login', [
                    'email' => 'inexistente@exemplo.test',
                    'password' => 'senha-errada',
                ])
                ->status();
        }

        $this->assertSame(429, $statuses[10], 'A 11ª tentativa deve ser bloqueada pelo throttle.');
        $this->assertSame(429, $statuses[11], 'A 12ª tentativa deve continuar bloqueada com outro IP forjado.');
    }

    public function test_proxy_configurado_tem_o_ip_do_cabecalho_aceito(): void
    {
        // O middleware do bootstrap já rodou; configuramos o proxy confiável
        // antes de processar um novo Request, exatamente como em produção.
        TrustProxies::at(['127.0.0.1']);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $request->headers->set('X-Forwarded-For', '203.0.113.77');

        (new TrustProxies())->handle($request, fn () => new Response());

        $this->assertSame('203.0.113.77', $request->ip());
    }

    public function test_configuracao_recusa_curinga_em_trusted_proxies(): void
    {
        $anteriorEnv = $_ENV['TRUSTED_PROXIES'] ?? null;
        $anteriorServer = $_SERVER['TRUSTED_PROXIES'] ?? null;

        $_ENV['TRUSTED_PROXIES'] = '*';
        $_SERVER['TRUSTED_PROXIES'] = '*';
        putenv('TRUSTED_PROXIES=*');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TRUSTED_PROXIES=* não é permitido');

        try {
            require __DIR__.'/../../config/deploy.php';
        } finally {
            putenv('TRUSTED_PROXIES');

            if ($anteriorEnv === null) {
                unset($_ENV['TRUSTED_PROXIES']);
            } else {
                $_ENV['TRUSTED_PROXIES'] = $anteriorEnv;
            }

            if ($anteriorServer === null) {
                unset($_SERVER['TRUSTED_PROXIES']);
            } else {
                $_SERVER['TRUSTED_PROXIES'] = $anteriorServer;
            }
        }
    }
}
