<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use App\Services\BackfillOrganizacional;
use App\Services\Recusa;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cadastro de acesso de site comum.
 *
 * A conta não nasce órfã: `users.company_id` é obrigatório e o login exige uma
 * empresa ativa. Por isso o registro cria um ambiente inicial da pessoa, com
 * papéis e estrutura mínima iguais aos de qualquer empresa. Depois, em
 * Cadastros › Empresas, ela envia o cadastro da empresa definitiva; a aprovação
 * reaproveita esta conta em vez de criar um e-mail duplicado.
 */
final class RegistradorDeAcesso
{
    /** @param array<string, mixed> $dados */
    public function registrar(array $dados): User
    {
        $email = mb_strtolower(trim((string) $dados['email']));

        if (User::withTrashed()->where('email', $email)->exists()) {
            throw new Recusa('Este e-mail já possui uma conta na plataforma.');
        }

        return DB::transaction(function () use ($dados, $email): User {
            $empresa = Company::create([
                'name' => 'Ambiente de '.(string) $dados['name'],
                'slug' => $this->slugLivre((string) $dados['name']),
                'email' => $email,
                'plan_id' => $this->planoInicial()->id,
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(30),
                'status' => 'active',
                'lifecycle_status' => 'active',
            ]);

            $papeis = Roles::provision(
                $empresa,
                Permission::query()->pluck('id', 'slug')->all(),
            );

            $usuario = User::create([
                'company_id' => $empresa->id,
                'name' => trim((string) $dados['name']),
                'email' => $email,
                'password' => (string) $dados['password'],
                'status' => 'active',
            ]);
            $usuario->forceFill(['email_verified_at' => now()])->save();
            $usuario->roles()->sync([$papeis['administrator']->id]);

            $totais = ['empresas' => 0, 'filiais' => 0, 'setores' => 0, 'cargos' => 0, 'vinculos' => 0, 'alcances' => 0];
            (new BackfillOrganizacional)->paraEmpresa($empresa, $totais);

            return $usuario;
        });
    }

    private function planoInicial(): Plan
    {
        return Plan::query()->firstOrCreate(
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
    }

    private function slugLivre(string $nome): string
    {
        $base = Str::slug($nome);
        $slug = $base !== '' ? $base : 'conta';
        $sufixo = 2;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$sufixo++;
        }

        return $slug;
    }
}
