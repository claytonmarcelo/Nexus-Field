<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de permissão no servidor. Esconder o botão no Blade não é controle de
 * acesso: a rota precisa recusar sozinha quem não tem a permissão.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Não autenticado.');
        }

        if (! $user->hasAnyPermission($permissions)) {
            abort(403, 'Você não tem permissão para esta ação.');
        }

        return $next($request);
    }
}
