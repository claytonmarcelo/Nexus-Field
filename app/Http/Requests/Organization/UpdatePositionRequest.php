<?php

namespace App\Http\Requests\Organization;

class UpdatePositionRequest extends OrganizacaoRequest
{
    use RegrasDeCargo;

    protected function permissao(): string
    {
        return 'organization.update';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->regrasComunsDeCargo();
    }
}
