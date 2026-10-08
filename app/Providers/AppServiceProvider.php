<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O catálogo de permissões é a fonte da verdade do projeto (não existem
        // policies arquivo por arquivo). Aqui ele encontra o `@can` do Blade: a
        // tela esconde o botão que o usuário não pode usar. A recusa de verdade
        // continua no middleware `permission:` da rota — esconder botão não é
        // controle de acesso, é apenas higiene de interface.
        Gate::before(function (User $usuario, string $habilidade) {
            if (! str_contains($habilidade, '.')) {
                return null;
            }

            return $usuario->hasPermission($habilidade) ?: null;
        });
    }
}
