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

class Client extends Model
{
    use Auditable;
    use BelongsToCompany;
    use SoftDeletes;
    use TemEnderecos;

    protected $fillable = [
        'company_id', 'name', 'trade_name', 'document', 'email', 'phone', 'status', 'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function financialRecords(): HasMany
    {
        return $this->hasMany(FinancialRecord::class);
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
