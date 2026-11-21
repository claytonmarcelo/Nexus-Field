<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employment extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    public const SITUACOES = ['ativo', 'afastado', 'encerrado', 'desligado'];

    protected $fillable = [
        'company_id', 'user_id', 'branch_id', 'department_id', 'position_id', 'manager_id',
        'employee_code', 'employment_type', 'status', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Nunca reescreve o histórico: fecha a passagem anterior e abre outra.
     * A desativação real de acesso é feita pelo serviço de desligamento.
     */
    public function encerrar(string $situacao = 'desligado'): void
    {
        $this->forceFill([
            'status' => $situacao,
            'ended_at' => now()->toDateString(),
        ])->save();
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', 'ativo')->whereNull('ended_at');
    }
}
