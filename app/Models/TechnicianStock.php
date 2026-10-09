<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que o técnico está carregando no carro, por produto. É estado, não histórico:
 * a linha existe para responder "ele tem este item agora?", e a resposta vem da
 * soma das movimentações de carga, consumo e devolução — os sinais de
 * `StockMovement::TECHNICIAN_SIGN`.
 *
 * Por que guardar se dá para somar? Porque as duas contas respondem perguntas
 * diferentes. O saldo central é derivado das movimentações (fase 11) porque o
 * estoque da empresa é o livro-caixa e ninguém tem o direito de ajustá-lo por
 * fora dele. A carga do técnico é material em movimento: uma vez baixada do
 * central, o que acontece nela dentro do carro é fato físico, e a linha trava
 * contra si mesma para que dois consumos simultâneos não baixem três unidades de
 * um produto que tinha duas.
 *
 * Saldo negativo não existe. O consumo que passa do que foi carregado é recusado
 * na tela com o número que há, porque "−2 unidades no carro" é a assinatura de
 * uma movimentação que alguém inventou.
 */
class TechnicianStock extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'technician_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** O que este técnico carrega, com produto e unidade junto para a ficha não consultar linha por linha. */
    public function scopeDoTecnico(Builder $q, Technician $tecnico): Builder
    {
        return $q->where('technician_id', $tecnico->id);
    }

    /** Carga de verdade: zerado é o produto que ele já devolveu inteiro, e a ficha não lista isso. */
    public function scopeComSaldo(Builder $q): Builder
    {
        return $q->where('quantity', '>', 0);
    }

    /**
     * A linha deste par técnico/produto, travada para escrita. Dentro de uma
     * transação é o que impede dois consumos de baixarem juntos mais do que a
     * carga permite: quem chega depois lê o quantidade já descontado, não o que
     * estava na tela de quem começou primeiro.
     */
    public static function travar(Technician $tecnico, Product $produto): static
    {
        $registro = static::query()
            ->where('technician_id', $tecnico->id)
            ->where('product_id', $produto->id)
            ->lockForUpdate()
            ->first();

        if ($registro !== null) {
            return $registro;
        }

        // O índice único (technician_id, product_id) é quem decide se duas cargas
        // simultâneas criaram a mesma linha: uma das duas vai receber a violação e
        // a transação dela desfaz, em vez de o saldo ficar com duas metades.
        static::query()->create([
            'technician_id' => $tecnico->id,
            'product_id' => $produto->id,
            'quantity' => 0,
        ]);

        return static::query()
            ->where('technician_id', $tecnico->id)
            ->where('product_id', $produto->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Aplica o delta e devolve o novo saldo já lido de volta da linha — é o número
     * que a mensagem na tela mostra, e ele vem do que o banco gravou, não da conta
     * feita em PHP antes do `save()`.
     */
    public function aplicar(float $delta): float
    {
        $novo = round((float) $this->quantity + $delta, 4);

        $this->quantity = $novo;
        $this->save();

        return $novo;
    }

    protected static function resumoAuditoria(Model $modelo): string
    {
        return sprintf(
            'carga de %s para %s',
            $modelo->product?->name ?? 'produto removido',
            $modelo->technician?->name ?? 'técnico sem ficha',
        );
    }
}
