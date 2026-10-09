<?php

namespace App\Support;

use Throwable;

/**
 * O motivo de recusa que pode subir para a página de erro.
 *
 * `abort(403, '…')` e `abort(404, '…')` carregam texto escrito por nós para dizer
 * exatamente o que faltou — a casca própria que engolisse esse texto deixaria
 * muda uma recusa que antes aparecia na página crua. O texto que o próprio
 * framework inventa, porém, descreve a casa por dentro (nome de rota, nome de
 * model, termo em inglês), e esse não chega à tela. A lista mora aqui porque a
 * regra é uma só: as cinco páginas de erro leem o mesmo critério.
 */
final class MotivoErro
{
    /**
     * Prefixos de mensagem fabricada pelo framework ou pelo Symfony. Cada um é
     * uma frase que nenhuma linha nossa escreve.
     */
    private const DO_FRAMEWORK = [
        'The route ',
        'No query results',
        'Not found.',
        'This action',
        'CSRF token',
        'Target class ',
        'Call to a member function',
        'Too few',
        'Too many',
    ];

    public static function exibivel(?Throwable $excecao): string
    {
        $motivo = trim((string) $excecao?->getMessage());

        if ($motivo === '') {
            return '';
        }

        foreach (self::DO_FRAMEWORK as $prefixo) {
            if (str_starts_with($motivo, $prefixo)) {
                return '';
            }
        }

        return $motivo;
    }
}
