<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ponte entre os escopos legados `visiveisPara` e a política única de alcance.
 *
 * O modelo continua dono das colunas que conhece (comissão, responsável,
 * subconsulta de cliente). O trait só acrescenta o recorte organizacional
 * quando ele existe; sem estrutura nova a consulta volta exatamente a mesma.
 */
trait ComAlcance
{
    /**
     * Método auxiliar (não um local scope): chamado de dentro de `visiveisPara`,
     * onde o Eloquent já repassou o builder e não deve injetá-lo uma segunda vez.
     *
     * @param  array<string, string>  $mapa
     */
    public function aplicarAlcance(Builder $query, User $usuario, array $mapa = []): Builder
    {
        return Alcance::aplicar($query, $usuario, $mapa);
    }
}
