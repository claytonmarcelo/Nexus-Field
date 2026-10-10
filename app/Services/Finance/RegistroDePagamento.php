<?php

namespace App\Services\Finance;

use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Models\User;
use App\Support\Auditor;
use App\Support\Formatters;
use App\Support\StatusCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O degrau entre o formulário de pagamento e o banco — e o único lugar que escreve
 * o estado de uma conta depois que ela nasce.
 *
 * O pagamento é a escrita de estado desta casa: ele entra, a linha do lançamento é
 * travada, a soma é lida de volta do banco e `status` sai de lá
 * (`FinancialRecord::recalcularEstado()`). Não existe "marcar como pago": existe
 * registrar o PIX, o cartão ou a transferência com valor, método, data e autor, e o
 * estado vir junto. Quem quisesse pintar a conta de quitada sem dinheiro registrado
 * teria de passar por aqui, e aqui a régua é a soma, não a vontade de quem digita.
 *
 * **A trava é o que faz dois lançamentos no mesmo segundo baterem.** Cada um confere
 * o saldo na própria leitura, cada um vê saldo suficiente, e a conta de R$ 500 fecha
 * com R$ 800 pagos — que é exatamente o quadro que ninguém consegue explicar na hora
 * do fechamento. A linha é travada antes de somar, e a soma é do banco, não da tela.
 *
 * Acima do saldo não entra: a resposta devolve quanto falta, em vez de aceitar a
 * generosidade e deixar o saldo negativo. Conta quitada também não recebe pagamento,
 * e conta cancelada não recebe dinheiro de conta que não existe para o caixa. As três
 * saem como `ValidationException` no campo `valor` — é o número em que a pessoa errou,
 * e é embaixo dele que a frase tem de aparecer.
 *
 * Estornar (apagar a linha do pagamento) é o degrau de quem responde pelo caixa, e não
 * o sumiço do fato: a auditoria guarda quem desfez, quanto e em que conta.
 */
final class RegistroDePagamento
{
    /**
     * Registra o dinheiro e devolve o que a apresentação precisa: a linha criada, a
     * conta já com o estado que a soma formou, o estado e a frase da tela. A conta
     * devolvida é a instância travada dentro da transação — é ela que tem o número
     * certo, não a que entrou pela binding da rota.
     *
     * @param  array<string, mixed>  $validado
     * @return array{pagamento: Payment, conta: FinancialRecord, estado: string, resumo: string}
     */
    public function registrar(FinancialRecord $registro, User $usuario, array $validado): array
    {
        [$pagamento, $conta, $estado] = DB::transaction(
            fn (): array => $this->gravar($registro, $usuario, $validado),
        );

        Auditor::gravar('pagamento de conta', $conta, [], sprintf(
            '%s de %s por %s, registrado por %s. Conta agora: %s.',
            StatusCatalog::label('payment_method', $pagamento->method),
            Formatters::money($pagamento->amount),
            Formatters::date($pagamento->paid_at),
            $usuario->name,
            $conta->rotuloEstado(),
        ));

        return [
            'pagamento' => $pagamento,
            'conta' => $conta,
            'estado' => $estado,
            'resumo' => $this->resumo($conta, $estado),
        ];
    }

    /**
     * Desfaz a linha e devolve a conta ao estado que a soma formada sem ela manda.
     * A leitura é travada pelos mesmos motivos do registro: estorno e pagamento
     * concurrentes na mesma conta somariam um saldo que ninguém viu.
     *
     * @return array{estado: string, resumo: string}
     */
    public function estornar(FinancialRecord $registro, Payment $pagamento, User $usuario): array
    {
        $estado = DB::transaction(function () use ($registro, $pagamento): string {
            $conta = FinancialRecord::query()->whereKey($registro->id)->lockForUpdate()->firstOrFail();

            Payment::query()->whereKey($pagamento->id)->delete();

            return $conta->recalcularEstado();
        });

        $conta = $registro->fresh();

        Auditor::gravar('estorno de pagamento', $conta, [], sprintf(
            'Estorno de %s de %s (%s), feito por %s. Conta voltou a %s.',
            Formatters::money($pagamento->amount),
            StatusCatalog::label('payment_method', $pagamento->method),
            Formatters::date($pagamento->paid_at),
            $usuario->name,
            $conta->rotuloEstado(),
        ));

        return [
            'estado' => $estado,
            'resumo' => sprintf(
                'Pagamento de %s estornado. A conta %s.',
                Formatters::money($pagamento->amount),
                $estado === FinancialRecord::PENDING
                    ? 'voltou a ficar em aberto'
                    : 'passou a '.mb_strtolower($conta->rotuloEstado()),
            ),
        ];
    }

    /**
     * A ordem dentro da transação é o que faz a trava valer: a conta é presa antes
     * de qualquer conferência, o saldo é lido do que o banco devolve — antes de a
     * linha existir — e o estado volta da soma já escrita.
     *
     * @param  array<string, mixed>  $validado
     * @return array{0: Payment, 1: FinancialRecord, 2: string}
     */
    private function gravar(FinancialRecord $registro, User $usuario, array $validado): array
    {
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

        return [$pagamento, $conta, $conta->recalcularEstado()];
    }

    /**
     * A frase da tela: quanto entrou e o que falta. Quitada merece o número cheio,
     * e conta meio paga precisa mostrar a sobra — é ela que a pessoa vai conferir no
     * extrato amanhã.
     */
    private function resumo(FinancialRecord $conta, string $estado): string
    {
        $pago = $conta->valorPago();

        return match ($estado) {
            FinancialRecord::PAID => sprintf(
                'Pagamento registrado: %s — %s de %s.',
                mb_strtolower($conta->rotuloEstado()),
                Formatters::money($pago),
                Formatters::money($conta->amount),
            ),
            default => sprintf(
                'Pagamento registrado: %s de %s nesta conta, faltam %s.',
                Formatters::money($pago),
                Formatters::money($conta->amount),
                Formatters::money($conta->saldo()),
            ),
        };
    }
}
