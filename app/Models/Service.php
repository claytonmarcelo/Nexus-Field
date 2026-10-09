<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'service_category_id', 'code', 'name', 'description', 'price',
        'estimated_minutes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /** Ordens que foram abertas com este serviço como objeto do trabalho. */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /** Itens de ordem que cobraram este serviço. */
    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * A rota também resolve o registro arquivado, mas só na leitura: a ficha
     * existe para mostrar o selo de arquivado e o histórico, e uma ordem antiga
     * tem direito de apontar para ele. Escrita não passa por cadastro morto —
     * POST, PUT, PATCH e DELETE continuam no 404, que é o servidor recusando
     * antes de qualquer botão. A única porta de volta é o botão restaurar, que
     * anda pelo id e não por esta binding. O escopo da empresa segue valendo.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $consulta = $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value);

        if (in_array(request()->method(), ['GET', 'HEAD'], true)) {
            return $consulta->withTrashed()->first();
        }

        return $consulta->first();
    }
}
