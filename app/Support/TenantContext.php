<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/**
 * Tenant corrente da request. Sem isso o escopo global não tem como saber
 * por qual empresa filtrar, e o multi-tenant viraria só uma coluna a mais.
 */
class TenantContext
{
    private static ?int $companyId = null;

    private static ?Company $company = null;

    public static function set(?int $companyId, ?Company $company = null): void
    {
        static::$companyId = $companyId;
        static::$company = $company;
    }

    public static function forget(): void
    {
        static::$companyId = null;
        static::$company = null;
    }

    public static function id(): ?int
    {
        return static::$companyId;
    }

    public static function company(): ?Company
    {
        if (static::$company === null && static::$companyId !== null) {
            static::$company = Company::query()->find(static::$companyId);
        }

        return static::$company;
    }

    public static function resolveFromUser(?User $user): void
    {
        if ($user === null) {
            static::forget();

            return;
        }

        static::set($user->company_id, $user->company);
    }
}
