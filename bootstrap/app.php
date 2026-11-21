<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\ResolveCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

// No console, o callback de middleware roda antes do bootstrapper que registra
// o repositório de configuração. O arquivo de config é a única camada autorizada
// a ler env(); aqui apenas consumimos o array que ele devolve.
$deploy = require __DIR__.'/../config/deploy.php';

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($deploy): void {
        $trustedHosts = $deploy['trusted_hosts'];
        $middleware->trustHosts(at: $trustedHosts !== [] ? $trustedHosts : null);

        // A Vercel encerra TLS no edge; sem isso o Laravel acha que a requisição
        // é HTTP e gera links de reset de senha com http://.
        $proxies = $deploy['trusted_proxies'];
        $middleware->trustProxies(
            at: is_string($proxies) && str_contains($proxies, ',')
                ? array_values(array_filter(array_map('trim', explode(',', $proxies))))
                : $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // O cron da Vercel precisa funcionar mesmo durante manutenção.
        $middleware->preventRequestsDuringMaintenance(except: ['cron/schedule']);

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

// Em Container Runtime o diretório da imagem é somente leitura. Logs, views
// compiladas e arquivos temporários passam a viver em /tmp.
if ($storagePath = $deploy['storage_path']) {
    $app->useStoragePath($storagePath);
}

return $app;
