<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use App\Support\UserContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A identidade entra antes do tenant: empresa, papéis e permissões vêm
        // do cache curto e o TenantContext reaproveita a relação já carregada.
        if ($user !== null) {
            UserContext::hydrate($user);
        }

        TenantContext::resolveFromUser($user);

        return $next($request);
    }
}
