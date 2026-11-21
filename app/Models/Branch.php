<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TemEnderecos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Branch extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes, TemEnderecos;

    protected $fillable = [
        'company_id', 'name', 'slug', 'type', 'manager_user_id', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (self $filial): void {
            if (blank($filial->slug)) {
                $filial->slug = Str::slug((string) $filial->name);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
