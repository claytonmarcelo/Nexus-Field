<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrder extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'service_id', 'technician_id', 'team_id', 'number', 'title',
        'description', 'priority', 'status', 'scheduled_starts_at', 'scheduled_ends_at',
        'started_at', 'completed_at', 'cancelled_at', 'execution_notes', 'cancellation_reason',
        'discount', 'street', 'number_address', 'complement', 'neighborhood', 'city', 'state',
        'zip_code', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_starts_at' => 'datetime',
            'scheduled_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'discount' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ServiceOrderAssignment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ServiceOrderStatusHistory::class)->latest('created_at');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(ServiceOrderCheckin::class);
    }

    public function financialRecords(): HasMany
    {
        return $this->hasMany(FinancialRecord::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'in_progress', 'on_hold']);
    }

    public function scopeInProgress(Builder $q): Builder
    {
        return $q->where('status', 'in_progress');
    }

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', 'completed');
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('scheduled_ends_at')
            ->where('scheduled_ends_at', '<', now());
    }

    public function scopeScheduledBetween(Builder $q, $inicio, $fim): Builder
    {
        return $q->whereBetween('scheduled_starts_at', [$inicio, $fim]);
    }
}
