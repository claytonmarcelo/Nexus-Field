<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportação em CSV desenhada direto da consulta, sem biblioteca externa. O
 * arquivo nasce na resposta e nunca no disco: dado de uma empresa hora fica em
 * /public por esquecimento, e streaming não tem esse risco.
 *
 * Separador `;` e BOM no começo são escolha, não acaso: é assim que o Excel em
 * português abre acento e coluna sem assistente de importação. O BOM existe só
 * aqui, nos bytes do download — os arquivos-fonte do projeto seguem UTF-8 sem
 * BOM.
 */
class Export
{
    /** @param  array<int, string>  $cabecalho  @param  iterable<int, array<int, mixed>>  $linhas  */
    public static function csv(string $nomeBase, array $cabecalho, iterable $linhas): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabecalho, $linhas): void {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, $cabecalho, ';');

            foreach ($linhas as $linha) {
                fputcsv($saida, array_map(static::celula(...), $linha), ';');
            }

            fclose($saida);
        }, static::nome($nomeBase), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function celula(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if ($valor instanceof \BackedEnum) {
            return (string) $valor->value;
        }

        if (is_bool($valor)) {
            return $valor ? 'sim' : 'não';
        }

        if ($valor instanceof \DateTimeInterface) {
            return Formatters::dateTime($valor);
        }

        $texto = str_replace(["\r\n", "\r"], "\n", trim((string) $valor));

        // Injeção de CSV: uma célula de texto que começa com =, +, - ou @ é lida
        // como fórmula pelo Excel. O apóstrofo devolve o conteúdo como texto, que
        // é o que um relatório sempre quis ser. Só o texto entra na conta: um
        // número negativo é saldo, não fórmula, e perderia a aritmética da coluna.
        if (is_string($valor) && $texto !== '' && preg_match('/^[=+\-@]/', $texto)) {
            return "'".$texto;
        }

        return $texto;
    }

    private static function nome(string $base): string
    {
        $carimbo = now()->format('Y-m-d-H-i');

        return 'nexusfield-'.str($base)->slug('-').'-'.$carimbo.'.csv';
    }
}
