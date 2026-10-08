<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialty extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'slug', 'description',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(Technician::class, 'specialty_technician');
    }
}
