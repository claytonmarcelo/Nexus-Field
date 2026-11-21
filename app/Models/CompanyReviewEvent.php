<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Trilha das transições do pedido: quem mudou o que, quando e por quê. */
class CompanyReviewEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'registration_id', 'from_state', 'to_state', 'actor_id',
        'reason', 'ip_hash', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(CompanyRegistration::class, 'registration_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
