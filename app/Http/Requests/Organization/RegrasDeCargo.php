<?php

namespace App\Http\Requests\Organization;

use Illuminate\Validation\Rule;

trait RegrasDeCargo
{
    /** @return array<string, mixed> */
    private function regrasComunsDeCargo(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('positions', 'name')
                    ->where(function ($query): void {
                        $query->where('company_id', $this->empresaId())->whereNull('deleted_at');

                        $departamento = $this->input('department_id');

                        if ($departamento === null || $departamento === '') {
                            $query->whereNull('department_id');
                        } else {
                            $query->where('department_id', (int) $departamento);
                        }
                    })
                    ->ignore($this->idDaRota('cargo')),
            ],
            'department_id' => [
                'nullable', 'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->empresaId())
                    ->whereNull('deleted_at')),
            ],
            'level' => ['required', 'integer', 'min:1', 'max:9'],
            'description' => ['nullable', 'string', 'max:500'],
            'suggested_role_id' => [
                'nullable', 'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->empresaId())),
            ],
            'active' => ['boolean'],
        ];
    }
}
