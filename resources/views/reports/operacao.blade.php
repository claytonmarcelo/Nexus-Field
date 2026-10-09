@use('App\Support\Formatters')

<x-layouts.app
    :title="$relatorio['rotulo']"
    :subtitle="$relatorio['pergunta']"
    :trilha="['Gestão' => route('reports.index'), 'Relatórios' => route('reports.index'), $relatorio['aba'] => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $linhas->total() }} {{ $linhas->total() === 1 ? 'técnico com fato' : 'técnicos com fato' }}
            @if ($linhas->total() > 0)
                · página {{ $linhas->currentPage() }} de {{ max(1, $linhas->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @include('reports.partials.exportar', ['chave' => 'operacao', 'janela' => $janela])
        </div>
    </div>

    @include('reports.partials.abas', ['chave' => 'operacao', 'janela' => $janela, 'disponiveis' => $disponiveis])

    <x-ui.filters :rota="route('reports.operacao')" :limpar="route('reports.operacao')">
        <x-ui.input label="Ordem concluída a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @include('reports.partials.janela', ['janela' => $janela])

    @if ($linhas->total() === 0)
        <x-ui.card title="Nada para fechar">
            <x-ui.state tone="empty" title="Período sem operação fechada"
                text="Nenhuma ordem terminou nesta janela e nenhuma passagem de campo foi registrada nela. Ordem que está em andamento não conta aqui: o fechado de operação pergunta pelo que aconteceu, e o que ainda não acabou aparece na listagem de ordens e na agenda.">
                @can('orders.view')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('orders.index')"
                        icon="fa-solid fa-clipboard-list">Ordens em andamento</x-ui.button>
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
                            <span>Ordens concluídas</span>
                            <strong class="nf-valor nf-mono">{{ $totais['ordens'] }}</strong>
                        </li>
                        <li>
                            <span>Valor gerado</span>
                            <strong class="nf-valor nf-mono nf-status-done">{{ Formatters::money($totais['valor']) }}</strong>
                        </li>
                        <li>
                            <span>Descontos dados</span>
                            <strong class="nf-valor nf-mono">{{ Formatters::money($totais['descontos']) }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Visitas de campo</span>
                            <strong class="nf-mono">{{ $totais['visitas'] }}</strong>
                        </li>
                        <li>
                            <span>Técnicos com fato no período</span>
                            <strong class="nf-mono">{{ $totais['tecnicos'] }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Visitas sem posição medida</span>
                            <strong class="nf-mono {{ $totais['sem_posicao'] > 0 ? 'nf-status-progress' : '' }}">
                                {{ $totais['sem_posicao'] }}
                            </strong>
                        </li>
                        <li>
                            <span>Visitas ainda abertas</span>
                            <strong class="nf-mono {{ $totais['visitas_abertas'] > 0 ? 'nf-status-waiting' : '' }}">
                                {{ $totais['visitas_abertas'] }}
                            </strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <p class="nf-text-muted-2 small mb-0">
                        Visitar sem posição medida é o check-in feito sem o navegador liberar a geolocalização, e visita
                        ainda aberta é a que entrou no campo e não teve saída registrada. As duas continuam no fechado
                        com a média que têm: um número que some o fato mal medido explicaria menos que um número que
                        diz quantos faltam.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="mt-3">
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Produção e presença de campo por técnico no período, com ordens concluídas, valor gerado,
                        tempo médio de execução e as medidas das visitas
                    </caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="rotulo" rotulo="Técnico" padrao="rotulo" />
                            <th scope="col">Estado</th>
                            <x-ui.sort-link coluna="ordens" rotulo="Ordens concluídas" />
                            <x-ui.sort-link coluna="valor" rotulo="Valor gerado" />
                            <th scope="col" class="text-end">Descontos dados</th>
                            <x-ui.sort-link coluna="tempo_execucao" rotulo="Tempo médio de execução" />
                            <x-ui.sort-link coluna="visitas" rotulo="Visitas" />
                            <x-ui.sort-link coluna="tempo_local" rotulo="Tempo médio no local" />
                            <x-ui.sort-link coluna="distancia" rotulo="Distância média da chegada" />
                            <th scope="col" class="text-end">Sem posição</th>
                            <th scope="col" class="text-end">Ainda abertas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $linha['rotulo'] }}
                                    @if ($linha['chave'] === '0')
                                        <div class="nf-text-muted-2 small">
                                            ordem fechada sem técnico na ficha
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    {{ $linha['estado'] ?? Formatters::TIME_NULL }}
                                </td>
                                <td class="nf-mono">{{ $linha['ordens'] }}</td>
                                <td class="text-end nf-mono">{{ Formatters::money($linha['valor']) }}</td>
                                <td class="text-end nf-mono">
                                    {{ $linha['descontos'] > 0 ? Formatters::money($linha['descontos']) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ Formatters::duration($linha['tempo_execucao']) }}
                                </td>
                                <td class="nf-mono">{{ $linha['visitas'] }}</td>
                                <td class="text-end nf-mono">
                                    {{ Formatters::duration($linha['tempo_local']) }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['distancia'] === null ? Formatters::TIME_NULL : Formatters::decimal($linha['distancia'], 0).' m' }}
                                </td>
                                <td class="text-end nf-mono {{ $linha['sem_posicao'] > 0 ? 'nf-status-progress' : '' }}">
                                    {{ $linha['sem_posicao'] }}
                                </td>
                                <td class="text-end nf-mono {{ $linha['visitas_abertas'] > 0 ? 'nf-status-waiting' : '' }}">
                                    {{ $linha['visitas_abertas'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$linhas" rotulo="técnicos" />
        </x-ui.card>

        <p class="nf-text-muted-2 small mt-3 mb-0">
            Só entram na tabela os técnicos com ordem concluída ou passagem de campo na janela — quem não teve fato em
            nenhum dos dois lados não é linha zerada, é alguém que este período não resume. As médias saem de soma e
            contagem sobre as mesmas duas agregações do MySQL: tempo de execução usa os carimbos
            <span class="nf-mono">started_at</span> e <span class="nf-mono">completed_at</span> da ordem, e tempo no
            local usa a entrada e a saída da visita. Valor gerado é a soma dos itens da ordem menos o desconto dado
            nela, o mesmo cálculo que a ficha usa para cobrar.
        </p>
    @endif
</x-layouts.app>
