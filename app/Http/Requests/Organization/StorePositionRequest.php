<?php

namespace App\Http\Requests\Organization;

class StorePositionRequest extends OrganizacaoRequest
{
    use RegrasDeCargo;

    protected function permissao(): string
    {
        return 'organization.create';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->regrasComunsDeCargo();
    }
}
