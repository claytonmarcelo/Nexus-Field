@props(['serie' => []])

@php
    $valores = $serie['valores'] ?? [];
    $comparar = $serie['comparar'] ?? [];
    $pontos = count($valores);

    // Menos de dois pontos não é série, é ponto: não há linha a desenhar. E série
    // toda zero também não — o traço raso na base diria "movimento constante"
    // onde o banco respondeu "nada aconteceu". Nesses dois casos o cartão fica só
    // com o número dele, que já é a verdade inteira.
    $extremos = array_merge($valores, $comparar === [] ? [] : $comparar);
    $desenha = $pontos >= 2 && $extremos !== [] && max($extremos) > 0;
@endphp

@if ($desenha)
    @php
        $maior = max($extremos);
        $menor = min($extremos);
        $faixa = ($maior - $menor) ?: 1;
        $passo = 100 / ($pontos - 1);

        // O desenho é geometricamente esticado pela largura do cartão, mas cada
        // ponto continua sendo a contagem do banco naquele dia: nada aqui
        // interpola, suaviza ou inventa o ponto que não foi medido.
        $traco = static fn (array $linha): string => implode(' ', array_map(
            static fn ($valor, $i) => sprintf(
                '%s,%s',
                number_format($i * $passo, 2, '.', ''),
                number_format(30 - (($valor - $menor) / $faixa) * 26, 2, '.', ''),
            ),
            $linha,
            array_keys($linha),
        ));

        $trao = $traco($valores);
        $traoComparar = $comparar === [] ? null : $traco($comparar);
        $area = '0.00,32.00 '.$trao.' 100.00,32.00';

        $numero = static fn ($valor): string => ($serie['prefixo'] ?? '')
            .number_format((float) $valor, (int) ($serie['decimais'] ?? 0), ',', '.');

        // A figura é decorativa para quem vê, mas tem que ser lida por quem não
        // vê: a descrição devolve os três números que o traço conta.
        $descricao = trim((string) ($serie['legenda'] ?? 'Série do período')).'. '
            .'Começa em '.$numero($valores[0]).', termina em '.$numero($valores[$pontos - 1])
            .', maior ponto '.$numero($maior).'.';
    @endphp

    <svg class="nf-spark" viewBox="0 0 100 32" preserveAspectRatio="none"
         role="img" aria-label="{{ $descricao }}">
        <polygon class="nf-spark-area" points="{{ $area }}"></polygon>
        <polyline class="nf-spark-line" points="{{ $trao }}"></polyline>
        @if ($traoComparar !== null)
            <polyline class="nf-spark-line nf-spark-line-b" points="{{ $traoComparar }}"></polyline>
        @endif
    </svg>

    @if ($traoComparar !== null)
        <p class="nf-spark-legenda mb-0">
            <span>{{ $serie['rotulo_a'] ?? 'o período' }}</span>
            <span>{{ $serie['rotulo_b'] ?? 'a comparação' }}</span>
        </p>
    @endif
@endif
