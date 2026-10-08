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
