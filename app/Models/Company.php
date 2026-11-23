<?php

namespace App\Models;

use App\Support\UserContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'document', 'phone', 'email', 'website', 'logo_path',
        'plan_id', 'subscription_status', 'trial_ends_at', 'subscribed_at',
        'expires_at', 'status', 'legal_name', 'trade_name', 'cnpj', 'ie', 'im',
        'cnae_main', 'cnae_secondary', 'legal_nature', 'size', 'opened_at',
        'tax_regime', 'segment', 'employee_count', 'field_technician_count',
        'contacts', 'legal_responsible', 'lifecycle_status', 'registration_id',
    ];

    protected static function booted(): void
    {
        // Nome, logo, status e assinatura aparecem na identidade cacheada de
        // todas as contas da empresa.
        static::saved(static fn (): mixed => UserContext::invalidate());
        static::deleted(static fn (): mixed => UserContext::invalidate());
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'expires_at' => 'datetime',
            'opened_at' => 'date',
            'cnae_secondary' => 'array',
            'contacts' => 'array',
            'legal_responsible' => 'array',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(CompanyRegistration::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ! in_array($this->subscription_status, ['expired', 'canceled'], true);
    }
}
