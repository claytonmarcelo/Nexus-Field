<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TemEnderecos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Technician extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes, TemEnderecos;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'document', 'phone', 'email', 'status', 'region',
        'latitude', 'longitude', 'last_location_at', 'admission_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_location_at' => 'datetime',
            'admission_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'specialty_technician');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot(['joined_at', 'left_at']);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(ServiceOrderCheckin::class);
    }

    /** O que ele está carregando agora, por produto. Estado, não histórico. */
    public function stocks(): HasMany
    {
        return $this->hasMany(TechnicianStock::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', 'available');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', '<>', 'inactive');
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
