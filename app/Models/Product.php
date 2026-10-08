<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'sku', 'name', 'description', 'unit', 'cost', 'price', 'reorder_point',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'reorder_point' => 'decimal:4',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeWithCentralBalance(Builder $q): Builder
    {
        // Subquery correlacionada em vez de join + groupBy: o groupBy substituiria o SELECT
        // de products e quebraria a paginação da listagem.
        return $q->select('products.*')->selectSub(static::centralBalanceQuery(), 'central_balance');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeBelowReorderPoint(Builder $q): Builder
    {
        $balance = static::centralBalanceQuery();

        // MySQL não aceita alias de SELECT no WHERE: a expressão entra de novo, crua.
        return $q->withCentralBalance()
            ->whereRaw('('.$balance->toSql().') < products.reorder_point', $balance->getBindings());
    }

    private static function centralBalanceQuery(): QueryBuilder
    {
        $cases = '';
        $bindings = [];

        foreach (StockMovement::CENTRAL_SIGN as $type => $sign) {
            $cases .= ' when type = ? then ? * quantity';
            $bindings[] = $type;
            $bindings[] = $sign;
        }

        return DB::table('stock_movements')
            ->selectRaw('coalesce(sum(case'.$cases.' else 0 end), 0)', $bindings)
            ->whereColumn('stock_movements.product_id', 'products.id')
            ->whereColumn('stock_movements.company_id', 'products.company_id');
    }
}
