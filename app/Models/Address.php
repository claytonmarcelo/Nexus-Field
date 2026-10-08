<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    use Auditable;
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'addressable_type', 'addressable_id', 'type', 'zip_code', 'street',
        'number', 'complement', 'neighborhood', 'city', 'state', 'latitude', 'longitude',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_primary' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function fullLine(): string
    {
        return collect([
            $this->street.($this->number ? ', '.$this->number : ''),
            $this->complement,
            $this->neighborhood,
            $this->city ? $this->city.'/'.$this->state : null,
        ])->filter()->implode(' - ');
    }
}
