<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'client_id', 'body', 'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Quem escreveu a nota: toda nota sai do escritório (usuário) ou da carteira
     * (cliente), nunca dos dois. A carteira vem primeiro porque, no atendimento, o
     * que o escritório lê é o nome do cliente, não o da conta que digitou. A ficha
     * usa isto em vez de escolher a relação na tela, para autoria e marca de interno
     * não divergirem.
     */
    public function autor(): string
    {
        return $this->client?->name ?? $this->user?->name ?? 'Sem autoria registrada';
    }
}
