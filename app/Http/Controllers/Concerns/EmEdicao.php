<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Qual peça a ficha deve abrir em modo de edição. A resposta vem do
 * `?editar_*=id`, e a peça é procurada dentro da coleção que já está carregada
 * deste cadastro: um id de outra empresa não existe nesta coleção, então a tela
 * volta ao formulário de inclusão em vez de vazar registro alheio.
 */
trait EmEdicao
{
    /** @param  Collection<int, Model>  $pecas */
    private function emEdicao(Request $request, string $param, Collection $pecas): ?Model
    {
        $id = $request->query($param);

        return ctype_digit((string) $id) ? $pecas->firstWhere('id', (int) $id) : null;
    }
}
