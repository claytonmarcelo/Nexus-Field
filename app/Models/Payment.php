<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Formatters;
use App\Support\StatusCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O dinheiro que mudou de mão.
 *
 * Pagamento não é estado de lançamento, é fato: uma linha com valor, método, data
 * e autor. Um lançamento pode ter três, e é a soma deles que decide se a conta
 * está aberta, meio paga ou quitada (`FinancialRecord::recalcularEstado()`).
 *
 * A data é a do caixa, não a do teclado: `paid_at` vem do formulário porque o
 * PIX de ontem é registrado hoje, e o que se pergunta aqui é quando o dinheiro
 * saiu ou entrou. Diferente da movimentação de estoque, onde o fato é agora. O
 * limite é o hoje — pagamento com data futura é conta que ainda não aconteceu.
 *
 * Estornar pagamento (apagar a linha) é o degrau de quem responde pelo caixa
 * (`financial.approve`), e fica na auditoria com quem fez: diferente do livro de
 * estoque, o pagamento errado se desfaz porque não existe "pagamento negativo"
 * no extrato de ninguém — mas só se desfaz por cima, com permissão e rastro.
 */
class Payment extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id', 'financial_record_id', 'user_id', 'amount', 'method', 'reference',
        'paid_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function financialRecord(): BelongsTo
    {
        return $this->belongsTo(FinancialRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function rotuloMetodo(?string $metodo): string
    {
        return StatusCatalog::label('payment_method', $metodo);
    }

    protected static function resumoAuditoria(Model $modelo): string
    {
        return sprintf(
            'pagamento de %s por %s',
            Formatters::money($modelo->amount),
            static::rotuloMetodo($modelo->method),
        );
    }
}
