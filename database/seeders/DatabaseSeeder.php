<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Conta raiz do sistema: e-mail administrativo permanente e absoluto do projeto,
     * definido pelo dono em 2026-10-08. O valor é identidade, não credencial — a senha
     * continua só no `.env`. `SEED_ADMIN_EMAIL` pode apontar para outro endereço, menos
     * para o endereço aposentado abaixo: esse o seeder recusa.
     */
    public const ROOT_EMAIL = 'nexusfield.admin@gmail.com';

    /**
     * Endereço que foi a conta raiz até 2026-10-08 e saiu do sistema por medida de
     * segurança. Ele não volta nem por `.env`: a migration que o aposentou é
     * irreversível, e o seeder recusa semear raiz nele.
     */
    public const RETIRED_ROOT_EMAIL = 'marcelolimadez@gmail.com';

    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $company = $this->seedCompany();
        $roles = $this->seedRoles($company, $permissions);
        $this->seedRootAccount($company, $roles);
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
        return Roles::provision($company, $permissions);
    }

    private function seedRootAccount(Company $company, array $roles): void
    {
        $email = env('SEED_ADMIN_EMAIL', self::ROOT_EMAIL);

        // O `.env` pode apontar a raiz para outro endereço, mas não para o aposentado:
        // sem esta guarda, um `SEED_ADMIN_EMAIL` velho faria o seeder ressuscitar a
        // identidade que saiu do sistema por medida de segurança.
        if (Str::lower($email) === self::RETIRED_ROOT_EMAIL) {
            throw new \RuntimeException(
                'A conta raiz não pode ser semeada em '.self::RETIRED_ROOT_EMAIL.
                ': esse endereço foi aposentado por medida de segurança. '.
                'Defina SEED_ADMIN_EMAIL com a identidade vigente.'
            );
        }

        $password = env('SEED_ADMIN_PASSWORD');

        if (! $password) {
            $password = Str::password(16);

            if (app()->environment('production')) {
                throw new \RuntimeException(
                    'Defina SEED_ADMIN_PASSWORD antes de semear em produção; '.
                    'credencial gerada automaticamente não é auditável.'
                );
            }

            $this->command?->warn("Credenciais da conta raiz: {$email} / {$password}");
        }

        $usuario = User::query()->firstOrNew(['email' => $email]);
        $usuario->company_id = $company->id;
        $usuario->name = 'Administrador Nexus-Field';
        $usuario->password = $password;
        $usuario->status = 'active';
        $usuario->email_verified_at = now();
        $usuario->save();

        // A bandeira entra num save separado: enquanto ela é falsa o seeder ainda
        // pode arrumar o e-mail da conta, e o `updating` da conta raiz passa a
        // valer a partir daqui — inclusive contra o próprio seeder.
        $usuario->forceFill(['is_root' => true])->save();

        $usuario->roles()->syncWithoutDetaching([$roles['administrator']->id]);
    }
}
