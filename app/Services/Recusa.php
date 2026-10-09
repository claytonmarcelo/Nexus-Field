<?php

namespace App\Services;

/**
 * O domínio disse não, e a frase é feita para a tela.
 *
 * Recusa não é falha técnica: é regra de negócio que o fluxo sempre previu — o salto
 * proibido no estado de uma ordem, a chegada depois de o trabalho ter encerrado, o
 * rascunho que já virou operação. Um serviço lança isto quando a escrita pedida
 * degradaria o registro, e a camada de apresentação devolve o motivo como flash, no
 * mesmo idioma que o usuário lê. É a porta que mantém a recusa em 302: quem escreve
 * fora do fluxo é barrado no serviço, em vez de devolver ao visitante a exceção que
 * o navegador mostraria como 500.
 *
 * O tom diz o peso do "não". `erro` é a escrita proibida; `aviso` é o gesto repetido
 * que não estraga nada — a segunda chegada de quem já está em campo é esse caso, e a
 * tela o pinta sem parecer que alguém fez algo errado.
 */
final class Recusa extends \RuntimeException
{
    private string $tom = 'erro';

    public static function aviso(string $motivo): self
    {
        $recusa = new self($motivo);
        $recusa->tom = 'aviso';

        return $recusa;
    }

    public function tom(): string
    {
        return $this->tom;
    }
}
