<?php

namespace App\Http\Requests\Organization;

use App\Models\Employment;
use App\Support\StatusCatalog;
use Illuminate\Validation\Rule;

trait RegrasDeVinculo
{
    /** @return array<string, mixed> */
    private function regrasComunsDeVinculo(): array
    {
        $atual = $this->route('vinculo');

        return [
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->empresaId())
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
                function (string $atributo, mixed $valor, callable $falha) use ($atual): void {
                    $consulta = Employment::query()
                        ->where('user_id', (int) $valor)
                        ->where('status', 'ativo')
                        ->whereNull('ended_at');

                    if (is_object($atual) && method_exists($atual, 'getKey')) {
                        $consulta->whereKeyNot($atual->getKey());
                    }

                    if ($consulta->exists()) {
                        $falha('Esta conta já tem vínculo ativo. Use editar para movimentar a passagem atual.');
                    }
                },
            ],
            'employee_code' => [
                'required', 'string', 'max:32',
                Rule::unique('employments', 'employee_code')
                    ->where(fn ($query) => $query
                        ->where('company_id', $this->empresaId())
                        ->whereNull('deleted_at'))
                    ->ignore($this->idDaRota('vinculo')),
            ],
            'branch_id' => $this->regraFilial(),
            'department_id' => [
                'nullable', 'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->empresaId())
                    ->whereNull('deleted_at')),
            ],
            'position_id' => [
                'nullable', 'integer',
                Rule::exists('positions', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->empresaId())
                    ->whereNull('deleted_at')),
            ],
            'manager_id' => [
                ...$this->regraUsuario('manager_id'),
                function (string $atributo, mixed $valor, callable $falha): void {
                    if (filled($valor) && (int) $valor === (int) $this->input('user_id')) {
                        $falha('Uma conta não pode ser gestora de si mesma.');
                    }
                },
            ],
            'employment_type' => ['required', Rule::in(array_keys(StatusCatalog::options('employment_type')))],
            'status' => ['required', Rule::in(['ativo', 'afastado'])],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return parent::messages() + [
            'user_id.exists' => 'A conta precisa pertencer a esta empresa.',
            'employment_type.in' => 'Escolha um tipo de vínculo da lista.',
            'status.in' => 'Use a ação de desligamento para encerrar um vínculo.',
        ];
    }
}
