{{--
    Cabeçalho de tabela que ordena de verdade: o clique troca coluna e direção
    na query string (com page=1, porque ordenar a página 4 por outro campo é
    outra pergunta). O `aria-sort` no <th> é o que diz ao leitor de tela qual é
    a ordem atual, e a seta no ícone é a mesma informação para quem vê.
--}}
@props([
    'coluna',
    'rotulo',
    'padrao' => null,
])

@php
    $atual = (string) request('ordena', $padrao);
    $direcao = strtolower((string) request('direcao')) === 'desc' ? 'desc' : 'asc';
    $proxima = $atual === $coluna && $direcao === 'asc' ? 'desc' : 'asc';

    $estado = match (true) {
        $atual !== $coluna => 'none',
        $direcao === 'desc' => 'descending',
        default => 'ascending',
    };

    $icone = match (true) {
        $atual !== $coluna => 'fa-solid fa-sort',
        $direcao === 'desc' => 'fa-solid fa-arrow-down-wide-short',
        default => 'fa-solid fa-arrow-up-wide-short',
    };

    $url = request()->fullUrlWithQuery(['ordena' => $coluna, 'direcao' => $proxima, 'page' => 1]);
@endphp

<th scope="col" aria-sort="{{ $estado }}" {{ $attributes->merge(['class' => 'nf-th-sort']) }}>
    <a class="nf-sort-link{{ $atual === $coluna ? ' is-active' : '' }}" href="{{ $url }}">
        {{ $rotulo }}
        <i class="{{ $icone }}" aria-hidden="true"></i>
        <span class="visually-hidden">
            @if ($atual === $coluna)
                (ordenando por {{ mb_strtolower($rotulo) }}, {{ $direcao === 'desc' ? 'decrescente' : 'crescente' }} — clique para {{ $proxima === 'desc' ? 'decrescente' : 'crescente' }})
            @else
                (ordenar por {{ mb_strtolower($rotulo) }})
            @endif
        </span>
    </a>
</th>
