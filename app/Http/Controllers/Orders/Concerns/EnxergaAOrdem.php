<?php

namespace App\Http\Controllers\Orders\Concerns;

use App\Models\ServiceOrder;
use App\Models\User;

/**
 * O alcance do usuário sobre uma ordem. A rota ligada pelo binding implícito já
 * passou pelo escopo de empresa, mas empresa é metade da pergunta: o técnico tem
 * as dele e a conta de cliente tem a carteira dela. As fichas aninhadas — itens e
 * quadro de comissão — entram pela ordem, então a mesma resposta vale para todas.
 */
trait EnxergaAOrdem
{
    protected function garantirVisivel(ServiceOrder $ordem, User $usuario): void
    {
        abort_unless(
            ServiceOrder::query()->visiveisPara($usuario)->whereKey($ordem->id)->exists(),
            404,
            'Esta ordem não está no seu alcance.',
        );
    }
}
