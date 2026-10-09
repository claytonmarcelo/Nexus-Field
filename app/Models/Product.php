<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
    use Auditable, BelongsToCompany, SoftDeletes;

    /** Unidades que a tela oferece; o cadastro aceita qualquer uma delas. */
    public const UNIDADES = [
        'un' => 'Unidade',
        'kg' => 'Quilograma',
        'g' => 'Grama',
        'l' => 'Litro',
        'ml' => 'Mililitro',
        'm' => 'Metro',
        'cm' => 'Centímetro',
        'cx' => 'Caixa',
        'pct' => 'Pacote',
        'par' => 'Par',
    ];

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

    /** O que cada técnico está carregando deste produto, por par técnico/produto. */
    public function cargas(): HasMany
    {
        return $this->hasMany(TechnicianStock::class);
    }

    /** Itens de ordem que já cobraram este produto. */
    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
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

    /** O avesso do filtro acima: o que ainda aguenta uma saída sem virar alerta. */
    public function scopeAtOrAboveReorderPoint(Builder $q): Builder
    {
        $balance = static::centralBalanceQuery();

        return $q->withCentralBalance()
            ->whereRaw('('.$balance->toSql().') >= products.reorder_point', $balance->getBindings());
    }

    /**
     * O saldo central de um produto lido na hora. É a conta que uma saída precisa
     * passar antes de ser gravada: a listagem mostra o saldo derivado, a gravação
     * confere contra o mesmo `CASE`, e os dois números são o mesmo SQL — se a
     * regra do sinal mudasse, mudaria num lugar só.
     */
    public function saldoCentralAtual(): float
    {
        [$cases, $bindings] = static::casoDoSinal();

        return (float) DB::table('stock_movements')
            ->selectRaw('coalesce(sum(case'.$cases.' else 0 end), 0) as saldo', $bindings)
            ->where('product_id', $this->id)
            ->where('company_id', $this->company_id)
            ->value('saldo');
    }

    /**
     * O que cada tipo de movimentação faz com o estoque central, em SQL. Público
     * porque a tela de movimentações precisa montar o mesmo CASE para ordenar e
     * somar sem inventar uma segunda régua.
     */
    public static function centralBalanceQuery(): QueryBuilder
    {
        [$cases, $bindings] = static::casoDoSinal();

        return DB::table('stock_movements')
            ->selectRaw('coalesce(sum(case'.$cases.' else 0 end), 0)', $bindings)
            ->whereColumn('stock_movements.product_id', 'products.id')
            ->whereColumn('stock_movements.company_id', 'products.company_id');
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private static function casoDoSinal(): array
    {
        $cases = '';
        $bindings = [];

        foreach (StockMovement::CENTRAL_SIGN as $type => $sign) {
            $cases .= ' when type = ? then ? * quantity';
            $bindings[] = $type;
            $bindings[] = $sign;
        }

        return [$cases, $bindings];
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
