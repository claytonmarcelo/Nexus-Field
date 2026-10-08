<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CompanyScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            // Com tenant resolvido, o contexto manda: um company_id vindo do
            // request não pode escrever na empresa alheia. Sem contexto
            // (console/seed) o valor explícito é mantido.
            if (($id = TenantContext::id()) !== null) {
                $model->company_id = $id;
            }
        });
    }

    /** Fora do escopo: usado por relatórios agregados e pelo contexto de console. */
    public function scopeAnyCompany(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class);
    }
}
