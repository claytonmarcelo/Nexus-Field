@use('App\Support\Formatters')

<x-layouts.app
    :title="$relatorio['rotulo']"
    :subtitle="$relatorio['pergunta']"
    :trilha="['Gestão' => route('reports.index'), 'Relatórios' => route('reports.index'), $relatorio['aba'] => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $linhas->total() }} {{ $linhas->total() === 1 ? 'produto movimentado' : 'produtos movimentados' }}
            @if ($linhas->total() > 0)
                · página {{ $linhas->currentPage() }} de {{ max(1, $linhas->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @include('reports.partials.exportar', ['chave' => 'estoque', 'janela' => $janela])
        </div>
    </div>

    @include('reports.partials.abas', ['chave' => 'estoque', 'janela' => $janela, 'disponiveis' => $disponiveis])

    <x-ui.filters :rota="route('reports.estoque')" :limpar="route('reports.estoque')">
        <x-ui.input label="Movimentação a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @include('reports.partials.janela', ['janela' => $janela])

    @if ($linhas->total() === 0)
        <x-ui.card title="Nada para fechar">
            <x-ui.state tone="empty" title="Período sem movimentação"
                text="Nenhum produto teve compra, carga, consumo, devolução ou ajuste registrado nesta janela. Item parado no depósito não aparece aqui: este relatório fecha o que aconteceu com o estoque, e catálogo inteiro sem movimento é outra pergunta — a da listagem de produtos.">
                    @can('products.view')
                        <x-ui.button variant="soft-primary" size="sm" :href="route('products.index')"
                            icon="fa-solid fa-boxes-stacked">Listagem de produtos</x-ui.button>
                    @endcan
                    @can('stock.view')
                        <x-ui.button variant="ghost" size="sm" :href="route('movements.index')"
                            icon="fa-solid fa-right-left">Livro-caixa do estoque</x-ui.button>
                    @endcan
            </x-ui.state>
        </x-ui.card>
    @else
        <x-ui.card title="Fechamento deste período"
            subtitle="Somado no MySQL sobre a janela inteira — cada número é a soma de todas as linhas abaixo, não só desta página.">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Linhas do livro-caixa</span>
                            <strong class="nf-mono">{{ $totais['linhas'] }}</strong>
                        </li>
                        <li>
                            <span>Produtos tocados</span>
                            <strong class="nf-mono">{{ $totais['produtos'] }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Entrou por compra</span>
                            <strong class="nf-mono nf-status-done">{{ Formatters::decimal($totais['compradas']) }}</strong>
                        </li>
                        <li>
                            <span>Saiu por consumo</span>
                            <strong class="nf-mono">{{ Formatters::decimal($totais['consumidas']) }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Custo do que foi consumido</span>
                            <strong class="nf-valor nf-mono">{{ Formatters::money($totais['custo_consumido']) }}</strong>
                        </li>
                        <li>
                            <span>Abaixo do ponto de reposição</span>
                            <strong class="nf-valor nf-mono {{ $totais['abaixo'] > 0 ? 'nf-status-canceled' : 'nf-status-done' }}">
                                {{ $totais['abaixo'] }}
                            </strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <p class="nf-text-muted-2 small mb-0">
                        Compradas e consumidas somam unidades de espécies diferentes — quilograma e unidade na mesma
                        coluna. O número que responde por depósito é o de cada linha, com a própria unidade; este rodapé
                        serve para saber o tamanho do período, não para fazer conta entre produtos.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="mt-3">
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Movimentações do período por produto, com o que entrou, o que saiu, o custo do consumo e o
                        saldo central de hoje
                    </caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="rotulo" rotulo="Produto" padrao="rotulo" />
                            <th scope="col">Unidade</th>
                            <x-ui.sort-link coluna="linhas" rotulo="Movimentações" />
                            <x-ui.sort-link coluna="compradas" rotulo="Compradas" />
                            <th scope="col" class="text-end">Carregadas</th>
                            <x-ui.sort-link coluna="consumidas" rotulo="Consumidas" />
                            <th scope="col" class="text-end">Devolvidas</th>
                            <th scope="col" class="text-end">Ajustadas</th>
                            <x-ui.sort-link coluna="custo_consumido" rotulo="Custo do consumo" />
                            <x-ui.sort-link coluna="saldo_central" rotulo="Saldo central hoje" />
                            <th scope="col" class="text-end">Ponto de reposição</th>
                            <th scope="col" class="text-end">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr>
                                <td>
                                    @can('products.view')
                                        <a class="fw-semibold" href="{{ route('products.show', $linha['chave']) }}">
                                            {{ $linha['nome'] }}
                                        </a>
                                    @else
                                        <span class="fw-semibold">{{ $linha['nome'] }}</span>
                                    @endcan
                                    <div class="nf-text-muted-2 small nf-mono">{{ $linha['sku'] }}</div>
                                </td>
                                <td class="nf-text-muted-2 small">{{ $linha['unidade'] }}</td>
                                <td class="nf-mono">{{ $linha['linhas'] }}</td>
                                <td class="text-end nf-mono">
                                    {{ $linha['compradas'] > 0 ? Formatters::decimal($linha['compradas']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['carregadas'] > 0 ? Formatters::decimal($linha['carregadas']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['consumidas'] > 0 ? Formatters::decimal($linha['consumidas']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['devolvidas'] > 0 ? Formatters::decimal($linha['devolvidas']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['ajustadas'] != 0 ? Formatters::decimal($linha['ajustadas']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['custo_consumido'] > 0 ? Formatters::money($linha['custo_consumido']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono {{ $linha['saldo_central'] < 0 ? 'nf-status-canceled' : '' }}">
                                    {{ Formatters::decimal($linha['saldo_central']) }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['ponto_reposicao'] > 0 ? Formatters::decimal($linha['ponto_reposicao']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end">
                                    @if ($linha['abaixo'])
                                        <span class="nf-status nf-status-canceled">abaixo do ponto</span>
                                    @else
                                        <span class="nf-status nf-status-draft">na medida</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$linhas" rotulo="produtos" />
        </x-ui.card>

        @if ($reposicao->isNotEmpty())
            <x-ui.card class="mt-3" title="Reposição pedida por este período"
                subtitle="Os produtos da tabela acima que estão abaixo do próprio ponto de reposição, do buraco mais fundo para o menos.">
                @php
                    // A régua da barra é o maior falta do grupo, não o maior saldo: sem
                    // ela o item que quase não tem ponto ficaria com a barra cheia e a
                    // leitura de prioridade sairia invertida.
                    $faltas = $reposicao->map(fn (array $linha) => max(0, $linha['ponto_reposicao'] - $linha['saldo_central']));
                    $maiorFalta = max(0.0001, floatval($faltas->max()));
                @endphp

                <ul class="nf-bar-list mb-0">
                    @foreach ($reposicao as $linha)
                        @php($faltando = max(0, $linha['ponto_reposicao'] - $linha['saldo_central']))
                        <li>
                            <div class="nf-bar-head">
                                <span>
                                    {{ $linha['nome'] }}
                                    <span class="nf-text-muted-2 nf-mono">{{ $linha['sku'] }}</span>
                                </span>
                                <span class="nf-mono">
                                    faltam {{ Formatters::decimal($faltando) }}
                                    <span class="nf-text-muted-2">{{ mb_strtolower($linha['unidade']) }}</span>
                                </span>
                            </div>
                            <div class="nf-bar-track" role="img"
                                aria-label="{{ $linha['nome'] }}: saldo {{ Formatters::decimal($linha['saldo_central']) }} de um ponto de {{ Formatters::decimal($linha['ponto_reposicao']) }}">
                                <div class="nf-bar-fill tone-canceled"
                                    style="--nf-bar-width: {{ round($faltando / $maiorFalta * 100, 1) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <p class="nf-text-muted-2 small mt-3 mb-0">
            As colunas de quantidade somam o que aconteceu dentro da janela, cada uma com o sinal do próprio tipo de
            movimentação. O <em>saldo central hoje</em> não: é o saldo de agora, calculado pela mesma subquery da
            listagem de produtos e da ficha, porque saldo de período somando só as linhas da janela daria um número que
            não existe em lugar nenhum. Por isso um produto pode ter consumido pouco e estar negativo — o que pesa no
            saldo é a história inteira, não o mês.
        </p>
    @endif
</x-layouts.app>
