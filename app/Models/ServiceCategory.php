<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceCategory extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'slug',
    ];

    protected static function booted(): void
    {
        // O slug é o que a unicidade por empresa garante no schema; deixar de fora
        // é criar categoria duplicada com nome grafado diferente.
        static::saving(function (self $categoria) {
            if (blank($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->name);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
