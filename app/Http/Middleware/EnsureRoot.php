<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta das ações que alcançam a aplicação inteira, não uma empresa.
 *
 * Limpar cache compilado, rotas ou configuração não é ajuste de tenant: o
 * arquivo é compartilhado por todas as empresas da instalação. Por isso o
 * administrador de uma empresa — mesmo com `*` no catálogo dela — não passa
 * aqui. A raiz é a única identidade que representa o sistema, e a checagem
 * fica no servidor porque esconder o botão no Blade seria só higiene visual.
 */
class EnsureRoot
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isRoot() !== true) {
            abort(403, 'Somente a conta raiz pode executar esta ação global.');
        }

        return $next($request);
    }
}
