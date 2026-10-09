<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Geração real de avisos internos. Não é e-mail nem push: é o sino da barra e a
 * central de notificações, gravados na tabela `notifications` desta empresa. Um
 * módulo chama o Notifier no ato de negócio (ordem criada, chamado aberto, item
 * abaixo do ponto de reposição, lançamento vencido) e o destinatário decide a
 * hora de ler.
 */
class Notifier
{
    /** @var array<int, string> tipos conhecidos, para a tela filtrar por vocabulário e não por texto solto */
    public const TIPOS = [
        'ordem.criada', 'ordem.atribuida', 'ordem.concluida', 'ordem.cancelada', 'ordem.atrasada',
        'chamdo.aberto', 'chamdo.resolvido',
        'estoque.baixo', 'financeiro.vencendo',
        'agenda.lembrete',
    ];

    public static function para(
        User $destino,
        string $tipo,
        string $titulo,
        ?string $corpo = null,
        ?string $link = null,
        array $dados = [],
    ): ?Notification {
        if ($destino->status !== 'active') {
            return null;
        }

        return Notification::query()->create([
            'company_id' => $destino->company_id,
            'user_id' => $destino->id,
            'type' => $tipo,
            'title' => $titulo,
            'body' => $corpo,
            'link' => $link,
            'data' => $dados === [] ? null : $dados,
        ]);
    }

    /**
     * Avisa todo mundo da empresa que tem a permissão citada — é assim que
     * "chamado novo" chega a quem pode atender, sem lista de e-mail fixa.
     *
     * @return int avisos gravados
     */
    public static function paraQuemPode(
        string $permissao,
        string $tipo,
        string $titulo,
        ?string $corpo = null,
        ?string $link = null,
        array $dados = [],
        ?User $autor = null,
    ): int {
        $destinos = static::quemPode($permissao);

        $gravados = 0;

        foreach ($destinos as $destino) {
            // Quem praticou o ato de negócio não precisa ouvir o sino do próprio
            // ato: ele acabou de ver o que fez. O aviso toca para quem tem de
            // agir em seguida.
            if ($autor !== null && (int) $destino->id === (int) $autor->id) {
                continue;
            }

            if (static::para($destino, $tipo, $titulo, $corpo, $link, $dados) !== null) {
                $gravados++;
            }
        }

        return $gravados;
    }

    /** @return Collection<int, User> */
    public static function quemPode(string $permissao): Collection
    {
        // O modelo User não tem escopo global de empresa: sem este filtro o sino
        // tocaria atravessando a fronteira do tenant, e aviso que atravessa
        // fronteira não é alerta — é vazamento.
        return User::query()
            ->where('company_id', TenantContext::id())
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', $permissao))
            ->get();
    }

    /**
     * Deduplicador do sino: aquela conta ainda tem um aviso por ler, daquele
     * tipo, apontando para aquela mesma origem? A varredura diária e a falta no
     * central são fatos que se repetem até alguém agir — sem esta pergunta o
     * alerta vira spam, e spam ensina a pessoa a ignorar o sino.
     */
    public static function jaAvisaram(int $usuarioId, string $tipo, ?string $link): bool
    {
        return Notification::query()
            ->where('user_id', $usuarioId)
            ->where('type', $tipo)
            ->whereNull('read_at')
            ->when(
                $link === null,
                fn ($q) => $q->whereNull('link'),
                fn ($q) => $q->where('link', $link),
            )
            ->exists();
    }

    /**
     * O "marcar tudo lido" varre só a bandeja de quem clicou: o alvo é a conta,
     * nunca a empresa.
     *
     * @return int carimbos dados
     */
    public static function marcarTudoComoLida(User $usuario): int
    {
        return Notification::query()
            ->where('user_id', $usuario->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public static function marcarComoLida(Notification $aviso, User $quemLeu): void
    {
        if ((int) $aviso->user_id === $quemLeu->id && $aviso->read_at === null) {
            $aviso->forceFill(['read_at' => now()])->save();
        }
    }
}
