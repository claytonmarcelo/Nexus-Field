<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\ResolveCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveCompany::class,
        ]);

        // O tenant tem de existir antes de a rota trocar o parâmetro por modelo:
        // sem essa ordem o CompanyScope ainda está cego durante o SubstituteBindings
        // e a ficha de um registro de outra empresa abre normal.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveCompany::class,
        );

        $middleware->alias([
            'permission' => EnsurePermission::class,
            'company' => ResolveCompany::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
