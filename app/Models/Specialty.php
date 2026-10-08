<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Specialty extends Model
{
    use Auditable;
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'slug', 'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $especialidade) {
            // O slug é a chave estável do cadastro (única por empresa) e nasce do
            // nome, com acento removido: ninguém digita identificação técnica.
            if (blank($especialidade->slug)) {
                $especialidade->slug = Str::slug((string) $especialidade->name);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(Technician::class, 'specialty_technician');
    }
}
