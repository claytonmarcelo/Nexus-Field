<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Leitura de ficha: o que a tela de um cadastro mostra do trabalho que aquele
 * registro já gerou. É consulta, não escrita — cada domínio continua dono do que
 * grava — e existe porque cliente e técnico precisam das mesmas duas coisas sem
 * que cada controller reinvente a régua: a janela de agenda que ainda vem e a
 * trilha de atos que já aconteceu.
 *
 * Quem chama entrega um Builder já restrito ao alcance do usuário
 * (`visiveisPara`) e ao vínculo da ficha; é dele que saem o total, as próximas
 * janelas e a última. Nada aqui conta linha de outra empresa: o escopo global do
 * `TenantContext` segue valendo nos modelos consultados.
 */
final class FichaHistorico
{
    /** Quantas linhas de um domínio a ficha comporta antes de virar lista. */
    public const REGISTROS_NA_FICHA = 6;

    /** Janelas de agenda mostradas na frente da ficha. */
    public const JANELAS_NA_FICHA = 4;

    /**
     * As janelas de agenda de um vínculo, em três números honestos: as que ainda
     * vêm, a última que já foi e o total de compromissos daquele alcance.
     *
     * "Ainda vem" é compromisso agendado com `ends_at` no futuro. Concluído e
     * cancelado não são promessa — são fato — e por isso não ocupam a frente da
     * ficha; quem só tem janela passada não recebe uma agenda em branco.
     *
     * @param  Builder<Appointment>  $compromissos
     * @return array{proximas: Collection<int, Appointment>, passada: ?Appointment, total: int}
     */
    public static function janelas(Builder $compromissos, int $limite = self::JANELAS_NA_FICHA): array
    {
        // A cópia local já sai com as relações que a ficha pinta: sem ela, cada
        // janela custaria três consultas na tela.
        $compromissos = $compromissos->with(['technician:id,name', 'client:id,name', 'serviceOrder:id,number']);

        $proximas = (clone $compromissos)
            ->where('status', 'scheduled')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($limite)
            ->get();

        $passada = (clone $compromissos)
            ->whereNotIn('id', $proximas->pluck('id')->push(0)->all())
            ->latest('starts_at')
            ->first();

        return [
            'proximas' => $proximas,
            'passada' => $passada,
            'total' => (clone $compromissos)->count(),
        ];
    }

    /**
     * Os últimos atos da trilha sobre este registro exato, e não sobre a tela
     * aberta ao lado dele: a ficha do cliente mostra o que mexeu na linha do
     * cliente; um contato gravado ali dentro tem `entity_id` próprio e vive na
     * trilha do contato.
     *
     * A consulta anda pelo `class_basename` e pelo id como string porque é assim
     * que o `Auditor` grava.
     *
     * @return Collection<int, AuditLog>
     */
    public static function trilha(Model $registro, int $limite = 8): Collection
    {
        return AuditLog::query()
            ->where('entity_type', class_basename($registro))
            ->where('entity_id', strval($registro->getKey()))
            ->latest('created_at')
            ->limit($limite)
            ->get();
    }
}
