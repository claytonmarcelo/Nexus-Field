<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'leader_id', 'region', 'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'leader_id');
    }

    /** Quadro atual: `detach` trabalha nesta relação, por isso ela não filtra nada. */
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(Technician::class, 'team_members')
            ->withPivot(['joined_at', 'left_at']);
    }

    /** Só quem ainda está na equipe (saída registrada em `left_at`). */
    public function membrosAtivos(): BelongsToMany
    {
        return $this->technicians()->wherePivotNull('left_at');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }
}
