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

    /**
     * Rótulo em português de cada módulo. A matriz de permissões do papel
     * desenha daqui: catálogo em inglês na interface de um sistema em
     * português é classe morta com crachá.
     *
     * @var array<string, string>
     */
    public const ROTULOS_MODULOS = [
        'dashboard' => 'Painel',
        'clients' => 'Clientes',
        'technicians' => 'Técnicos',
        'teams' => 'Equipes',
        'services' => 'Serviços',
        'products' => 'Produtos',
        'stock' => 'Estoque',
        'orders' => 'Ordens de serviço',
        'tickets' => 'Chamados',
        'agenda' => 'Agenda',
        'financial' => 'Financeiro',
        'reports' => 'Relatórios',
        'notifications' => 'Notificações',
        'users' => 'Usuários',
        'roles' => 'Papéis',
        'settings' => 'Configurações',
        'audit' => 'Auditoria',
    ];

    /**
     * O verbo do catálogo traduzido para a tela. `execute` na ordem é
     * "Executar", `move` no estoque é "Movimentar" — a coluna esquerda da
     * matriz é a mesma em qualquer papel.
     *
     * @var array<string, string>
     */
    public const ROTULOS_ACOES = [
        'view' => 'Ver',
        'create' => 'Criar',
        'update' => 'Editar',
        'delete' => 'Excluir',
        'export' => 'Exportar',
        'approve' => 'Aprovar',
        'execute' => 'Executar',
        'close' => 'Fechar',
        'move' => 'Movimentar',
        'adjust' => 'Ajustar',
        'manage' => 'Gerenciar',
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

    public static function moduloRotulo(string $modulo): string
    {
        return static::ROTULOS_MODULOS[$modulo] ?? $modulo;
    }

    public static function acaoRotulo(string $acao): string
    {
        return static::ROTULOS_ACOES[$acao] ?? $acao;
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
                'orders.view', 'orders.create', 'orders.update', 'orders.approve', 'orders.execute', 'orders.export',
                'tickets.view', 'tickets.create', 'tickets.update', 'tickets.execute', 'tickets.close',
                'tickets.export',
                'agenda.view', 'agenda.create', 'agenda.update', 'agenda.delete',
                'financial.view', 'financial.create', 'financial.update', 'financial.approve', 'financial.export',
                'reports.view', 'reports.export',
                'notifications.view', 'notifications.manage',
                // A Fase 20 entrega ao supervisor a gerência do time que ele
                // alcança: ver a folha, criar e editar quem enxerga menos que
                // ele, e ler os papéis. Excluir conta e desenhar papel continuam
                // degrau de administrador — e o alcance, não a rota, é que fecha
                // a mão dele no formulário.
                'users.view', 'users.create', 'users.update',
                'roles.view',
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
