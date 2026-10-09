<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Escrita única na trilha de auditoria. Toda ação de usuário autenticado passa
 * por aqui: o registro guarda a empresa, quem fez (com o nome congelado no
 * momento, porque o usuário pode mudar de nome ou sair depois), o quê, em quê,
 * o que mudou de valor, o IP e o navegador.
 *
 * Em console sem sessão (seeders, migrate) não há autor, então nada é gravado:
 * auditoria conta atos de pessoas, não a carga de demonstração.
 */
class Auditor
{
    /** @var array<int, string> Colunas que jamais entram no diff de mudanças. */
    private const NUNCA_AUDITADO = [
        'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at', 'last_login_at',
    ];

    private static bool $silenciado = false;

    /** Roda um serviço (por exemplo uma migration de demonstração) sem deixar rastro. */
    public static function silenciar(callable $servico): mixed
    {
        $anterior = static::$silenciado;
        static::$silenciado = true;

        try {
            return $servico();
        } finally {
            static::$silenciado = $anterior;
        }
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $mudancas  campo => [antes, depois]
     */
    public static function gravar(
        string $acao,
        Model|string|null $entidade = null,
        array $mudancas = [],
        ?string $descricao = null,
    ): void {
        if (static::$silenciado || Auth::guest()) {
            return;
        }

        $usuario = Auth::user();
        $pedido = request();

        $tipo = $entidade instanceof Model ? class_basename($entidade) : $entidade;
        $identificador = $entidade instanceof Model ? (string) $entidade->getKey() : null;

        AuditLog::query()->create([
            'company_id' => static::empresaDa($entidade),
            'user_id' => $usuario->id,
            'user_name' => $usuario->name,
            'action' => Str::limit($acao, 64, ''),
            'entity_type' => $tipo === null ? null : Str::limit($tipo, 128, ''),
            'entity_id' => $identificador === null ? null : Str::limit($identificador, 64, ''),
            'description' => $descricao === null ? null : Str::limit($descricao, 255, ''),
            // O diff entra cru: a coluna `changes` tem cast `array`, que é quem
            // codifica. Codificar aqui também era dupla codificação — a trilha
            // voltava lida como string JSON dentro de string JSON.
            'changes' => $mudancas === [] ? null : static::limpar($mudancas),
            'ip_address' => $pedido?->ip(),
            'user_agent' => $pedido === null ? null : Str::limit((string) $pedido->userAgent(), 255, ''),
            'created_at' => now(),
        ]);
    }

    /** @return array<string, array{0: mixed, 1: mixed}> só o que de fato mudou */
    public static function diferenca(Model $modelo): array
    {
        return collect($modelo->getChanges())
            ->except(self::NUNCA_AUDITADO)
            ->map(fn ($depois, $campo) => [$modelo->getOriginal($campo), $depois])
            ->filter(fn (array $par) => (string) $par[0] !== (string) $par[1])
            ->all();
    }

    private static function empresaDa(Model|string|null $entidade): ?int
    {
        if ($entidade instanceof Model && $entidade->getAttribute('company_id') !== null) {
            return (int) $entidade->getAttribute('company_id');
        }

        return TenantContext::id() ?? Auth::user()?->company_id;
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $mudancas
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private static function limpar(array $mudancas): array
    {
        $saida = [];

        foreach ($mudancas as $campo => $par) {
            if (in_array($campo, self::NUNCA_AUDITADO, true)) {
                continue;
            }

            $saida[$campo] = [
                static::escalar($par[0] ?? null),
                static::escalar($par[1] ?? null),
            ];
        }

        return $saida;
    }

    private static function escalar(mixed $valor): mixed
    {
        if ($valor === null || is_scalar($valor)) {
            return $valor;
        }

        return Str::limit(json_encode($valor, JSON_UNESCAPED_UNICODE), 120, '');
    }
}
