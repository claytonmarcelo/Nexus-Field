<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employment;
use App\Models\OrganizationScope;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Política única de alcance organizacional: o papel diz o que a pessoa pode
 * fazer; aqui se decide sobre QUAL conjuntos de dados ela pode agir.
 *
 * A mesma instância alimenta tela, CSV, indicadores, notificações e feeds.
 * Quando não há estrutura gravada, a política devolve o comportamento legado
 * (empresa inteira, carteira de cliente ou trabalho do próprio técnico), o que
 * garante que a migração não faça dado nenhum aparecer ou desaparecer.
 */
final class Alcance
{
    public const EMPRESA = 'company';
    public const FILIAL = 'branch';
    public const SETOR = 'department';
    public const EQUIPE = 'team';
    public const SUBORDINADOS = 'subordinates';
    public const PROPRIO = 'self';
    public const CLIENTE = 'client';

    /** @return Collection<int, OrganizationScope> */
    public static function escopos(User $usuario): Collection
    {
        $gravados = OrganizationScope::query()
            ->where('user_id', $usuario->id)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        if ($gravados->isNotEmpty()) {
            return $gravados;
        }

        if ($usuario->client_id !== null) {
            return collect([new OrganizationScope([
                'scope' => self::CLIENTE,
                'scope_id' => $usuario->client_id,
                'active' => true,
            ])]);
        }

        if ($usuario->technician !== null) {
            return collect([new OrganizationScope([
                'scope' => self::PROPRIO,
                'scope_id' => $usuario->id,
                'active' => true,
            ])]);
        }

        return collect([new OrganizationScope(['scope' => self::EMPRESA, 'active' => true])]);
    }

    public static function tem(User $usuario, string $alcance): bool
    {
        return self::escopos($usuario)->contains(
            fn (OrganizationScope $escopo) => $escopo->scope === $alcance,
        );
    }

    /** @return array<int, int>|null null = empresa inteira */
    public static function idsClientes(User $usuario): ?array
    {
        $escopos = self::escopos($usuario);

        if ($escopos->contains(fn (OrganizationScope $e) => $e->scope === self::EMPRESA)) {
            return null;
        }

        $ids = $escopos
            ->where('scope', self::CLIENTE)
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids === [] ? [] : $ids;
    }

