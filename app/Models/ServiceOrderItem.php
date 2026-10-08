<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_order_id', 'service_id', 'product_id', 'description', 'quantity', 'unit_price',
        'discount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subtotal(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    /** O que entra na conta do cliente por esta linha, já com o desconto dela. */
    public function total(): float
    {
        return round($this->subtotal() - (float) $this->discount, 2);
    }

    /** Uma linha é serviço ou produto; o CHECK do banco não aceita outra mistura. */
    public function isService(): bool
    {
        return $this->service_id !== null;
    }
}
