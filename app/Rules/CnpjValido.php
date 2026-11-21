<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CNPJ com os dois dígitos verificadores conferidos — não apenas 14 dígitos. */
class CnpjValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D+/', '', (string) $value);

        if ($cnpj === null || strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('Informe um CNPJ válido.');

            return;
        }

        if ($this->digito($cnpj, 12) !== (int) $cnpj[12] || $this->digito($cnpj, 13) !== (int) $cnpj[13]) {
            $fail('Informe um CNPJ válido.');
        }
    }

    private function digito(string $cnpj, int $posicao): int
    {
        $pesos = $posicao === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $cnpj[$i] * $pesos[$i];
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
