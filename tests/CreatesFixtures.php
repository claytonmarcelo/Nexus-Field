<?php

namespace Tests;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;

trait CreatesFixtures
{
    protected function makeCompany(string $slug = 'empresa-teste'): Company
    {
        $plan = Plan::create([
            'name' => 'Plano '.$slug,
            'slug' => 'teste-'.$slug,
            'max_users' => 10,
            'max_technicians' => 10,
            'max_clients' => 100,
            'max_service_orders_per_month' => 100,
            'price_monthly' => 0,
            'is_active' => true,
        ]);

        return Company::create([
            'name' => 'Empresa '.$slug,
            'slug' => $slug,
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'status' => 'active',
        ]);
    }

    protected function seedPermissions(): void
    {
        foreach (PermissionCatalog::all() as $slug => $meta) {
            Permission::create([
                'slug' => $slug,
                'module' => $meta['module'],
                'action' => $meta['action'],
            ]);
        }
    }

    protected function makeRole(string $slug, Company $company): Role
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'is_system' => true,
        ]);

        $granted = PermissionCatalog::byRole($slug);
        $ids = $granted === ['*']
            ? Permission::pluck('id')->all()
            : Permission::whereIn('slug', $granted)->pluck('id')->all();

        $role->permissions()->attach($ids);

        return $role;
    }

    protected function makeUser(string $roleSlug, Company $company, string $email = 'user@test.local'): User
    {
        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Usuário '.$roleSlug,
            'email' => $email,
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);

        $user->roles()->attach($this->makeRole($roleSlug, $company)->id);

        return $user->fresh('roles.permissions');
    }
}
