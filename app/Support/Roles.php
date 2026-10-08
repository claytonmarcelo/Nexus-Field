<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Role;

/**
 * Papéis de sistema de uma empresa. Os papéis são por tenant, então semear uma
 * empresa nova — a real ou a de demonstração — precisa do mesmo conjunto, com as
 * mesmas permissões do catálogo. Um método só evita que dois seeders divirjam.
 */
class Roles
{
    public const SLUGS = ['administrator', 'supervisor', 'employee', 'technician', 'client'];

    /**
     * Rótulo em português de cada papel. `Str::headline($slug)` desenhava
     * "Administrator" e "Employee" numa interface em português.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'administrator' => 'Administrador',
        'supervisor' => 'Supervisor / Gestor',
        'employee' => 'Funcionário',
        'technician' => 'Técnico',
        'client' => 'Cliente',
    ];

    /** @param array<string, int> $permissions slug de permissão => id @return array<string, Role> */
    public static function provision(Company $company, array $permissions): array
    {
        $criados = [];

        foreach (self::SLUGS as $slug) {
            $role = Role::query()->firstOrNew(['company_id' => $company->id, 'slug' => $slug]);
            $role->name = self::LABELS[$slug];
            $role->is_system = true;
            $role->save();

            $concedidas = PermissionCatalog::byRole($slug);
            $ids = $concedidas === ['*']
                ? array_values($permissions)
                : array_values(array_intersect_key($permissions, array_flip($concedidas)));

            $role->permissions()->sync($ids);
            $criados[$slug] = $role;
        }

        return $criados;
    }
}
