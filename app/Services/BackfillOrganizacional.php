<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employment;
use App\Models\OrganizationScope;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Dá a toda empresa existente uma estrutura organizacional mínima e equivalente
 * ao comportamento atual: matriz, setores básicos, cargos, vínculo dos usuários
 * e alcance que preserva o que eles já enxergavam.
 *
 * Tudo é idempotente: rodar de novo não duplica nada e corrige apenas o alcance
 * de quem mudou de carteira de cliente.
 */
final class BackfillOrganizacional
{
    /** @var array<int, array{slug: string, name: string, parent: string|null, type: string}> */
    private const DEPARTAMENTOS = [
        ['slug' => 'administracao', 'name' => 'Administração', 'parent' => null, 'type' => 'administrativo'],
        ['slug' => 'financeiro', 'name' => 'Financeiro', 'parent' => 'administracao', 'type' => 'financeiro'],
        ['slug' => 'comercial', 'name' => 'Comercial', 'parent' => null, 'type' => 'comercial'],
        ['slug' => 'operacao', 'name' => 'Operação', 'parent' => null, 'type' => 'operacao'],
        ['slug' => 'suporte', 'name' => 'Suporte', 'parent' => 'operacao', 'type' => 'suporte'],
        ['slug' => 'ti', 'name' => 'Tecnologia da Informação', 'parent' => 'administracao', 'type' => 'ti'],
    ];

    /** @var array<int, array{name: string, level: int, role: string}> */
    private const CARGOS = [
        ['name' => 'Diretor', 'level' => 1, 'role' => 'administrator'],
        ['name' => 'Gerente', 'level' => 2, 'role' => 'supervisor'],
        ['name' => 'Supervisor', 'level' => 3, 'role' => 'supervisor'],
        ['name' => 'Analista', 'level' => 4, 'role' => 'employee'],
        ['name' => 'Técnico', 'level' => 5, 'role' => 'technician'],
        ['name' => 'Operacional', 'level' => 6, 'role' => 'employee'],
    ];

    /** @return array<string, int> */
    public function executar(): array
    {
        $totais = ['empresas' => 0, 'filiais' => 0, 'setores' => 0, 'cargos' => 0, 'vinculos' => 0, 'alcances' => 0];

        DB::transaction(function () use (&$totais): void {
            foreach (Company::query()->orderBy('id')->cursor() as $empresa) {
                $this->paraEmpresa($empresa, $totais);
                $totais['empresas']++;
            }
        });

        return $totais;
    }

    /**
     * Estrutura mínima de uma única empresa. Público para que a aprovação de um
     * cadastro use a mesma rotina, na mesma transação, sem segunda fonte de verdade.
     *
     * @param array<string, int> $totais
     */
    public function paraEmpresa(Company $empresa, array &$totais): void
    {
        $filial = Branch::query()->firstOrCreate(
            ['company_id' => $empresa->id, 'slug' => 'matriz'],
            ['name' => 'Matriz', 'type' => 'matriz', 'active' => true],
        );
        $totais['filiais']++;

        $papelPorSlug = Role::query()
            ->where('company_id', $empresa->id)
            ->pluck('id', 'slug');

        $setoresPorSlug = [];

        foreach (self::DEPARTAMENTOS as $setor) {
            $pai = $setor['parent'] === null ? null : ($setoresPorSlug[$setor['parent']] ?? null);

            $registro = Department::query()->firstOrCreate(
                ['company_id' => $empresa->id, 'slug' => $setor['slug']],
                [
                    'branch_id' => $filial->id,
                    'parent_id' => $pai,
                    'name' => $setor['name'],
                    'type' => $setor['type'],
                    'active' => true,
                ],
            );

            $setoresPorSlug[$setor['slug']] = $registro->id;
            $totais['setores']++;
        }

        $cargoPorRole = [];

        foreach (self::CARGOS as $cargo) {
            $registro = Position::query()->firstOrCreate(
                ['company_id' => $empresa->id, 'department_id' => null, 'name' => $cargo['name']],
                [
                    'level' => $cargo['level'],
                    'suggested_role_id' => $papelPorSlug[$cargo['role']] ?? null,
                    'active' => true,
                ],
            );

            $cargoPorRole[$cargo['role']] = $registro->id;
            $totais['cargos']++;
        }

        foreach (User::query()->where('company_id', $empresa->id)->orderBy('id')->cursor() as $usuario) {
            $papel = $usuario->roles()->first()?->slug;
            $setor = match ($papel) {
                'administrator', 'supervisor', 'technician', 'employee' => $setoresPorSlug[
                    $papel === 'administrator' ? 'administracao' : 'operacao'
                ],
                default => null,
            };
            $cargo = $cargoPorRole[$papel] ?? null;

            Employment::query()->firstOrCreate(
                ['company_id' => $empresa->id, 'user_id' => $usuario->id],
                [
                    'branch_id' => $filial->id,
                    'department_id' => $setor,
                    'position_id' => $cargo,
                    'employee_code' => sprintf('NF-%03d-%04d', $empresa->id, $usuario->id),
                    'employment_type' => 'clt',
                    'status' => 'ativo',
                    'started_at' => now()->toDateString(),
                ],
            );
            $totais['vinculos']++;

            // Equivalência exata com o comportamento anterior: conta de cliente
            // via a própria carteira e ficha de campo vê só o seu trabalho.
            // Qualquer outro usuário do escritório continuava vendo a empresa
            // inteira, então recebe escopo `company`.
            $ehCliente = $usuario->client_id !== null;
            $ehTecnico = ! $ehCliente && $usuario->technician !== null;
            $alcance = $ehCliente ? 'client' : ($ehTecnico ? 'self' : 'company');
            $idAlcance = $ehCliente ? $usuario->client_id : ($ehTecnico ? $usuario->id : null);

            OrganizationScope::query()->updateOrCreate(
                [
                    'company_id' => $empresa->id,
                    'user_id' => $usuario->id,
                    'scope' => $alcance,
                ],
                [
                    'scope_id' => $idAlcance,
                    'active' => true,
                ],
            );
            $totais['alcances']++;
        }
    }
}
