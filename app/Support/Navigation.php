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
                    [
                        'label' => 'Ordens de serviço',
                        'route' => 'orders.index',
                        'permission' => 'orders.view',
                        'icon' => 'fa-solid fa-clipboard-list',
                    ],
                    [
                        'label' => 'Chamados',
                        'route' => 'tickets.index',
                        'permission' => 'tickets.view',
                        'icon' => 'fa-solid fa-headset',
                    ],
                    [
                        'label' => 'Agenda',
                        'route' => 'agenda.index',
                        'permission' => 'agenda.view',
                        'icon' => 'fa-solid fa-calendar-days',
                    ],
                    [
                        'label' => 'Visitas de campo',
                        'route' => 'checkins.index',
                        'permission' => 'orders.view',
                        'icon' => 'fa-solid fa-location-crossing',
                    ],
                    [
                        'label' => 'Estoque',
                        'route' => 'movements.index',
                        'permission' => 'stock.view',
                        'icon' => 'fa-solid fa-right-left',
                    ],
                    [
                        'label' => 'Financeiro',
                        'route' => 'financial.index',
                        'permission' => 'financial.view',
                        'icon' => 'fa-solid fa-sack-dollar',
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
                    [
                        'label' => 'Técnicos',
                        'route' => 'technicians.index',
                        'permission' => 'technicians.view',
                        'icon' => 'fa-solid fa-user-gear',
                    ],
                    [
                        'label' => 'Equipes',
                        'route' => 'teams.index',
                        'permission' => 'teams.view',
                        'icon' => 'fa-solid fa-users-rectangle',
                    ],
                    [
                        'label' => 'Especialidades',
                        'route' => 'specialties.index',
                        'permission' => 'technicians.view',
                        'icon' => 'fa-solid fa-certificate',
                    ],
                ],
            ],
            [
                'label' => 'Catálogo',
                'items' => [
                    [
                        'label' => 'Serviços',
                        'route' => 'services.index',
                        'permission' => 'services.view',
                        'icon' => 'fa-solid fa-screwdriver-wrench',
                    ],
                    [
                        'label' => 'Produtos',
                        'route' => 'products.index',
                        'permission' => 'products.view',
                        'icon' => 'fa-solid fa-boxes-stacked',
                    ],
                    [
                        'label' => 'Categorias de serviço',
                        'route' => 'categories.index',
                        'permission' => 'services.view',
                        'icon' => 'fa-solid fa-layer-group',
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
