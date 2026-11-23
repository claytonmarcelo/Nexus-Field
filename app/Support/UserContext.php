<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Identidade de leitura da conta autenticada. Papéis, permissões, empresa e
 * plano mudam pouco, mas eram consultados em toda navegação — em banco remoto
 * isso virava várias idas ao banco antes mesmo do conteúdo da página. O cache
 * curto protege essas leituras e o modelo recebe as relações já prontas, sem
 * mudar o que as views e o TenantContext esperam encontrar.
 */
final class UserContext
{
    private const TTL_SECONDS = 60;

    /** Assinatura cancelada precisa barrar o login rápido, sem esperar o TTL cheio. */
    private const COMPANY_TTL_SECONDS = 15;

    private const VERSION_KEY = 'user-context:version';

    /** @var \WeakMap<User, true>|null */
    private static ?\WeakMap $hidratados = null;

    /** Anexa ao modelo tudo o que a sessão precisa para navegar sem ir ao banco. */
    public static function hydrate(User $user): void
    {
        if ($user->relationLoaded('roles') && $user->relationLoaded('company')) {
            return;
        }

        $data = static::data($user);

        $user->setRelation('roles', $data['roles']);
        $user->setRelation('company', $data['company']);
        $user->setRelation('technician', $data['technician']);

        $data['company']?->setRelation('plan', $data['plan']);

        $user->setPermissionSlugs($data['permissions']);
        static::track($user);
    }

    /** No login só a assinatura importa; papéis e plano entram no primeiro clique. */
    public static function hydrateCompany(User $user): void
    {
        if ($user->relationLoaded('company')) {
            return;
        }

        if (app()->runningUnitTests()) {
            $company = Company::query()->find($user->company_id);
        } else {
            $company = Cache::remember(
                'user-context:company:v'.static::version().':'.$user->id,
                now()->addSeconds(self::COMPANY_TTL_SECONDS),
                static fn (): ?Company => Company::query()->find($user->company_id),
            );
        }

        $user->setRelation('company', $company);
        static::track($user);
    }

    public static function companyIsActive(User $user): bool
    {
        static::hydrateCompany($user);

        return $user->company?->isActive() ?? false;
    }

    /**
     * Invalida a identidade de todas as contas. Papel alterado, empresa mudada
     * ou permissão concedida precisam valer no próximo clique, não no fim do TTL.
     */
    public static function invalidate(): void
    {
        User::forgetPermissionCache();

        // Uma troca de papel no meio do request não pode deixar o próprio
        // modelo com a identidade antiga até o redirect seguinte.
        foreach (static::$hidratados ?? [] as $usuario => $_) {
            $usuario->unsetRelation('roles');
            $usuario->unsetRelation('company');
            $usuario->unsetRelation('technician');
        }

        static::$hidratados = null;

        if (app()->runningUnitTests()) {
            return;
        }

        Cache::increment(self::VERSION_KEY);
        // O increment inicializa a chave em 1 quando ela ainda não existe; o
        // remember só garante validade para o caso de um store sem increment.
        Cache::remember(self::VERSION_KEY, now()->addDay(), static fn (): int => 1);
    }

    private static function track(User $user): void
    {
        $hidratados = static::$hidratados ??= new \WeakMap();
        $hidratados[$user] = true;
    }

    /** @return array<string, mixed> */
    private static function data(User $user): array
    {
        if (app()->runningUnitTests()) {
            return static::consult($user);
        }

        return Cache::remember(
            'user-context:v'.static::version().':'.$user->id,
            now()->addSeconds(self::TTL_SECONDS),
            static fn (): array => static::consult($user),
        );
    }

    private static function version(): int
    {
        return (int) Cache::remember(self::VERSION_KEY, now()->addDay(), static fn (): int => 1);
    }

    /** @return array<string, mixed> */
    private static function consult(User $user): array
    {
        $roles = $user->roles()->with('permissions')->get();

        $permissions = $user->isRoot()
            ? Permission::query()->pluck('slug')->all()
            : $roles->pluck('permissions')->flatten()->pluck('slug')->unique()->values()->all();

        $company = $user->relationLoaded('company')
            ? $user->company
            : Company::query()->find($user->company_id);

        // No login o TenantContext ainda não foi resolvido. A ficha de campo é
        // buscada fora do escopo, mas cravada na empresa da própria conta.
        $technician = Technician::query()
            ->anyCompany()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->first();

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'company' => $company,
            'plan' => $company?->plan,
            'technician' => $technician,
        ];
    }
}
