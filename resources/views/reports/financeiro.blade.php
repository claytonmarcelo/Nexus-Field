@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="$relatorio['rotulo']"
    :subtitle="$relatorio['pergunta']"
    :trilha="['Gestão' => route('reports.index'), 'Relatórios' => route('reports.index'), $relatorio['aba'] => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $linhas->total() }} {{ $linhas->total() === 1 ? 'categoria' : 'categorias' }}
            @if ($linhas->total() > 0)
                · página {{ $linhas->currentPage() }} de {{ max(1, $linhas->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @include('reports.partials.exportar', ['chave' => 'financeiro', 'janela' => $janela])
        </div>
    </div>

    @include('reports.partials.abas', ['chave' => 'financeiro', 'janela' => $janela, 'disponiveis' => $disponiveis])

    <x-ui.filters :rota="route('reports.financeiro')" :limpar="route('reports.financeiro')">
        <x-ui.input label="Vencendo a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />

        <x-ui.select label="Lado do caixa" name="tipo" :opcoes="$tipos" :value="request('tipo')"
            placeholder="Receita e despesa" data-nf-autosubmit />
    </x-ui.filters>

    @include('reports.partials.janela', ['janela' => $janela])

    @if ($linhas->total() === 0)
        <x-ui.card title="Nada para fechar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma conta nem nenhum pagamento nesta janela"
                    text="Nem vencimento, nem dinheiro mudou de mão dentro do período com estes filtros. Alargue a janela, troque o lado do caixa ou consulte de novo sem filtro para ver o que a carteira tem.">
                    <x-ui.button variant="ghost" size="sm" :href="route('reports.financeiro')"
                        icon="fa-solid fa-rotate-left">Limpar filtros</x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Período sem fatos"
                    text="Nenhuma conta vence nesta janela e nenhum pagamento foi registrado nela. O fechado de um mês sem movimento é isso: zero nas duas metades — não uma carteira vazia, que é outra pergunta.">
                    @can('financial.view')
                        <x-ui.button variant="soft-primary" size="sm" :href="route('financial.index')"
                            icon="fa-solid fa-scale-balanced">Abrir a carteira</x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card title="Fechamento deste período"
            subtitle="Somado no MySQL sobre a janela inteira — cada número é a soma de todas as linhas abaixo, não só desta página.">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Contas que vencem</span>
                            <strong class="nf-valor nf-mono">{{ $totais['contas'] }}</strong>
                        </li>
                        <li>
                            <span>Valor previsto delas</span>
                            <strong class="nf-valor nf-mono">{{ Formatters::money($totais['previsto']) }}</strong>
                        </li>
                        <li>
                            <span>Já baixado dessas contas</span>
                            <strong class="nf-valor nf-mono nf-status-done">{{ Formatters::money($totais['pago']) }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Ainda em aberto</span>
                            <strong class="nf-valor nf-mono">{{ Formatters::money($totais['em_aberto']) }}</strong>
                        </li>
                        <li>
                            <span>Vencidas do período</span>
                            <strong class="nf-valor nf-mono {{ $totais['vencidas'] > 0 ? 'nf-status-canceled' : '' }}">
                                {{ $totais['vencidas'] }}
                            </strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <dl class="nf-total-linha mb-0">
                        <dt>Dinheiro que entrou ou saiu</dt>
                        <dd>{{ Formatters::money($totais['dinheiro']) }}</dd>
                    </dl>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <p class="nf-text-muted-2 small mb-0">
                        As duas somas olham datas diferentes: <em>previsto</em> e <em>baixado</em> vêm das contas que
                        vencem nesta janela, e <em>dinheiro</em> vem dos pagamentos com data dentro dela — inclusive o
                        de uma conta que venceu em outro mês. É por isso que os dois lados não fecham iguais.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="mt-3">
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Contas a receber e a pagar por categoria no período, com o previsto, o que já foi baixado e o
                        dinheiro que efetivamente mudou de mão
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Lado do caixa</th>
                            <x-ui.sort-link coluna="rotulo" rotulo="Categoria" />
                            <x-ui.sort-link coluna="contas" rotulo="Contas que vencem" />
                            <x-ui.sort-link coluna="previsto" rotulo="Valor previsto" />
                            <x-ui.sort-link coluna="pago" rotulo="Já baixado" />
                            <x-ui.sort-link coluna="em_aberto" rotulo="Ainda em aberto" />
                            <th scope="col" class="text-end">Vencidas</th>
                            <x-ui.sort-link coluna="dinheiro" rotulo="Dinheiro no período" />
                            <th scope="col" class="text-end">Pagamentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr>
                                <td>
                                    <span class="{{ StatusCatalog::badge('financial_type', $linha['tipo']) }}">
                                        {{ StatusCatalog::label('financial_type', $linha['tipo']) }}
                                    </span>
                                </td>
                                <td class="fw-semibold">{{ $linha['rotulo'] }}</td>
                                <td class="nf-mono">{{ $linha['contas'] === 0 ? Formatters::TIME_NULL : $linha['contas'] }}</td>
                                <td class="text-end nf-mono">
                                    {{ $linha['contas'] === 0 ? Formatters::TIME_NULL : Formatters::money($linha['previsto']) }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['pago'] === 0 ? Formatters::TIME_NULL : Formatters::money($linha['pago']) }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['em_aberto'] === 0 ? Formatters::TIME_NULL : Formatters::money($linha['em_aberto']) }}
                                </td>
                                <td class="text-end nf-mono {{ $linha['vencidas'] > 0 ? 'nf-status-canceled' : '' }}">
                                    {{ $linha['vencidas'] === 0 ? Formatters::TIME_NULL : $linha['vencidas'] }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['dinheiro'] === 0 ? Formatters::TIME_NULL : Formatters::money($linha['dinheiro']) }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['pagamentos'] === 0 ? Formatters::TIME_NULL : $linha['pagamentos'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$linhas" rotulo="categorias" />
        </x-ui.card>

        @if ($metodos->isNotEmpty())
            <x-ui.card class="mt-3" title="Dinheiro por forma de pagamento"
                subtitle="Pagamentos com data dentro da janela, não importando em que mês a conta venceu.">
                @php($maior = max($metodos->pluck('total')->all()))

                <ul class="nf-bar-list mb-0">
                    @foreach ($metodos as $metodo)
                        <li>
                            <div class="nf-bar-head">
                                <span>
                                    {{ $metodo['metodo'] }}
                                    <span class="nf-text-muted-2">
                                        · {{ $metodo['pagamentos'] }} {{ $metodo['pagamentos'] === 1 ? 'pagamento' : 'pagamentos' }}
                                    </span>
                                </span>
                                <span class="nf-mono">{{ Formatters::money($metodo['total']) }}</span>
                            </div>
                            <div class="nf-bar-track" role="img"
                                aria-label="{{ $metodo['metodo'] }}: {{ Formatters::money($metodo['total']) }}">
                                <div class="nf-bar-fill tone-done"
                                    style="--nf-bar-width: {{ $maior > 0 ? round($metodo['total'] / $maior * 100, 1) : 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <p class="nf-text-muted-2 small mt-3 mb-0">
            Categoria sem conta e sem pagamento no período não aparece aqui: o vocabulário do cadastro define a ordem
            das linhas, não o tamanho da tabela, e onze linhas com oito zeros são o cadastro copiado, não um fechado.
            Um par de categoria que não está no vocabulário (dado importado, lançamento anterior ao catálogo) entra na
            tabela do mesmo jeito — é justamente a linha que o escritório precisa ver. O CSV baixa o período inteiro,
            com todas as linhas, e não a página que está na tela.
        </p>
    @endif
</x-layouts.app>
