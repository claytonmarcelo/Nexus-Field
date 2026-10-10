<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Services\Finance\RegistroDePagamento;
use App\Support\StatusCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registrar o dinheiro que mudou de mão — e desfazer um registro errado.
 *
 * A tela é a fachada, e ficam aqui as regras que só o formulário sabe: valor
 * positivo com duas casas, método do catálogo, e a data que não é futura nem reabre
 * exercício. O resto é de `App\Services\Finance\RegistroDePagamento` — a conta
 * travada antes de somar, o estado derivado da soma lida do banco, o limite que não
 * deixa pagar mais do que falta e a auditoria de quem fez. Não existe "marcar como
 * pago" em lugar nenhum, nem aqui nem lá: a régua é o dinheiro registrado.
 */
class PaymentController extends Controller
{
    private const ANOS_RETROATIVOS = 2;

    public function __construct(private readonly RegistroDePagamento $pagamentos) {}

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

        $resultado = $this->pagamentos->registrar($registro, $usuario, $validado);

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', $resultado['resumo']);
    }

    /**
     * A linha é procurada dentro da conta, e um id de outra conta é 404 antes de
     * qualquer escrita: estornar o pagamento do colega é a mesma chave que escreve
     * na carteira errada. O que o estorno faz com o saldo e com o estado é de
     * `RegistroDePagamento::estornar()`.
     */
    public function destroy(Request $request, FinancialRecord $registro, Payment $pagamento): RedirectResponse
    {
        if ((int) $pagamento->financial_record_id !== (int) $registro->id) {
            abort(404, 'Este pagamento não pertence a esta conta.');
        }

        $usuario = $request->user();

        $resultado = $this->pagamentos->estornar($registro, $pagamento, $usuario);

        return redirect()
            ->route('financial.show', $registro)
            ->with('status', $resultado['resumo']);
    }
}
