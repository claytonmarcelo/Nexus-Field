<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderCheckin extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'service_order_id', 'technician_id', 'checkin_at', 'checkin_latitude',
        'checkin_longitude', 'checkout_at', 'checkout_latitude', 'checkout_longitude', 'status',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'checkin_at' => 'datetime',
            'checkin_latitude' => 'decimal:7',
            'checkin_longitude' => 'decimal:7',
            'checkout_at' => 'datetime',
            'checkout_latitude' => 'decimal:7',
            'checkout_longitude' => 'decimal:7',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('checkout_at');
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('checkin_at', now()->toDateString());
    }
}
