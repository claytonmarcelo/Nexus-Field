@use('App\Support\Formatters')

<x-layouts.app
    title="Relatórios"
    subtitle="Fechamento de período. Cada número sai do MySQL com a mesma régua que o módulo usa para gravar o fato, porque fechado que discorda da carteira não é fechado."
    :trilha="['Gestão' => null, 'Relatórios' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $disponiveis === [] ? 'Nenhum fechado disponível' : count($disponiveis).' '.(count($disponiveis) === 1 ? 'relatório' : 'relatórios') }}
            nesta conta
        </p>

        <div class="nf-list-acoes">
            @if ($disponiveis !== [])
                <p class="nf-text-muted-2 small mb-0">
                    O período escolhido abaixo vale para todos eles.
                </p>
            @endif
        </div>
    </div>

    <x-ui.filters :rota="route('reports.index')" :limpar="route('reports.index')">
        <x-ui.input label="Período a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @include('reports.partials.janela', ['janela' => $janela])

    @if ($disponiveis === [])
        <x-ui.card title="Nenhum módulo para resumir">
            <x-ui.state tone="denied" title="Sem leitura de módulo"
                text="Esta conta tem acesso aos relatórios, mas nenhum papel dela lê financeiro, ordens, chamados ou estoque — e é a leitura do módulo que dá o número ao fechado. Peça ao administrador a permissão de visualização do módulo que você precisa conferir." />
        </x-ui.card>
    @else
        <x-ui.card title="Fechamento deste período"
            subtitle="Contado sobre a janela inteira, não sobre a empresa toda.">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Receita realizada</span>
                            <strong class="nf-mono nf-status-done">{{ Formatters::money($fechamento['receita']) }}</strong>
                        </li>
                        <li>
                            <span>Despesa realizada</span>
                            <strong class="nf-mono nf-status-canceled">{{ Formatters::money($fechamento['despesa']) }}</strong>
                        </li>
                        <li>
                            <span>Saldo do período</span>
                            <strong class="nf-mono {{ $fechamento['saldo'] < 0 ? 'nf-status-canceled' : '' }}">
                                {{ Formatters::money($fechamento['saldo']) }}
                            </strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Ordens concluídas</span>
                            <strong class="nf-mono">{{ $fechamento['ordens'] }}</strong>
                        </li>
                        <li>
                            <span>Valor gerado</span>
                            <strong class="nf-mono">{{ Formatters::money($fechamento['valor']) }}</strong>
                        </li>
                        <li>
                            <span>Visitas de campo</span>
                            <strong class="nf-mono">{{ $fechamento['visitas'] }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Chamados abertos</span>
                            <strong class="nf-mono">{{ $fechamento['chamados'] }}</strong>
                        </li>
                        <li>
                            <span>Ainda em andamento</span>
                            <strong class="nf-mono {{ $fechamento['chamados_andamento'] > 0 ? 'nf-status-progress' : '' }}">
                                {{ $fechamento['chamados_andamento'] }}
                            </strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Movimentações de estoque</span>
                            <strong class="nf-mono">{{ $fechamento['movimentacoes'] }}</strong>
                        </li>
                        <li>
                            <span>Produtos tocados</span>
                            <strong class="nf-mono">{{ $fechamento['produtos'] }}</strong>
                        </li>
                    </ul>
                </div>
            </div>

            <p class="nf-text-muted-2 small mt-3 mb-0">
                Receita e despesa realizadas são a soma dos <em>pagamentos</em> cuja data cai nesta janela, lida em
                <span class="nf-mono">payments</span> — não o valor previsto das contas, que vence quando vence. Ordem
                concluída e valor gerado vêm de <span class="nf-mono">service_orders</span> fechadas na janela, com o
                mesmo cálculo de item e desconto que a ficha da ordem usa para cobrar.
            </p>
        </x-ui.card>

        <div class="row g-3 mt-1">
            @foreach ($disponiveis as $chave => $entrada)
                <div class="col-12 col-lg-6">
                    <x-ui.card :title="$entrada['rotulo']" :subtitle="$entrada['pergunta']">
                        <x-slot:tools>
                            <i class="{{ $entrada['icone'] }} nf-text-muted-2" aria-hidden="true"
                                title="{{ $entrada['rotulo'] }}"></i>
                        </x-slot:tools>

                        <p class="mb-3 nf-text-muted-2 small">
                            @switch($chave)
                                @case('financeiro')
                                    Duas somas lado a lado: as contas que vencem na janela, com o que já foi baixado
                                    delas, e o dinheiro que efetivamente entrou ou saiu no mesmo período. Elas
                                    diferem numa operação real — o serviço de 28 fecha a conta de 13 e o PIX cai em
                                    15 — e o relatório mostra as duas em vez de escolher uma.
                                    @break

                                @case('operacao')
                                    Produção e presença pelo técnico: ordens concluídas e o que valeram, tempo médio
                                    de execução medido nos carimbos da ordem, e as passagens de campo com tempo no
                                    local, distância da chegada e o que não teve posição medida.
                                    @break

                                @case('chamados')
                                    Protocolos abertos na janela, agrupados por prioridade, categoria do serviço ou
                                    técnico. A taxa de prazo usa o mesmo intervalo de
                                    <span class="nf-mono">Ticket::PRAZO_HORAS</span> que a listagem marca como
                                    atrasado.
                                    @break

                                @case('estoque')
                                    O livro-caixa do período por produto: compras, cargas, consumos, devoluções e
                                    ajustes com o sinal de cada tipo, o custo do que foi consumido e o saldo central
                                    de hoje.
                            @endswitch
                        </p>

                        <x-ui.button variant="soft-primary" size="sm"
                            :href="route($entrada['rota'], ['inicio' => $janela['inicio']->format('Y-m-d'), 'fim' => $janela['fim']->format('Y-m-d')])"
                            icon="fa-solid fa-chart-column">
                            Abrir com este período
                        </x-ui.button>
                    </x-ui.card>
                </div>
            @endforeach
        </div>

        <p class="nf-text-muted-2 small mt-3 mb-0">
            A janela tem teto de {{ \App\Support\Relatorio::DIAS_MAXIMO }} dias: período maior que isso é pedido para o
            banco varrer anos, e o relatório corta o início e avisa em vez de responder devagar. Cada tela tem a própria
            exportação em CSV, com exatamente as linhas que estão na tabela.
        </p>
    @endif
</x-layouts.app>
