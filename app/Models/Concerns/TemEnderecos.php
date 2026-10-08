<?php

namespace App\Models\Concerns;

use App\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Cadastros que têm pé-direito: cliente, técnico, ordem de serviço. A tabela de
 * endereços é polimórfica desde a fase 2, então o mesmo formulário e o mesmo
 * controller atendem qualquer um deles — o dono da relação vem da rota.
 */
trait TemEnderecos
{
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /** Endereço que a operação usa primeiro: o marcado como principal, ou o mais recente. */
    public function enderecoPrincipal(): ?Address
    {
        return $this->addresses->firstWhere('is_primary', true)
            ?? $this->addresses->sortByDesc('created_at')->first();
    }
}
