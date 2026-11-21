<?php

namespace App\Http\Requests\Organization;

use Illuminate\Validation\Rule;

class StoreBranchRequest extends OrganizacaoRequest
{
    protected function permissao(): string
    {
        return 'organization.create';
    }

    protected function prepareForValidation(): void
    {
        $this->derivarSlug();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => $this->regrasSlug('branches', 'filial'),
            'type' => ['required', Rule::in(['matriz', 'filial'])],
            'manager_user_id' => $this->regraUsuario('manager_user_id'),
            'active' => ['boolean'],
        ];
    }
}
