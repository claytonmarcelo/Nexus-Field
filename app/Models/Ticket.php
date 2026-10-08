<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'service_order_id', 'responsible_user_id', 'technician_id',
        'protocol', 'subject', 'description', 'category', 'priority', 'status', 'opened_at',
        'resolved_at', 'closed_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'in_progress', 'waiting']);
    }

    public function scopeUrgent(Builder $q): Builder
    {
        return $q->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeResolvedBetween(Builder $q, $inicio, $fim): Builder
    {
        return $q->whereBetween('resolved_at', [$inicio, $fim]);
    }
}
