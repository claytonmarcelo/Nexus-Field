<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\StatusCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O livro-caixa do estoque: cada linha é um fato que aconteceu numa hora, contra
 * um produto, e tem autor. Não se edita e não se apaga — quem errou registra outra
 * linha que diz o que corrigiu, e o saldo sai da soma. É a razão de `products` não
 * ter coluna de quantidade: número de estoque que alguém digita é número que
 * ninguém pode explicar.
 *
 * Os dois sinais abaixo são a régua inteira.
 *
 * `CENTRAL_SIGN` é o que cada tipo faz com o estoque da empresa. Compra e
 * devolução entram, carga sai, ajuste carrega o próprio sinal na quantidade (o
 * inventário pode tanto achar unidade quanto perder), e consumo fica de fora de
 * propósito: o que o técnico gastou no local já saiu do central quando a carga
 * foi baixada, e contá-lo outra vez seria o mesmo parafuso descontado duas vezes.
 *
 * `TECHNICIAN_SIGN` é o que o mesmo fato faz com a carga do carro: a carga entra,
 * o consumo e a devolução saem.
 *
 * Os dois juntos explicam por que `load` é −1 num lado e +1 no outro: a
 * movimentação não é um número solto, é uma unidade que troca de mãos.
 */
class StockMovement extends Model
{
    use Auditable, BelongsToCompany;

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

    /** Tipos que mexem na carga do técnico: sem técnico não há de quem falar. */
    public const COM_TECNICO = ['load', 'consume', 'return'];

    /** O consumo é o único fato que pede a ordem em que a unidade foi gasta. */
    public const COM_ORDEM = ['consume'];

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

    /**
     * O efeito desta linha no estoque central. O ajuste carrega o sinal na própria
     * quantidade, então a multiplicação é o que devolve o número com o sinal certo —
     * e é a mesma conta da subquery do saldo, não uma parecida.
     */
    public function efeitoCentral(): float
    {
        return round((self::CENTRAL_SIGN[$this->type] ?? 0) * (float) $this->quantity, 4);
    }

    /** Null é "este tipo não fala com a carga do técnico", e a tela mostra isso com as mesmas palavras do saldo. */
    public function efeitoTecnico(): ?float
    {
        if ($this->technician_id === null || ! array_key_exists($this->type, self::TECHNICIAN_SIGN)) {
            return null;
        }

        return round(self::TECHNICIAN_SIGN[$this->type] * (float) $this->quantity, 4);
    }

    public function mexeComTecnico(): bool
    {
        return in_array($this->type, self::COM_TECNICO, true);
    }

    public function escopoDa(): string
    {
        return $this->technician_id === null ? 'estoque central' : 'carga do técnico';
    }

    /**
     * O alcance da casa aplicado ao livro-caixa. A régua é responder pelo
     * inventário (`stock.adjust`), não o nome do papel: quem pode ajustar é quem lê
     * a empresa inteira, e quem só movimenta — o técnico com a própria ficha — lê as
     * linhas em que o nome dele aparece: a própria carga, o próprio consumo, a
     * própria devolução. As compras da empresa não têm técnico, então não entram
     * nessa leitura sem que a tela precise explicar o recorte.
     */
    public function scopeVisiveisPara(Builder $q, User $usuario): Builder
    {
        $tecnico = $usuario->technician;

        if ($tecnico !== null && ! $usuario->hasPermission('stock.adjust')) {
            return $q->where('technician_id', $tecnico->id);
        }

        return $q;
    }

    public static function alcanceRestrito(User $usuario): bool
    {
        return $usuario->technician !== null && ! $usuario->hasPermission('stock.adjust');
    }

    protected static function resumoAuditoria(Model $modelo): string
    {
        return sprintf(
            'movimentação "%s" de %s',
            StatusCatalog::label('movement', $modelo->type),
            $modelo->product?->name ?? 'produto removido',
        );
    }
}
