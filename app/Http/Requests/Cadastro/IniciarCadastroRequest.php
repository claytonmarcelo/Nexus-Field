<?php

namespace App\Http\Requests\Cadastro;

use App\Rules\CnpjValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Etapa 1: dados da empresa e do administrador inicial. */
class IniciarCadastroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', 'max:18', new CnpjValido],
            'razao_social' => ['required', 'string', 'max:191'],
            'nome_fantasia' => ['nullable', 'string', 'max:191'],
            'ie' => ['nullable', 'string', 'max:32'],
            'im' => ['nullable', 'string', 'max:32'],
            'cnae_main' => ['nullable', 'string', 'max:16'],
            'natureza_juridica' => ['nullable', 'string', 'max:16'],
            'porte' => ['nullable', Rule::in(['mei', 'micro', 'pequena', 'media', 'grande'])],
            'abertura' => ['nullable', 'date', 'before_or_equal:today'],
            'regime_tributario' => ['nullable', Rule::in(['simples_nacional', 'lucro_presumido', 'lucro_real'])],
            'segmento' => ['nullable', 'string', 'max:64'],
            'funcionarios' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'tecnicos_campo' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:191'],
            'numero' => ['nullable', 'string', 'max:16'],
            'complemento' => ['nullable', 'string', 'max:64'],
            'bairro' => ['nullable', 'string', 'max:64'],
            'cidade' => ['nullable', 'string', 'max:64'],
            'uf' => ['nullable', 'string', 'size:2'],
            'responsavel_nome' => ['nullable', 'string', 'max:191'],
            'responsavel_cpf' => ['nullable', 'string', 'max:14'],
            'responsavel_email' => ['nullable', 'email', 'max:191'],
            'admin_nome' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'telefone' => ['required', 'string', 'max:24'],
            'aceite_termos' => ['required', 'accepted'],
            'aceite_privacidade' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'aceite_termos.accepted' => 'Aceite os termos de uso para continuar.',
            'aceite_privacidade.accepted' => 'Aceite a política de privacidade para continuar.',
        ];
    }
}
