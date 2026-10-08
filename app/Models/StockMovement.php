<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToCompany;

    // consume fica fora de propósito: o consumo baixa o estoque carregado pelo técnico,
    // não o central. adjustment carrega o sinal na própria quantidade (inventario pode
    // baixar), os demais tipos usam quantidade positiva.
    public const CENTRAL_SIGN = [
        'purchase' => 1,
        'return' => 1,
        'adjustment' => 1,
        'load' => -1,
    ];

    public const TECHNICIAN_SIGN = [
        'load' => 1,
        'consume' => -1,
        'return' => -1,
    ];

    protected $fillable = [
        'company_id', 'product_id', 'technician_id', 'service_order_id', 'user_id',
        'type', 'quantity', 'unit_cost', 'note', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
