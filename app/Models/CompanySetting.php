<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        $companyId = TenantContext::id();

        if ($companyId === null) {
            return $default;
        }

        $row = static::query()->anyCompany()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        return $row?->value['value'] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['company_id' => TenantContext::id(), 'key' => $key],
            ['value' => ['value' => $value]],
        );
    }
}
