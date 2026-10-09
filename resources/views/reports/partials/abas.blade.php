{{--
    Vai e volta entre os fechamentos sem perder o período: cada link leva as duas
    datas resolvidas, não as digitadas — assim o recorte cortado pelo teto continua
    cortado do mesmo jeito na tela ao lado, em vez de mudar de tamanho num clique.

    Só entram na fila os relatórios que esta conta pode abrir. Um pill para uma rota
    que responderia 403 não é navegação, é porta trancada com maçanete brilhante.
--}}
@php
    $periodo = [
        'inicio' => $janela['inicio']->format('Y-m-d'),
        'fim' => $janela['fim']->format('Y-m-d'),
    ];
@endphp

<div class="nf-relatorio-abas">
    <ul class="nav nav-pills mb-0">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('reports.index', $periodo) }}">
                <i class="fa-solid fa-table-cells-large me-2" aria-hidden="true"></i>Resumo do período
            </a>
        </li>

        @foreach ($disponiveis as $outra => $entrada)
            <li class="nav-item">
                <a class="nav-link {{ $outra === $chave ? 'active' : '' }}"
                    @if ($outra === $chave) aria-current="page" @endif
                    href="{{ route($entrada['rota'], $periodo) }}">
                    {{ $entrada['aba'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
