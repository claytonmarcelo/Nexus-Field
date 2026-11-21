<?php

namespace App\Http\Requests\Organization;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Base dos formulários de estrutura interna. A empresa nunca vem do
 * formulário: ela é resolvida pelo middleware antes da rota trocar o parâmetro
 * pelo modelo. Aqui só se declaram regras já dentro dessa fronteira.
 */
abstract class OrganizacaoRequest extends FormRequest
{
    abstract protected function permissao(): string;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->permissao()) ?? false;
    }

    protected function empresaId(): int
    {
        return (int) TenantContext::id();
    }

    /** @return array<int, mixed> */
    protected function regrasSlug(string $tabela, string $parametro): array
    {
        return [
            'required', 'string', 'max:120',
            Rule::unique($tabela, 'slug')
                ->where(fn ($query) => $query
                    ->where('company_id', $this->empresaId())
                    ->whereNull('deleted_at'))
                ->ignore($this->idDaRota($parametro)),
        ];
    }

    /** @return array<int, mixed> */
    protected function regraUsuario(string $campo): array
    {
        return [
            'nullable', 'integer',
            Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('company_id', $this->empresaId())
                ->where('status', 'active')
                ->whereNull('deleted_at')),
        ];
    }

    /** @return array<int, mixed> */
    protected function regraFilial(): array
    {
        return [
            'nullable', 'integer',
            Rule::exists('branches', 'id')->where(fn ($query) => $query
                ->where('company_id', $this->empresaId())
                ->whereNull('deleted_at')),
        ];
    }

    protected function derivarSlug(): void
    {
        if (filled($this->input('name'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    protected function idDaRota(string $nome): ?int
    {
        $parametro = $this->route($nome);

        if ($parametro === null) {
            return null;
        }

        return (int) (is_object($parametro) && method_exists($parametro, 'getKey')
            ? $parametro->getKey()
            : $parametro);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Esta empresa já tem um cadastro com este nome.',
            'name.unique' => 'Este nome já está em uso nesta empresa.',
            'employee_code.unique' => 'Este código de funcionário já está em uso nesta empresa.',
            'manager_user_id.exists' => 'O gestor precisa ser uma conta ativa desta empresa.',
            'branch_id.exists' => 'A filial precisa pertencer a esta empresa.',
            'department_id.exists' => 'O setor precisa pertencer a esta empresa.',
            'position_id.exists' => 'O cargo precisa pertencer a esta empresa.',
            'suggested_role_id.exists' => 'O papel sugerido precisa pertencer a esta empresa.',
        ];
    }
}
