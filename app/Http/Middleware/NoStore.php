<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nenhuma resposta da web volta do cache do navegador.
 *
 * O sintoma que motivou esta peça era o botão Voltar devolver o formulário de
 * entrada com o que a sessão anterior tinha digitado. Sem `no-store`, o bfcache
 * restaura o DOM antigo mesmo depois de a sessão ter sido invalidada no
 * servidor. A regra entra no grupo `web` inteiro — toda rota autenticada, a
 * entrada, a recuperação e a redefinição — para nenhuma tela com sessão virar
 * resposta guardada.
 */
class NoStore
{
    /** @var array<string, string> */
    private const CABECALHOS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::CABECALHOS as $nome => $valor) {
            $response->headers->set($nome, $valor);
        }

        return $response;
    }
}
