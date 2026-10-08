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
        'ordem.criada', 'ordem.concluida', 'ordem.cancelada', 'ordem.atrasada',
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
    ): int {
        $destinos = static::quemPode($permissao);

        $gravados = 0;

        foreach ($destinos as $destino) {
            if (static::para($destino, $tipo, $titulo, $corpo, $link, $dados) !== null) {
                $gravados++;
            }
        }

        return $gravados;
    }

    /** @return Collection<int, User> */
    public static function quemPode(string $permissao): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', $permissao))
            ->get();
    }

    public static function marcarComoLida(Notification $aviso, User $quemLeu): void
    {
        if ((int) $aviso->user_id === $quemLeu->id && $aviso->read_at === null) {
            $aviso->forceFill(['read_at' => now()])->save();
        }
    }
}
