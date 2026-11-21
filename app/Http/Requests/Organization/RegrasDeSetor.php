<?php

namespace App\Http\Requests\Organization;

use App\Models\Department;
use App\Support\StatusCatalog;
use Illuminate\Validation\Rule;

/**
 * A árvore tem três guardas: o Form Request devolve 422 com campo errado, o
 * modelo continua barrando escrita direta e o serviço recalcula os caminhos da
 * subárvore. Nenhuma das três substitui as outras.
 */
trait RegrasDeSetor
{
    /** @return array<string, mixed> */
    private function regrasComunsDeSetor(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => $this->regrasSlug('departments', 'setor'),
            'branch_id' => $this->regraFilial(),
            'parent_id' => $this->regraPai(),
            'description' => ['nullable', 'string', 'max:500'],
            'manager_user_id' => $this->regraUsuario('manager_user_id'),
            'type' => ['required', Rule::in(array_keys(StatusCatalog::options('department_type')))],
            'active' => ['boolean'],
        ];
    }

    /** @return array<int, mixed> */
    private function regraPai(): array
    {
        return [
            'nullable', 'integer',
            function (string $atributo, mixed $valor, callable $falha): void {
                if (blank($valor)) {
                    return;
                }

                $pai = Department::query()->find((int) $valor);

                if ($pai === null) {
                    $falha('O setor pai não existe nesta empresa.');

                    return;
                }

                $atual = $this->route('setor');

                if (is_object($atual) && method_exists($atual, 'getKey')) {
                    if ((int) $pai->id === (int) $atual->getKey()) {
                        $falha('Um setor não pode ser pai de si mesmo.');

                        return;
                    }

                    $prefixo = (string) $atual->path ?: ('/'.$atual->getKey().'/');

                    if (str_starts_with((string) $pai->path, $prefixo)) {
                        $falha('A mudança criaria um ciclo na hierarquia.');

                        return;
                    }
                }

                $filial = $this->input('branch_id');

                if (filled($filial) && $pai->branch_id !== null && (int) $pai->branch_id !== (int) $filial) {
                    $falha('O setor pai pertence a outra filial desta empresa.');

                    return;
                }

                if ((int) $pai->depth + 1 > Department::PROFUNDIDADE_MAXIMA) {
                    $falha('A hierarquia de setores atingiu o limite de profundidade.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return parent::messages() + [
            'parent_id.exists' => 'O setor pai não existe nesta empresa.',
            'type.in' => 'Escolha um tipo de setor da lista.',
        ];
    }
}
