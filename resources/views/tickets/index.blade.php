@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')
@use('Illuminate\Support\Str')

@php
    // A linha do cabeçalho fica inteira numa fonte só: interpolação quebrada em
    // duas linhas injetaria espaço no texto e a frase deixaria de ser a frase.
    $rotuloAlcance = $restrito
        ? 'Os chamados que são seus aparecem aqui — nada mais sai do banco.'
        : 'A voz do cliente registrada: o que foi relatado, o que está sendo atendido e o que venceu o prazo.';
@endphp

<x-layouts.app
    title="Chamados"
    :subtitle="$rotuloAlcance"
    :trilha="['Operação' => null, 'Chamados' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $chamados->total() }} {{ $chamados->total() === 1 ? 'chamado encontrado' : 'chamados encontrados' }}
            @if ($chamados->total() > 0)
                · página {{ $chamados->currentPage() }} de {{ max(1, $chamados->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('tickets.export')
                <x-ui.button variant="soft-primary" size="sm" :href="route('tickets.export', request()->query())"
                    icon="fa-solid fa-file-csv">Exportar CSV</x-ui.button>
            @endcan

            @can('tickets.create')
                <x-ui.button variant="primary" size="sm" :href="route('tickets.create')" icon="fa-solid fa-plus">
                    Nova chamada
                </x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('tickets.index')" :limpar="route('tickets.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Protocolo, assunto ou relato do cliente" data-nf-busca />
        </div>

        <x-ui.select label="Estado" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Todos os estados" data-nf-autosubmit />

        <x-ui.select label="Prioridade" name="prioridade" :opcoes="$prioridades" :value="request('prioridade')"
            placeholder="Todas as prioridades" data-nf-autosubmit />

        <x-ui.select label="Categoria" name="categoria" :opcoes="$categorias" :value="request('categoria')"
            placeholder="Todas as categorias" data-nf-autosubmit />

        <x-ui.select label="Prazo" name="atrasado" :opcoes="['1' => 'Só com prazo vencido']"
            :value="request('atrasado')" placeholder="Todos os prazos" data-nf-autosubmit />

        @unless ($restrito)
            <x-ui.select label="Técnico" name="tecnico" :opcoes="$tecnicos" :value="request('tecnico')"
                placeholder="Qualquer técnico da escala" data-nf-autosubmit />

            <x-ui.select label="Cliente" name="cliente" :opcoes="$clientes" :value="request('cliente')"
                placeholder="Qualquer cliente" data-nf-autosubmit />
        @endunless

        <x-ui.input label="Aberto a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($chamados->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum chamado com estes filtros"
                    text="Ajuste a busca, o estado, a prioridade, a categoria, o prazo ou a janela de abertura e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('tickets.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @elseif ($restrito)
                <x-ui.state tone="empty" title="Nenhum chamado no seu alcance"
                    text="Chamados aparecem aqui quando você é o técnico deles ou quando são do cliente da sua conta. Quem marca o técnico é o escritório, na tela de abrir chamado." />
            @else
                <x-ui.state tone="empty" title="Nenhum chamado nesta empresa"
                    text="O chamado é o que o cliente relata: um equipamento que falha, uma dúvida, um serviço que precisa de resposta. É dele que nasce a conversa com prazo.">
                    @can('tickets.create')
                        <x-ui.button variant="primary" size="sm" :href="route('tickets.create')" icon="fa-solid fa-plus">
                            Abrir o primeiro chamado
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Chamados desta empresa, com o prazo que a própria prioridade impõe</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="protocol" rotulo="Protocolo" />
                            <x-ui.sort-link coluna="subject" rotulo="O que foi relatado" />
                            <th scope="col">Cliente</th>
                            <x-ui.sort-link coluna="opened_at" rotulo="Abertura" padrao="opened_at" />
                            <th scope="col">Técnico</th>
                            <x-ui.sort-link coluna="priority" rotulo="Prioridade" />
                            <x-ui.sort-link coluna="status" rotulo="Estado" />
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($chamados as $chamado)
                            @php
                                $prazo = $chamado->prazoResolucao();
                                $vencido = $chamado->prazoVencido();
                            @endphp
                            <tr>
                                <td class="nf-mono">
                                    <a class="fw-semibold" href="{{ route('tickets.show', $chamado) }}">{{ $chamado->protocol }}</a>
                                    <div class="nf-text-muted-2 small">
                                        {{ $chamado->comments_count }} {{ $chamado->comments_count === 1 ? 'nota' : 'notas' }}
                                    </div>
                                </td>
                                <td>
                                    {{ $chamado->subject }}
                                    <div class="nf-text-muted-2 small">
                                        {{ $categorias[$chamado->category] ?? \Illuminate\Support\Str::headline((string) $chamado->category) }}
                                        @if ($chamado->service_order_id)
                                            · <span class="nf-mono">{{ $chamado->serviceOrder?->number }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $chamado->client->name }}</td>
                                <td>
                                    <span class="nf-mono">{{ Formatters::dateTime($chamado->opened_at) }}</span>
                                    <div class="nf-text-muted-2 small">
                                        prazo
                                        <span class="nf-mono">{{ $prazo ? Formatters::dateTime($prazo) : '—' }}</span>
                                    </div>

                                    @if ($vencido)
                                        <div class="nf-status nf-status-canceled small">prazo vencido</div>
                                    @endif
                                </td>
                                <td>{{ $chamado->technician?->name ?? Formatters::TIME_NULL }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('priority', $chamado->priority) }}">
                                        {{ StatusCatalog::label('priority', $chamado->priority) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('ticket', $chamado->status) }}">
                                        {{ StatusCatalog::label('ticket', $chamado->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="nf-table-actions">
                                        <x-ui.button variant="ghost" size="sm" :href="route('tickets.show', $chamado)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @can('tickets.update')
                                            @unless ($chamado->estaEncerrado())
                                                <x-ui.button variant="ghost" size="sm"
                                                    :href="route('tickets.edit', $chamado)" icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endunless
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$chamados" rotulo="chamados" />
        </x-ui.card>
    @endif
</x-layouts.app>
