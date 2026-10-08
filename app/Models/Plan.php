<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'max_users', 'max_technicians', 'max_clients',
        'max_service_orders_per_month', 'price_monthly', 'features', 'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price_monthly' => 'decimal:2',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
