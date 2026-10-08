<?php

/*
 * Tradução das mensagens de validação do framework.
 *
 * Só entram aqui as regras que o código do projeto usa hoje; uma nova regra
 * ganha a frase na fase em que passa a ser aplicada.
 */

return [
    'attributes' => [
        'email' => 'e-mail',
        'name' => 'nome',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'remember' => 'manter conectado',
    ],

    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'email' => 'O campo :attribute deve conter um e-mail válido.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',

    'array' => [
        'array' => 'O campo :attribute deve ser uma lista de itens.',
    ],

    'boolean' => [
        'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    ],

    'min' => [
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],

    'password' => [
        'letters' => 'O campo :attribute deve conter pelo menos uma letra.',
        'mixed' => 'O campo :attribute deve conter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'O campo :attribute deve conter pelo menos um número.',
        'symbols' => 'O campo :attribute deve conter pelo menos um símbolo.',
    ],
];
