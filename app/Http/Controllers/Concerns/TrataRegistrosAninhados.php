<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Peças que só existem dentro de um cadastro (contato e endereço de um cliente,
 * item de uma ordem de serviço, comentário de um chamado). A rota aninhada mostra
 * os dois IDs, então é preciso conferir que a peça é realmente do pai aberto:
 * sem isso, um id chutado em outra linha da mesma empresa seria editado pela
 * janela errada.
 */
trait TrataRegistrosAninhados
{
    protected function garantirQueEPecaDoCadastro(Model $cadastro, Model $peca, string $relacao): void
    {
        abort_if(
            (int) $peca->getAttribute($relacao.'_id') !== (int) $cadastro->getKey(),
            404,
            'Este registro não pertence ao cadastro aberto.'
        );
    }

    /**
     * Relação polimórfica: o dono é a dupla tipo + id. Conferir só o id deixaria
     * abrir, pela ficha de um cliente, o endereço cujo id coincide com o de outro
     * cadastro de outra tela.
     */
    protected function garantirQueEPerecoDoCadastro(Model $cadastro, Model $peca, string $relacao): void
    {
        $ehDesteDono = $peca->getAttribute($relacao.'_type') === $cadastro->getMorphClass()
            && (int) $peca->getAttribute($relacao.'_id') === (int) $cadastro->getKey();

        abort_unless($ehDesteDono, 404, 'Este registro não pertence ao cadastro aberto.');
    }
}
