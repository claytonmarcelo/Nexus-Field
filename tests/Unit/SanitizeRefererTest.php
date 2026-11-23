<?php

namespace Tests\Unit;

use App\Http\Middleware\SanitizeReferer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SanitizeRefererTest extends TestCase
{
    private function refererVistoPeloControlador(string $referer, string $url = 'http://localhost/login'): ?string
    {
        $request = Request::create($url, 'POST', [], [], [], ['HTTP_REFERER' => $referer]);
        $visto = null;

        (new SanitizeReferer())->handle($request, function (Request $req) use (&$visto): Response {
            $visto = $req->headers->get('referer');

            return new Response('', Response::HTTP_NO_CONTENT);
        });

        return $visto;
    }

    public function test_referer_da_mesma_origem_e_mantido(): void
    {
        $this->assertSame('http://localhost/dashboard', $this->refererVistoPeloControlador('http://localhost/dashboard'));
        $this->assertSame('https://localhost:8443/dashboard', $this->refererVistoPeloControlador(
            'https://localhost:8443/dashboard',
            'https://localhost:8443/login',
        ));
    }

    public function test_referer_de_outra_origem_e_removido(): void
    {
        $this->assertNull($this->refererVistoPeloControlador('https://evil.example/phishing'));
        $this->assertNull($this->refererVistoPeloControlador('http://localhost:8080/login'));
        $this->assertNull($this->refererVistoPeloControlador('https://localhost/login'));
        $this->assertNull($this->refererVistoPeloControlador('/dashboard'));
    }
}
