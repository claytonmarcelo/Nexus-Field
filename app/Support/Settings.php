<?php

namespace App\Support;

use App\Models\CompanySetting;

/**
 * A leitura e a escrita do que a empresa escolheu. Todo valor pedido aqui sai
 * com tipo certo e com o padrão do catálogo quando a empresa nunca opinou —
 * quem consome (o raio do check-in, a janela da varredura) não precisa saber
 * se a linha existe na tabela. A chave é de quem escreve, o padrão é de quem
 * declara: as duas coisas moram no SettingsCatalog, uma só vez.
 */
final class Settings
{
    public static function valor(string $chave): mixed
    {
        $guardado = CompanySetting::valueFor($chave);
        $padrao = SettingsCatalog::PREFERENCIAS[$chave]['padrao'] ?? null;

        if ($guardado === null || $guardado === '') {
            return $padrao;
        }

        if (is_bool($padrao)) {
            return filter_var($guardado, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_int($padrao)) {
            return is_numeric($guardado) ? (int) $guardado : $padrao;
        }

        if (is_float($padrao)) {
            return is_numeric($guardado) ? (float) $guardado : $padrao;
        }

        return $guardado;
    }

    public static function inteiro(string $chave): int
    {
        return (int) self::valor($chave);
    }

    public static function ligado(string $chave): bool
    {
        return (bool) self::valor($chave);
    }

    /**
     * Grava a escolha da empresa, chave a chave. Null é "sem opinião": a linha
     * sai da tabela e a leitura volta a responder o padrão da casa — deixar uma
     * opinião nula guardada seria fingir que a empresa escolheu zero.
     *
     * @param  array<string, mixed>  $valores  chave => valor já validado
     */
    public static function gravar(array $valores): void
    {
        foreach ($valores as $chave => $valor) {
            if ($valor === null) {
                CompanySetting::query()
                    ->where('company_id', TenantContext::id())
                    ->where('key', $chave)
                    ->delete();

                continue;
            }

            CompanySetting::put($chave, $valor);
        }
    }
}
