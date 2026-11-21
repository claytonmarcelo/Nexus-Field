<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Services\Recusa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Department extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    /** Profundidade máxima permitida pela árvore: protege consultas e a tela. */
    public const PROFUNDIDADE_MAXIMA = 8;

    protected $fillable = [
        'company_id', 'branch_id', 'parent_id', 'name', 'slug', 'description',
        'manager_user_id', 'type', 'depth', 'path', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'depth' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $setor): void {
            if ($setor->parent_id === null) {
                $setor->depth = 0;

                return;
            }

            $recusa = static::validarPai($setor);

            if ($recusa !== null) {
                throw $recusa;
            }
        });

        static::creating(function (self $setor): void {
            if (blank($setor->slug)) {
                $setor->slug = Str::slug((string) $setor->name);
            }
        });

        static::created(function (self $setor): void {
            // O caminho materializado precisa do id, então só existe depois do
            // insert. saveQuietly evita um segundo evento de auditoria.
            $pai = $setor->parent_id === null ? null : static::query()->find($setor->parent_id);
            $setor->path = ($pai?->path ?: '/').$setor->getKey().'/';
            $setor->saveQuietly();
        });
    }

    private static function validarPai(self $setor): ?Recusa
    {
        if ((int) $setor->parent_id === (int) $setor->getKey()) {
            return new Recusa('Um setor nao pode ser pai de si mesmo.');
        }

        // O escopo global garante que só apareça um pai da empresa atual. Sem
        // isso, um id de outra empresa poderia virar raiz da árvore local.
        $pai = static::query()->find($setor->parent_id);

        if ($pai === null) {
            return new Recusa('O setor pai nao existe nesta empresa.');
        }

        if ((int) $pai->company_id !== (int) $setor->company_id) {
            return new Recusa('O setor pai pertence a outra empresa.');
        }

        // O proprio path ja termina com o id do setor e uma barra: "/1/2/".
        // Um pai dentro da subarvore comeca com esse prefixo.
        $prefixo = $setor->path ?: ('/'.$setor->getKey().'/');

        if (str_starts_with((string) $pai->path, $prefixo)) {
            return new Recusa('A mudanca criaria um ciclo na hierarquia.');
        }

        if ($pai->depth + 1 > self::PROFUNDIDADE_MAXIMA) {
            return new Recusa('A hierarquia de setores atingiu o limite de profundidade.');
        }

        $setor->depth = $pai->depth + 1;

        return null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    public function serviceCategories(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class, 'department_service_categories')
            ->withPivot('escopo');
    }

    /** Inclui o próprio setor e toda a subárvore, sem OR solto fora do closure. */
    public function scopeNaArvore(Builder $query, self $setor): Builder
    {
        return $query->where('company_id', $setor->company_id)
            ->where(function (Builder $q) use ($setor): void {
                $q->where('id', $setor->id)
                    ->orWhere('path', 'like', (string) $setor->path.'%');
            });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
