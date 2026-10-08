<?php

namespace App\Support;

/**
 * Catálogo único de permissões. Módulos e ações ficam aqui para que seeders,
 * policies e menu usem exatamente as mesmas strings, sem variação por página.
 */
class PermissionCatalog
{
    /** @var array<string, array<int, string>> */
    public const MODULES = [
        'dashboard' => ['view'],
        'clients' => ['view', 'create', 'update', 'delete', 'export'],
        'technicians' => ['view', 'create', 'update', 'delete'],
        'teams' => ['view', 'create', 'update', 'delete'],
        'services' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete'],
        'stock' => ['view', 'move', 'adjust', 'export'],
        'orders' => ['view', 'create', 'update', 'delete', 'approve', 'execute', 'export'],
        'tickets' => ['view', 'create', 'update', 'execute', 'close', 'export'],
        'agenda' => ['view', 'create', 'update', 'delete'],
        'financial' => ['view', 'create', 'update', 'delete', 'approve', 'export'],
        'reports' => ['view', 'export'],
        'notifications' => ['view', 'manage'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'settings' => ['view', 'manage'],
        'audit' => ['view', 'export'],
    ];

    /** @return array<string, array{module: string, action: string}> */
    public static function all(): array
    {
        $permissions = [];

        foreach (static::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                $permissions["{$module}.{$action}"] = [
                    'module' => $module,
                    'action' => $action,
                ];
            }
        }

        return $permissions;
    }

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(static::all());
    }

    /** @return array<string, array<int, string>> */
    public static function byRole(string $role): array
    {
        return match ($role) {
            'administrator' => ['*'],
            'supervisor' => [
                'dashboard.view',
                'clients.view', 'clients.create', 'clients.update', 'clients.export',
                'technicians.view', 'technicians.create', 'technicians.update',
                'teams.view', 'teams.create', 'teams.update',
                'services.view', 'services.create', 'services.update',
                'products.view', 'products.create', 'products.update',
                'stock.view', 'stock.move', 'stock.adjust', 'stock.export',
                'orders.view', 'orders.create', 'orders.update', 'orders.approve', 'orders.export',
                'tickets.view', 'tickets.create', 'tickets.update', 'tickets.execute', 'tickets.close',
                'tickets.export',
                'agenda.view', 'agenda.create', 'agenda.update', 'agenda.delete',
                'financial.view', 'financial.create', 'financial.update', 'financial.approve', 'financial.export',
                'reports.view', 'reports.export',
                'notifications.view', 'notifications.manage',
                'users.view',
                'settings.view',
                'audit.view', 'audit.export',
            ],
            'employee' => [
                'dashboard.view',
                'clients.view', 'clients.create', 'clients.update',
                'technicians.view',
                'services.view',
                'products.view',
                'stock.view', 'stock.move',
                'orders.view', 'orders.create', 'orders.update', 'orders.execute',
                'tickets.view', 'tickets.create', 'tickets.update', 'tickets.execute',
                'agenda.view', 'agenda.create', 'agenda.update',
                'financial.view', 'financial.create',
                'reports.view',
                'notifications.view',
            ],
            'technician' => [
                'dashboard.view',
                'clients.view',
                'orders.view', 'orders.update', 'orders.execute',
                'tickets.view', 'tickets.create', 'tickets.execute',
                'agenda.view',
                'stock.view', 'stock.move',
                'notifications.view',
            ],
            'client' => [
                'dashboard.view',
                'orders.view',
                'tickets.view', 'tickets.create',
                'notifications.view',
            ],
            default => [],
        };
    }
}
