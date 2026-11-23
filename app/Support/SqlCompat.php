<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Expressoes SQL que mudam entre MySQL e Postgres.
 *
 * O projeto roda em MySQL no local e em Lakebase Postgres na Vercel. Manter as
 * diferencas em um unico lugar evita que relatorios e metricas discordem so por
 * causa do dialeto do banco.
 */
final class SqlCompat
{
    public static function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /** Intervalo de horas a partir de uma expressao numerica. */
    public static function intervaloHoras(string $horas): string
    {
        return self::isPostgres()
            ? "make_interval(hours => ({$horas}))"
            : "interval ({$horas}) hour";
    }

    /** Horas entre duas colunas de data/hora. */
    public static function diferencaHoras(string $inicio, string $fim): string
    {
        return self::isPostgres()
            ? "extract(epoch from ({$fim} - {$inicio})) / 3600.0"
            : "timestampdiff(hour, {$inicio}, {$fim})";
    }

    /** Minutos entre duas colunas de data/hora. */
    public static function diferencaMinutos(string $inicio, string $fim): string
    {
        return self::isPostgres()
            ? "extract(epoch from ({$fim} - {$inicio})) / 60.0"
            : "timestampdiff(minute, {$inicio}, {$fim})";
    }

    /** Mes no formato YYYY-MM. */
    public static function anoMes(string $coluna): string
    {
        return self::isPostgres()
            ? "to_char({$coluna}, 'YYYY-MM')"
            : "date_format({$coluna}, '%Y-%m')";
    }

    /** Ordem estavel das prioridades, identica nos dois bancos. */
    public static function ordemPrioridade(): string
    {
        return "case priority when 'urgent' then 1 when 'high' then 2 "
            ."when 'normal' then 3 when 'low' then 4 else 5 end";
    }
}
