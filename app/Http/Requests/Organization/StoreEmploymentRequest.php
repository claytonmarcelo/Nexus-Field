<?php

namespace App\Http\Requests\Organization;

class StoreEmploymentRequest extends OrganizacaoRequest
{
    use RegrasDeVinculo;

    protected function permissao(): string
    {
        return 'organization.create';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->regrasComunsDeVinculo();
    }
}
