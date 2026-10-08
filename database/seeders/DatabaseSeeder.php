<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $company = $this->seedCompany();
        $roles = $this->seedRoles($company, $permissions);
        $this->seedAdministrator($company, $roles);
    }

    private function seedPermissions(): array
    {
        foreach (PermissionCatalog::all() as $slug => $meta) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['module' => $meta['module'], 'action' => $meta['action']],
            );
        }

        return Permission::query()->pluck('id', 'slug')->all();
    }

    private function seedCompany(): Company
    {
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'operacao'],
            [
                'name' => 'Operação',
                'max_users' => 25,
                'max_technicians' => 25,
                'max_clients' => 2000,
                'max_service_orders_per_month' => 5000,
                'price_monthly' => 0,
                'features' => ['fsm', 'agenda', 'relatorios'],
                'is_active' => true,
            ],
        );

        return Company::query()->firstOrCreate(
            ['slug' => 'nexusfield'],
            [
                'name' => 'Nexus-Field Operação',
                'plan_id' => $plan->id,
                'email' => 'operacao@nexusfield.local',
                'subscription_status' => 'active',
                'status' => 'active',
            ],
        );
    }

    private function seedRoles(Company $company, array $permissions): array
    {
        $created = [];

        foreach (['administrator', 'supervisor', 'employee', 'technician', 'client'] as $slug) {
            $role = Role::query()->firstOrNew(['company_id' => $company->id, 'slug' => $slug]);
            $role->name = Str::headline($slug);
            $role->is_system = true;
            $role->save();

            $granted = PermissionCatalog::byRole($slug);
            $ids = $granted === ['*']
                ? array_values($permissions)
                : array_values(array_intersect_key($permissions, array_flip($granted)));

            $role->permissions()->sync($ids);
            $created[$slug] = $role;
        }

        return $created;
    }

    private function seedAdministrator(Company $company, array $roles): void
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@nexusfield.local');

        $password = env('SEED_ADMIN_PASSWORD');

        if (! $password) {
            $password = Str::password(16);

            if (app()->environment('production')) {
                throw new \RuntimeException(
                    'Defina SEED_ADMIN_PASSWORD antes de semear em produção; '.
                    'credencial gerada automaticamente não é auditável.'
                );
            }

            $this->command?->warn("Credenciais do administrador: {$email} / {$password}");
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->company_id = $company->id;
        $user->name = 'Administrador Nexus-Field';
        $user->password = $password;
        $user->status = 'active';
        $user->email_verified_at = now();
        $user->save();

        $user->roles()->syncWithoutDetaching([$roles['administrator']->id]);
    }
}
