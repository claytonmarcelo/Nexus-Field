<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Mapa do menu lateral. Uma entrada só aparece quando a rota dela existe e o
 * usuário tem a permissão correspondente: link para tela que não existe é
 * funcionalidade falsa, e item que o papel não pode abrir vira um 403.
 */
class Navigation
{
    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, permission: string, icon: string}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'label' => 'Operação',
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'route' => 'dashboard',
                        'permission' => 'dashboard.view',
                        'icon' => 'fa-solid fa-gauge-high',
                    ],
                ],
            ],
            [
                'label' => 'Cadastros',
                'items' => [
                    [
                        'label' => 'Clientes',
                        'route' => 'clients.index',
                        'permission' => 'clients.view',
                        'icon' => 'fa-solid fa-building-user',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, url: string, icon: string, active: bool}>}>
     */
    public static function for(User $user): array
    {
        $visible = [];

        foreach (static::sections() as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (! Route::has($item['route']) || ! $user->hasPermission($item['permission'])) {
                    continue;
                }

                $items[] = [
                    'label' => $item['label'],
                    'url' => route($item['route']),
                    'icon' => $item['icon'],
                    // Uma tela de detalhe continua dentro do módulo: o item do
                    // menu fica aceso em index, show, create e edição.
                    'active' => request()->routeIs($item['route'])
                        || request()->routeIs(static::familia($item['route'])),
                ];
            }

            if ($items !== []) {
                $visible[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $visible;
    }

    /** `clients.index` => `clients.*`; rota sem ponto (dashboard) fica ela mesma. */
    private static function familia(string $rota): string
    {
        return str_contains($rota, '.') ? Str::beforeLast($rota, '.').'.*' : $rota;
    }
}
