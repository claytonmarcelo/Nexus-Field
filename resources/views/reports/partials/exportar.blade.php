{{--
    O CSV do recorte inteiro, não da página que está na tela.

    O link leva as datas resolvidas e o resto do que a tela está usando — lado do
    caixa, ângulo do chamado, coluna e direção da ordenação — porque a exportação
    monta o mesmo `Relatorio` a partir da mesma request. Baixar um arquivo que sai
    numa ordem diferente da que a pessoa está olhando é planilha que não bate com a
    tela, e as duas são assinadas.
--}}
@php
    $recorte = array_filter(
        request()->only(['tipo', 'angulo', 'ordena', 'direcao']),
        fn ($valor) => $valor !== null && $valor !== '',
    );
@endphp

@can('reports.export')
    <x-ui.button variant="ghost" size="sm" icon="fa-solid fa-file-csv"
        :href="route('reports.export', array_merge($recorte, [
            'relatorio' => $chave,
            'inicio' => $janela['inicio']->format('Y-m-d'),
            'fim' => $janela['fim']->format('Y-m-d'),
        ]))">
        Exportar CSV
    </x-ui.button>
@endcan
