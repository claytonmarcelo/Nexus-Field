<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Formatação de tela em um lugar só: dinheiro em reais com separador brasileiro,
 * datas no formato que o operador lê. Os números vêm do banco já corrigidos; aqui
 * só se desenha.
 */
class Formatters
{
    public const TIME_NULL = '—';

    public static function money(float|string|null $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    public static function decimal(float|string|null $valor, int $casas = 2): string
    {
        return number_format((float) $valor, $casas, ',', '.');
    }

    /**
     * O código da unidade, que é como a operação fala no corredor: "un", "kg", "m".
     * Produto sem unidade cadastrada conta como unidade — o "un" da casa, escrito
     * uma única vez, em vez de repetido em cada folha e controller que desenha saldo.
     */
    public static function unidade(?string $codigo): string
    {
        return strval($codigo ?? 'un');
    }

    /**
     * Tempo de execução lido como o operador lê: minuto até uma hora, hora e
     * resto acima dela. Null é traço, não zero — estimativa que ninguém deu
     * não deve aparecer como serviço de zero minuto.
     */
    public static function duration(?int $minutos): string
    {
        if ($minutos === null) {
            return self::TIME_NULL;
        }

        if ($minutos < 60) {
            return $minutos.' min';
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto === 0
            ? $horas.' h'
            : $horas.' h '.strval($resto).' min';
    }

    public static function date(Carbon|string|null $data): string
    {
        if ($data === null || $data === '') {
            return self::TIME_NULL;
        }

        return Carbon::parse($data)->format('d/m/Y');
    }

    public static function dateTime(Carbon|string|null $data): string
    {
        if ($data === null || $data === '') {
            return self::TIME_NULL;
        }

        return Carbon::parse($data)->format('d/m/Y H:i');
    }

    public static function time(Carbon|string|null $data): string
    {
        if ($data === null || $data === '') {
            return self::TIME_NULL;
        }

        return Carbon::parse($data)->format('H:i');
    }
}
