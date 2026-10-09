<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Support\Auditor;
use App\Support\Formatters;
use App\Support\StatusCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registrar o dinheiro que mudou de mão — e desfazer um registro errado.
 *
 * O pagamento é a única escrita de estado nesta tela: ele entra, a linha do
 * lançamento é travada, a soma é lida de volta do banco e `status` sai de lá
 * (`FinancialRecord::recalcularEstado()`). Não existe "marcar como pago": existe
 * registrar o PIX, o cartão ou a transferência com valor, método, data e autor, e
 * o estado vir junto.
 *
 * A trava é o que faz dois lançamentos no mesmo segundo baterem. Cada um confere
 * o saldo na própria leitura, cada um vê saldo suficiente, e a conta de R$ 500
 * fecha com R$ 800 pagos — que é exatamente o quadro que ninguém consegue explicar
 * na hora do fechamento. A linha é travada antes de somar, e a soma é do banco,
 * não da tela.
 *
 * Acima do saldo não entra: a resposta devolve quanto falta, em vez de aceitar a
 * generosidade e deixar o saldo negativo. Conta quitada também não recebe
 * pagamento — quem precisa lançar algo a mais está falando de outra conta.
 *
 * Estornar (apagar a linha do pagamento) é o degrau de quem responde pelo caixa, e
 * não o sumiço do fato: a auditoria guarda quem desfez, quanto e em que conta.
 */
class PaymentController extends Controller
{
    private const ANOS_RETROATIVOS = 2;

    public function store(Request $request, FinancialRecord $registro): RedirectResponse
    {
        $usuario = $request->user();

        $validado = $request->validate([
            'valor' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'metodo' => ['required', Rule::in(array_keys(StatusCatalog::options('payment_method')))],
            'data' => [
                'required', 'date', 'before_or_equal:today',
                'after_or_equal:'.now()->subYears(self::ANOS_RETROATIVOS)->toDateString(),
            ],
            'referencia' => ['nullable', 'string', 'max:120'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [
            'valor.gt' => 'Pagamento de zero não muda nada: registre o que efetivamente entrou ou saiu.',
            'valor.decimal' => 'O valor do pagamento tem duas casas decimais, como o extrato.',
            'metodo.required' => 'Escolha como o dinheiro mudou de mão — é o que se confere no extrato.',
            'data.before_or_equal' => 'Dinheiro que ainda não mudou de mão não é pagamento: é previsão.',
            'data.after_or_equal' => sprintf(
                'Passa de %d anos para trás: reabertura de exercício não se faz por aqui.',
                self::ANOS_RETROATIVOS,
            ),
            'referencia.max' => 'A referência tem de caber em 120 caracteres.',
        ]);

        [$pagamento, $estado] = DB::transaction(function () use ($registro, $validado, $usuario): array {
            $conta = FinancialRecord::query()->whereKey($registro->id)->lockForUpdate()->firstOrFail();

            if ($conta->status === FinancialRecord::CANCELED) {
                throw ValidationException::withMessages([
                    'valor' => sprintf(
                        'A conta "%s" está cancelada: reabra antes de registrar dinheiro, porque pagamento em '
                        .'conta inexistente não aparece em carteira nenhuma.',
                        $conta->description,
                    ),
                ]);
            }

            $saldo = $conta->saldo();

            if ($saldo <= 0) {
                throw ValidationException::withMessages([
                    'valor' => sprintf(
                        'Esta conta já está quitada (%s de %s). O que falta tem de vir de outro lançamento.',
                        Formatters::money($conta->valorPago()),
                        Formatters::money($conta->amount),
                    ),
                ]);
            }

            $valor = round((float) $validado['valor'], 2);

            if ($valor > $saldo + 0.005) {
                throw ValidationException::withMessages([
                    'valor' => sprintf(
                        'Faltam %s para fechar esta conta de %s. Registre %s ou cancele e reabra com o valor certo.',
                        Formatters::money($saldo),
                        Formatters::money($conta->amount),
                        Formatters::money($saldo),
                    ),
                ]);
            }

            $pagamento = Payment::query()->create([
                'financial_record_id' => $conta->id,
                'user_id' => $usuario->id,
                'amount' => $valor,
                'method' => strval($validado['metodo']),
                'reference' => filled($validado['referencia'] ?? null) ? strval($validado['referencia']) : null,
                'paid_at' => $validado['data'],
                'note' => filled($validado['observacao'] ?? null) ? strval($validado['observacao']) : null,
            ]);

            return [$pagamento, $conta->recalcularEstado()];
        });

        Auditor::gravar('pagamento de conta', $registro, [], sprintf(
            '%s de %s por %s, registrado por %s. Conta agora: %s.',
            StatusCatalog::label('payment_method', $pagamento->method),
            Formatters::money($pagamento->amount),
            Formatters::date($pagamento->paid_at),
            $usuario->name,
            $registro->fresh()->rotuloEstado(),
        ));

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', $this->mensagem($registro->fresh(), $estado));
    }

    /**
     * Estorno. A linha do pagamento é procurada dentro da conta, e um id de outra
     * conta é 404 antes de qualquer escrita: estornar o pagamento do colega é a
     * mesma chave que escreve na carteira errada.
     */
    public function destroy(Request $request, FinancialRecord $registro, Payment $pagamento): RedirectResponse
    {
        if ((int) $pagamento->financial_record_id !== (int) $registro->id) {
            abort(404, 'Este pagamento não pertence a esta conta.');
        }

        $usuario = $request->user();

        $estado = DB::transaction(function () use ($registro, $pagamento): string {
            $conta = FinancialRecord::query()->whereKey($registro->id)->lockForUpdate()->firstOrFail();

            Payment::query()->whereKey($pagamento->id)->delete();

            return $conta->recalcularEstado();
        });

        Auditor::gravar('estorno de pagamento', $registro, [], sprintf(
            'Estorno de %s de %s (%s), feito por %s. Conta voltou a %s.',
            Formatters::money($pagamento->amount),
            StatusCatalog::label('payment_method', $pagamento->method),
            Formatters::date($pagamento->paid_at),
            $usuario->name,
            $registro->fresh()->rotuloEstado(),
        ));

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', sprintf(
                'Pagamento de %s estornado. A conta %s.',
                Formatters::money($pagamento->amount),
                $estado === FinancialRecord::PENDING ? 'voltou a ficar em aberto' : 'passou a '.mb_strtolower($registro->fresh()->rotuloEstado()),
            ));
    }

    private function mensagem(FinancialRecord $registro, string $estado): string
    {
        $pago = $registro->valorPago();

        return match ($estado) {
            FinancialRecord::PAID => sprintf(
                'Pagamento registrado: %s — %s de %s.',
                mb_strtolower($registro->rotuloEstado()),
                Formatters::money($pago),
                Formatters::money($registro->amount),
            ),
            default => sprintf(
                'Pagamento registrado: %s de %s nesta conta, faltam %s.',
                Formatters::money($pago),
                Formatters::money($registro->amount),
                Formatters::money($registro->saldo()),
            ),
        };
    }
}
