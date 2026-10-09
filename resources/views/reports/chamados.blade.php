@use('App\Support\Formatters')

<x-layouts.app
    :title="$relatorio['rotulo']"
    :subtitle="$relatorio['pergunta']"
    :trilha="['Gestão' => route('reports.index'), 'Relatórios' => route('reports.index'), $relatorio['aba'] => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $linhas->total() }} {{ $linhas->total() === 1 ? 'grupo' : 'grupos' }}
            · {{ mb_strtolower($angulos[$angulo]) }}
            @if ($linhas->total() > 0)
                · página {{ $linhas->currentPage() }} de {{ max(1, $linhas->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @include('reports.partials.exportar', ['chave' => 'chamados', 'janela' => $janela])
        </div>
    </div>

    @include('reports.partials.abas', ['chave' => 'chamados', 'janela' => $janela, 'disponiveis' => $disponiveis])

    <x-ui.filters :rota="route('reports.chamados')" :limpar="route('reports.chamados')">
        <x-ui.input label="Chamado aberto a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />

        <x-ui.select label="Agrupar por" name="angulo" :opcoes="$angulos" :value="$angulo"
            hint="O período pergunta pela abertura; o prazo se mede dentro do mesmo grupo." data-nf-autosubmit />
    </x-ui.filters>

    @include('reports.partials.janela', ['janela' => $janela])

    @if ($linhas->total() === 0)
        <x-ui.card title="Nada para fechar">
            <x-ui.state tone="empty" title="Período sem protocolo aberto"
                text="Nenhum chamado abriu nesta janela neste recorte. Chamado aberto antes e resolvido agora não entra aqui: o fechado responde pelos protocolos que nasceram no período, porque é sobre a abertura deles que se mede o prazo de resposta.">
                @can('tickets.view')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('tickets.index')"
                        icon="fa-regular fa-life-ring">Mesa de atendimento</x-ui.button>
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
                            <span>Chamados abertos</span>
                            <strong class="nf-mono">{{ $totais['chamados'] }}</strong>
                        </li>
                        <li>
                            <span>Críticos (alta e urgente)</span>
                            <strong class="nf-mono {{ $totais['criticos'] > 0 ? 'nf-status-canceled' : '' }}">
                                {{ $totais['criticos'] }}
                            </strong>
                        </li>
                        <li>
                            <span>Resolvidos</span>
                            <strong class="nf-mono nf-status-done">{{ $totais['resolvidos'] }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Ainda em andamento</span>
                            <strong class="nf-mono {{ $totais['em_andamento'] > 0 ? 'nf-status-progress' : '' }}">
                                {{ $totais['em_andamento'] }}
                            </strong>
                        </li>
                        <li>
                            <span>Fora do prazo agora</span>
                            <strong class="nf-mono {{ $totais['vencendo'] > 0 ? 'nf-status-canceled' : '' }}">
                                {{ $totais['vencendo'] }}
                            </strong>
                        </li>
                        <li>
                            <span>Resolvidos dentro do prazo</span>
                            <strong class="nf-mono">{{ $totais['no_prazo'] }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <dl class="nf-total-linha mb-0">
                        <dt>Taxa no prazo do período</dt>
                        <dd>{{ $totais['taxa'] === null ? Formatters::TIME_NULL : Formatters::decimal($totais['taxa'], 1).'%' }}</dd>
                    </dl>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <p class="nf-text-muted-2 small mb-0">
                        Fora do prazo agora é protocolo que continua aberto depois do intervalo da própria prioridade,
                        medido contra o relógio de hoje. Taxa no prazo responde só pelos resolvidos: chamado ainda
                        aberto não tem prazo cumprido nem perdido, e contá-lo nos dois lados da divisão inventaria uma
                        média.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="mt-3">
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Chamados do período por {{ mb_strtolower($angulos[$angulo]) }}, com a quantidade, os críticos,
                        o que está em andamento e o que foi resolvido dentro do prazo
                    </caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="rotulo" rotulo="Grupo"
                                :padrao="$angulo === 'prioridade' ? null : 'rotulo'" />
                            <x-ui.sort-link coluna="chamados" rotulo="Chamados" />
                            <th scope="col">% do período</th>
                            <th scope="col" class="text-end">Críticos</th>
                            <x-ui.sort-link coluna="em_andamento" rotulo="Em andamento" />
                            <th scope="col" class="text-end">Fora do prazo</th>
                            <x-ui.sort-link coluna="resolvidos" rotulo="Resolvidos" />
                            <x-ui.sort-link coluna="no_prazo" rotulo="No prazo" />
                            <th scope="col" class="text-end">Taxa no prazo</th>
                            <x-ui.sort-link coluna="tempo_resposta" rotulo="Tempo médio de resposta" />
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $linha['rotulo'] }}
                                    @if ($linha['prazo'] !== null)
                                        <div class="nf-text-muted-2 small">
                                            prazo de {{ $linha['prazo'] }} {{ $linha['prazo'] === 1 ? 'hora' : 'horas' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="nf-mono">{{ $linha['chamados'] }}</td>
                                <td class="text-end">
                                    <div class="nf-mono">{{ Formatters::decimal($linha['porcentagem'], 1) }}%</div>
                                    <div class="nf-bar-track nf-relatorio-barra" role="img"
                                        aria-label="{{ $linha['rotulo'] }}: {{ $linha['porcentagem'] }}% dos chamados do período">
                                        <div class="nf-bar-fill tone-progress"
                                            style="--nf-bar-width: {{ min(100, $linha['porcentagem']) }}%"></div>
                                    </div>
                                </td>
                                <td class="text-end nf-mono {{ $linha['criticos'] > 0 ? 'nf-status-canceled' : '' }}">
                                    {{ $linha['criticos'] }}
                                </td>
                                <td class="text-end nf-mono {{ $linha['em_andamento'] > 0 ? 'nf-status-progress' : '' }}">
                                    {{ $linha['em_andamento'] }}
                                </td>
                                <td class="text-end nf-mono {{ $linha['vencendo'] > 0 ? 'nf-status-canceled' : '' }}">
                                    {{ $linha['vencendo'] }}
                                </td>
                                <td class="text-end nf-mono">{{ $linha['resolvidos'] }}</td>
                                <td class="text-end nf-mono">{{ $linha['no_prazo'] }}</td>
                                <td class="text-end nf-mono">
                                    {{ $linha['taxa'] === null ? Formatters::TIME_NULL : Formatters::decimal($linha['taxa'], 1).'%' }}
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $linha['tempo_resposta'] === null ? Formatters::TIME_NULL : Formatters::decimal($linha['tempo_resposta'], 1).' h' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$linhas" rotulo="grupos" />
        </x-ui.card>

        <p class="nf-text-muted-2 small mt-3 mb-0">
            No ângulo por prioridade as quatro prioridades aparecem sempre, com zero quando o período não teve nenhuma:
            "urgente não abriu este mês" é resposta, e sumir com a linha faria a tabela parecer curta. Nos ângulos por
            categoria e por técnico só entram os grupos com chamado aberto na janela, e um grupo que perdeu o cadastro
            depois da abertura (categoria excluída, chamado sem técnico apontado) continua na tabela se descrevendo
            sozinho pelo <span class="nf-mono">slug</span> gravado no protocolo — a mesma régua da listagem, porque o
            chamado aconteceu com aquela categoria.
        </p>
    @endif
</x-layouts.app>
