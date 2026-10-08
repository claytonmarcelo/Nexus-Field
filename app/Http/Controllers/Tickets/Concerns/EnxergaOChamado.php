<?php

namespace App\Http\Controllers\Tickets\Concerns;

use App\Models\Ticket;
use App\Models\User;

/**
 * O alcance do usuário sobre um chamado. O binding implícito da rota já passou
 * pelo escopo de empresa, mas empresa é metade da pergunta: o técnico responde
 * pelos chamados dele, a conta de cliente pela carteira dela. A nota entra pelo
 * chamado, então a mesma resposta vale para a conversa toda.
 */
trait EnxergaOChamado
{
    protected function garantirVisivel(Ticket $chamado, User $usuario): void
    {
        abort_unless(
            Ticket::query()->visiveisPara($usuario)->whereKey($chamado->id)->exists(),
            404,
            'Este chamado não está no seu alcance.',
        );
    }
}