    /**
     * Usuários alcançáveis pelo escopo organizacional. null significa "todos da
     * empresa" — o mesmo que o comportamento atual para quem não tem recorte.
     *
     * @return array<int, int>|null
     */
    public static function idsUsuarios(User $usuario): ?array
    {
        $escopos = self::escopos($usuario);

        if ($escopos->contains(fn (OrganizationScope $e) => $e->scope === self::EMPRESA)) {
            return null;
        }

        $ids = collect();
        $temRecorteDeUsuario = false;

        foreach ($escopos as $escopo) {
            if (! in_array($escopo->scope, [self::EMPRESA, self::CLIENTE], true)) {
                $temRecorteDeUsuario = true;
            }

            $ids = $ids->merge(match ($escopo->scope) {
                self::PROPRIO => [$usuario->id],
                self::CLIENTE => [],
                self::SETOR => self::usuariosDoSetor($usuario, (int) $escopo->scope_id),
                self::FILIAL => self::usuariosDaFilial($usuario, (int) $escopo->scope_id),
                self::SUBORDINADOS => self::subordinados($usuario, (int) ($escopo->scope_id ?: $usuario->id)),
                self::EQUIPE => self::usuariosDaEquipe((int) $escopo->scope_id),
                default => [],
            });
        }

        // Escopo só de cliente não restringe usuários: a restrição já vem pela
        // carteira. Devolver [] aqui apagaria a listagem inteira.
        if (! $temRecorteDeUsuario) {
            return null;
        }

        return $ids->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Só os escopos criados pela estrutura nova estreitam a consulta. Enquanto o
     * usuário tem apenas `company`, `client` ou `self`, os escopos legados de
     * cada modelo (`visiveisPara`) continuam sendo a fonte da verdade — é isso
     * que preserva comissão de ordem, responsável de chamado e subconsultas.
     */
    public static function temRecorteOrganizacional(User $usuario): bool
    {
        return self::escopos($usuario)->contains(
            fn (OrganizationScope $escopo) => in_array($escopo->scope, [
                self::FILIAL, self::SETOR, self::EQUIPE, self::SUBORDINADOS,
            ], true),
        );
    }

    /**
     * Aplica o recorte organizacional a uma consulta já filtrada pelo tenant.
     * Mapa aceito: `cliente`, `usuario`, `tecnico` e `atribuicoes` (relação
     * `whereHas` de técnico, para não perder a comissão legada).
     *
     * Dentro do grupo de pessoa o OR é necessário e fica sempre em closure.
     * Entre grupos a junção é AND: interseção nunca transforma um recorte de
     * setor em acesso a dado de outro setor.
     *
     * @param  array<string, string>  $mapa
     */
    public static function aplicar(Builder $query, User $usuario, array $mapa = []): Builder
    {
        if (! self::temRecorteOrganizacional($usuario)) {
            return $query;
        }

        $usuarios = self::idsUsuarios($usuario) ?? [];
        $condicoes = [];

        $pessoa = [];

        if (isset($mapa['usuario'])) {
            $pessoa[] = fn (Builder $q) => $q->whereIn($mapa['usuario'], $usuarios);
        }

        $tecnicos = [];

        if (isset($mapa['tecnico']) || isset($mapa['atribuicoes'])) {
            $tecnicos = Technician::query()->whereIn('user_id', $usuarios)->pluck('id')->all();
        }

        if (isset($mapa['tecnico'])) {
            $pessoa[] = fn (Builder $q) => $q->whereIn($mapa['tecnico'], $tecnicos);
        }

        if (isset($mapa['atribuicoes'])) {
            $pessoa[] = fn (Builder $q) => $q->whereHas(
                $mapa['atribuicoes'],
                fn (Builder $q) => $q->whereIn('technician_id', $tecnicos),
            );
        }

        if ($pessoa !== []) {
            $condicoes[] = function (Builder $q) use ($pessoa): void {
                $q->where($pessoa[0]);

                foreach (array_slice($pessoa, 1) as $condicao) {
                    $q->orWhere($condicao);
                }
            };
        }

        $clientes = self::idsClientes($usuario);

        if ($clientes !== null && $clientes !== [] && isset($mapa['cliente'])) {
            $condicoes[] = fn (Builder $q) => $q->whereIn($mapa['cliente'], $clientes);
        }

        foreach ($condicoes as $condicao) {
            $query->where($condicao);
        }

        return $query;
    }

    /** @return array<int, int> */
    private static function usuariosDoSetor(User $usuario, int $setorId): array
    {
        $setor = Department::query()
            ->where('company_id', $usuario->company_id)
            ->find($setorId);

        if ($setor === null) {
            return [];
        }

        return Employment::query()
            ->where('company_id', $usuario->company_id)
            ->whereIn('department_id', Department::query()->naArvore($setor)->pluck('id'))
            ->pluck('user_id')
            ->all();
    }

    /** @return array<int, int> */
    private static function usuariosDaFilial(User $usuario, int $filialId): array
    {
        return Employment::query()
            ->where('company_id', $usuario->company_id)
            ->where('branch_id', $filialId)
            ->pluck('user_id')
            ->all();
    }

    /** @return array<int, int> */
    private static function subordinados(User $usuario, int $gestorId): array
    {
        $linhas = Employment::query()
            ->where('company_id', $usuario->company_id)
            ->get(['user_id', 'manager_id']);

        $porGestor = [];

        foreach ($linhas as $linha) {
            $chave = (int) $linha->manager_id;
            $porGestor[$chave][] = (int) $linha->user_id;
        }

        $encontrados = [];
        $fila = [$gestorId];

        while ($fila !== []) {
            $atual = array_shift($fila);

            foreach ($porGestor[$atual] ?? [] as $subordinado) {
                if (isset($encontrados[$subordinado])) {
                    continue;
                }

                $encontrados[$subordinado] = true;
                $fila[] = $subordinado;
            }
        }

        return array_keys($encontrados);
    }

    /** @return array<int, int> */
    private static function usuariosDaEquipe(int $equipeId): array
    {
        return DB::table('team_members')
            ->join('technicians', 'technicians.id', '=', 'team_members.technician_id')
            ->where('team_members.team_id', $equipeId)
            ->whereNull('team_members.left_at')
            ->pluck('technicians.user_id')
            ->all();
    }
}
