{{--
    A janela que o fechado realmente usou.

    `Relatorio::janela()` resolve o período antes de qualquer consulta: sem datas
    é o mês corrente, com uma só ela ganha o lado que falta, invertida se troca, e
    acima do teto o início entra para dentro. Nada disso está nos campos de data da
    barra de filtros — eles continuam vazios — então é esta faixa que responde a
    pergunta que todo fechado levanta: "de quando a quando é este número?".

    O aviso de corte vem junto porque período podado em silêncio é relatório que
    mente: a tela diz quantos dias ficaram de fora e o que fazer com eles.
--}}
<div class="nf-relatorio-janela">
    <p>
        <span class="nf-relatorio-janela-rotulo">Período fechado</span>
        <strong class="nf-mono">{{ $janela['etiqueta'] }}</strong>
    </p>

    @if ($janela['aviso'] !== null)
        <p class="nf-relatorio-aviso">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>{{ $janela['aviso'] }}</span>
        </p>
    @endif
</div>
