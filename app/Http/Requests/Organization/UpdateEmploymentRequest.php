<?php

namespace App\Http\Requests\Organization;

class UpdateEmploymentRequest extends OrganizacaoRequest
{
    use RegrasDeVinculo;

    protected function permissao(): string
    {
        return 'organization.update';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->regrasComunsDeVinculo();
    }
}
