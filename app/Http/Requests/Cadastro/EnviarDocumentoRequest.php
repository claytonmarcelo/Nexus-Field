<?php

namespace App\Http\Requests\Cadastro;

use App\Models\CompanyDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnviarDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(CompanyDocument::TIPOS)],
            'arquivo' => ['required', 'file', 'max:5120', 'mimes:pdf,png,jpg,jpeg'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'arquivo.max' => 'O arquivo passa de 5 MB.',
            'arquivo.mimes' => 'Envie apenas PDF, PNG ou JPG.',
        ];
    }
}
