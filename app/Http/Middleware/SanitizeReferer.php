<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `back()` e `url()->previous()` leem o `Referer` da requisição. Sem esta
 * barreira, um link externo que chega com `Referer` forjado transforma um
 * "erro de formulário" num redirecionamento para fora da aplicação.
 *
 * A regra é origem, não apenas host: http e https (ou portas diferentes) não
 * contam como a mesma origem. O cabeçalho externo é removido — o Laravel cai
 * de volta na URL anterior da sessão, que é gravada pelo próprio servidor.
 */
class SanitizeReferer
{
    public function handle(Request $request, Closure $next): Response
    {
        $referer = $request->headers->get('referer');

        if ($referer === null || $referer === '') {
            return $next($request);
        }

        $origemAtual = $this->origem($request->getSchemeAndHttpHost());
        $origemReferer = $this->origem($referer);

        if ($origemAtual === null || $origemReferer === null || ! hash_equals($origemAtual, $origemReferer)) {
            $request->headers->remove('referer');
        }

        return $next($request);
    }

    /** Devolve `esquema://host:porta` normalizado, ou null para URL relativa/inválida. */
    private function origem(string $url): ?string
    {
        $partes = parse_url($url);

        if ($partes === false || ! isset($partes['scheme'], $partes['host'])) {
            return null;
        }

        $esquema = strtolower($partes['scheme']);

        if (! in_array($esquema, ['http', 'https'], true)) {
            return null;
        }

        $porta = $partes['port'] ?? ($esquema === 'https' ? 443 : 80);

        return $esquema.'://'.strtolower($partes['host']).':'.$porta;
    }
}
