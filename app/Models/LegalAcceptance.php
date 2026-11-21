<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aceite de termo/privacidade ligado ao pedido, com versão e data/hora. */
class LegalAcceptance extends Model
{
    public const TERMOS = 'termos_de_uso';
    public const PRIVACIDADE = 'politica_de_privacidade';

    public $timestamps = false;

    protected $fillable = [
        'registration_id', 'user_id', 'kind', 'version',
        'accepted_at', 'ip_hash', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(CompanyRegistration::class, 'registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
