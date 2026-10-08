<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Filtros de listagem num lugar só. Cada módulo tem a própria tela, mas a
 * mecânica é a mesma: termo de busca sobre colunas brancas, igualdade que só
 * vale se o valor estiver na lista permitida, período fechado, ordenação por
 * coluna escolhida e tamanho de página. Nada aqui lê o banco: são peças que
 * montam o Builder do controller.
 *
 * A lista de colunas aceitas em ordenação não é decoração: `ordena` vem da query
 * string, e sem lista fechada um pedido malicioso jogaria coluna inexistente no
 * SQL.
 */
class ListFilters
{
    public const POR_PAGINA = [10, 15, 25, 50, 100];

    /** @param array<int, string> $colunas */
    public static function busca(Builder $query, Request $request, array $colunas, string $param = 'busca'): Builder
    {
        $termo = trim((string) $request->query($param));

        if ($termo === '' || $colunas === []) {
            return $query;
        }

        $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo);
        $como = '%'.$escapado.'%';

        return $query->where(function (Builder $sub) use ($colunas, $como) {
            foreach ($colunas as $coluna) {
                $sub->orWhere($coluna, 'like', $como);
            }
        });
    }

    /** @param array<int, string> $permitidos */
    public static function igual(
        Builder $query,
        Request $request,
        string $param,
        ?string $coluna = null,
        array $permitidos = [],
    ): Builder {
        $valor = trim((string) $request->query($param));

        if ($valor === '' || ($permitidos !== [] && ! in_array($valor, $permitidos, true))) {
            return $query;
        }

        return $query->where($coluna ?? $param, $valor);
    }

    /** Relação (classe) de um modelo: ?tecnico=3 filtra pelo dono da coluna. */
    public static function relacionado(
        Builder $query,
        Request $request,
        string $param,
        string $relacao,
        string $coluna = 'id',
    ): Builder {
        $valor = trim((string) $request->query($param));

        if ($valor === '' || ! ctype_digit($valor)) {
            return $query;
        }

        return $query->whereHas($relacao, fn (Builder $sub) => $sub->where($coluna, (int) $valor));
    }

    public static function periodo(
        Builder $query,
        Request $request,
        string $coluna,
        string $inicioParam = 'inicio',
        string $fimParam = 'fim',
    ): Builder {
        $inicio = static::data($request->query($inicioParam));
        $fim = static::data($request->query($fimParam));

        if ($inicio !== null) {
            $query->where($coluna, '>=', $inicio->startOfDay());
        }

        if ($fim !== null) {
            $query->where($coluna, '<=', $fim->endOfDay());
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $aceitas
     */
    public static function ordenar(
        Builder $query,
        Request $request,
        array $aceitas,
        string $colunaPadrao,
        string $direcaoPadrao = 'asc',
    ): Builder {
        [$coluna, $direcao] = static::ordenacaoAtual($request, $aceitas, $colunaPadrao, $direcaoPadrao);

        return $query->orderBy($coluna, $direcao);
    }

    /** @param array<int, string> $aceitas @return array{0: string, 1: string} */
    public static function ordenacaoAtual(Request $request, array $aceitas, string $colunaPadrao, string $direcaoPadrao = 'asc'): array
    {
        $coluna = (string) $request->query('ordena');

        if (! in_array($coluna, $aceitas, true)) {
            $coluna = $colunaPadrao;
        }

        $pedida = strtolower((string) $request->query('direcao'));

        $direcao = match (true) {
            in_array($pedida, ['asc', 'desc'], true) => $pedida,
            $coluna === $colunaPadrao => $direcaoPadrao,
            default => 'asc',
        };

        return [$coluna, $direcao];
    }

    public static function porPagina(Request $request, int $padrao = 15): int
    {
        $valor = (int) $request->query('por_pagina');

        if (in_array($valor, self::POR_PAGINA, true)) {
            return $valor;
        }

        return in_array($padrao, self::POR_PAGINA, true) ? $padrao : 15;
    }

    /** @param array<int, string> $params @return array<int, string> filtros realmente usados */
    public static function ativos(Request $request, array $params): array
    {
        return array_values(array_filter(
            $params,
            fn (string $param) => trim((string) $request->query($param)) !== '',
        ));
    }

    private static function data(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
