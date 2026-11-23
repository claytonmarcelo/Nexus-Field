<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de defesa em profundidade. O nginx da Vercel já entrega alguns
 * deles, mas a aplicação não pode depender de configuração de proxy — sempre
 * que respondida diretamente (teste, container, servidor local), sai protegida.
 */
class SecurityHeaders
{
    /** @var array<string, string> */
    private const CABECALHOS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::CABECALHOS as $nome => $valor) {
            $response->headers->set($nome, $valor);
        }

        // HSTS só faz sentido em HTTPS: em HTTP o navegador ignoraria a diretiva,
        // e marcá-la aqui poderia trancar um ambiente local num esquema errado.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
