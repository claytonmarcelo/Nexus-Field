<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'technician_id', 'client_id', 'service_order_id', 'ticket_id', 'title',
        'description', 'type', 'status', 'starts_at', 'ends_at', 'all_day', 'location',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scopeBetween(Builder $q, $inicio, $fim): Builder
    {
        return $q->where('starts_at', '<=', $fim)->where('ends_at', '>=', $inicio);
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', 'scheduled');
    }
}
