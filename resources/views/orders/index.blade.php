@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    // A linha do cabeçalho fica inteira numa fonte só: interpolação quebrada em
    // duas linhas injetaria espaço no texto e a frase deixaria de ser a frase.
    $rotuloAlcance = $restrito
        ? 'As ordens que são suas aparecem aqui — nada mais sai do banco.'
        : 'A operação da empresa: o que está aberto, o que está em campo e o que venceu.';
@endphp

<x-layouts.app
    title="Ordens de serviço"
    :subtitle="$rotuloAlcance"
    :trilha="['Operação' => null, 'Ordens de serviço' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $ordens->total() }} {{ $ordens->total() === 1 ? 'ordem encontrada' : 'ordens encontradas' }}
            @if ($ordens->total() > 0)
                · página {{ $ordens->currentPage() }} de {{ max(1, $ordens->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('orders.export')
                <x-ui.button variant="soft-primary" size="sm" :href="route('orders.export', request()->query())"
                    icon="fa-solid fa-file-csv">Exportar CSV</x-ui.button>
            @endcan

            @can('orders.create')
                <x-ui.button variant="primary" size="sm" :href="route('orders.create')" icon="fa-solid fa-plus">
                    Nova ordem
                </x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('orders.index')" :limpar="route('orders.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Número, título, descrição ou endereço" data-nf-busca />
        </div>

        <x-ui.select label="Estado" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Todos os estados" data-nf-autosubmit />

        <x-ui.select label="Prioridade" name="prioridade" :opcoes="$prioridades" :value="request('prioridade')"
            placeholder="Todas as prioridades" data-nf-autosubmit />

        @unless ($restrito)
            <x-ui.select label="Técnico" name="tecnico" :opcoes="$tecnicos" :value="request('tecnico')"
                placeholder="Qualquer técnico da escala" data-nf-autosubmit />

            <x-ui.select label="Cliente" name="cliente" :opcoes="$clientes" :value="request('cliente')"
                placeholder="Qualquer cliente" data-nf-autosubmit />
        @endunless

        <x-ui.input label="Agendada a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($ordens->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma ordem com estes filtros"
                    text="Ajuste a busca, o estado, a prioridade, o técnico ou a janela de agendamento e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('orders.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @elseif ($restrito)
                <x-ui.state tone="empty" title="Nenhuma ordem no seu alcance"
                    text="Ordens aparecem aqui quando você é o técnico delas ou quando são do cliente da sua conta. Quem marca o técnico é o escritório, na ficha da ordem." />
            @else
                <x-ui.state tone="empty" title="Nenhuma ordem de serviço nesta empresa"
                    text="A ordem é o documento do trabalho: cliente, serviço, itens cobrados, quem vai ao campo e quando. É dela que nascem o chamado, a agenda e o check-in.">
                    @can('orders.create')
                        <x-ui.button variant="primary" size="sm" :href="route('orders.create')" icon="fa-solid fa-plus">
                            Abrir a primeira ordem
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Ordens de serviço desta empresa, com o total que o banco soma dos itens</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="number" rotulo="Número" />
                            <x-ui.sort-link coluna="title" rotulo="Ordem" />
                            <th scope="col">Cliente</th>
                            <x-ui.sort-link coluna="scheduled_starts_at" rotulo="Agendamento" padrao="scheduled_starts_at" />
                            <th scope="col">Técnico</th>
                            <x-ui.sort-link coluna="priority" rotulo="Prioridade" />
                            <x-ui.sort-link coluna="status" rotulo="Estado" />
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordens as $ordem)
                            @php
                                $totais = $ordem->totais();
                                $atrasada = in_array($ordem->status, ['open', 'in_progress'], true)
                                    && $ordem->scheduled_ends_at !== null
                                    && $ordem->scheduled_ends_at->isPast();
                            @endphp
                            <tr>
                                <td class="nf-mono">
                                    <a class="fw-semibold" href="{{ route('orders.show', $ordem) }}">{{ $ordem->number }}</a>
                                </td>
                                <td>
                                    {{ $ordem->title }}
                                    <div class="nf-text-muted-2 small">
                                        {{ $ordem->items_count }} {{ $ordem->items_count === 1 ? 'item cobrado' : 'itens cobrados' }}
                                        @if ($ordem->service)
                                            · {{ $ordem->service->name }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $ordem->client->name }}</td>
                                <td>
                                    @if ($ordem->scheduled_starts_at)
                                        <span class="nf-mono">{{ Formatters::dateTime($ordem->scheduled_starts_at) }}</span>
                                        <div class="nf-text-muted-2 small">
                                            até <span class="nf-mono">{{ Formatters::time($ordem->scheduled_ends_at) }}</span>
                                        </div>
                                    @else
                                        <span class="nf-status nf-status-waiting">sem agendamento</span>
                                    @endif

                                    @if ($atrasada)
                                        <div class="nf-status nf-status-canceled small">prazo vencido</div>
                                    @endif
                                </td>
                                <td>{{ $ordem->technician?->name ?? Formatters::TIME_NULL }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('priority', $ordem->priority) }}">
                                        {{ StatusCatalog::label('priority', $ordem->priority) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('order', $ordem->status) }}">
                                        {{ StatusCatalog::label('order', $ordem->status) }}
                                    </span>
                                </td>
                                <td class="text-end nf-mono">{{ Formatters::money($totais['total']) }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        <x-ui.button variant="ghost" size="sm" :href="route('orders.show', $ordem)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @can('orders.update')
                                            @if (! $ordem->estaEncerrada())
                                                <x-ui.button variant="ghost" size="sm"
                                                    :href="route('orders.edit', $ordem)" icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$ordens" rotulo="ordens" />
        </x-ui.card>
    @endif
</x-layouts.app>
