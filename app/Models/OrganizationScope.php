<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationScope extends Model
{
    use Auditable, BelongsToCompany;

    /** Escopos reconhecidos pela política de alcance. */
    public const ALCACES = [
        'company', 'branch', 'department', 'team', 'subordinates', 'self', 'client',
    ];

    protected $fillable = [
        'company_id', 'user_id', 'scope', 'scope_id', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
