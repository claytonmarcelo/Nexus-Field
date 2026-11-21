<?php

namespace App\Http\Requests\Organization;

class UpdateDepartmentRequest extends OrganizacaoRequest
{
    use RegrasDeSetor;

    protected function permissao(): string
    {
        return 'organization.update';
    }

    protected function prepareForValidation(): void
    {
        $this->derivarSlug();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->regrasComunsDeSetor();
    }
}
