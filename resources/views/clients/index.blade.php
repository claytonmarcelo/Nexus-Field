@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Clientes"
    subtitle="Carteira da empresa: quem somos obrigados a atender, com endereço, contato e situação."
    :trilha="['Cadastros' => null, 'Clientes' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $clientes->total() }} {{ $clientes->total() === 1 ? 'cliente cadastrado' : 'clientes cadastrados' }}
            @if ($clientes->total() > 0)
                · página {{ $clientes->currentPage() }} de {{ max(1, $clientes->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('clients.export')
                <x-ui.button variant="soft-primary" size="sm" :href="route('clients.export', request()->query())" icon="fa-solid fa-file-csv">
                    Exportar CSV
                </x-ui.button>
            @endcan

            @can('clients.create')
                <x-ui.button variant="primary" size="sm" :href="route('clients.create')" icon="fa-solid fa-plus">
                    Novo cliente
                </x-ui.button>
            @endcan
        </div>
    </div>

    @can('clients.view')
        <x-ui.filters :rota="route('clients.index')" :limpar="route('clients.index')">
            <div class="nf-filters-largo">
                <x-ui.input
                    label="Buscar"
                    name="busca"
                    :value="request('busca')"
                    placeholder="Nome, fantasia, documento, e-mail ou telefone"
                    data-nf-busca
                />
            </div>

            <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
                placeholder="Todas" data-nf-autosubmit />

            <x-ui.select label="Cidade" name="cidade" :opcoes="$cidades" :value="request('cidade')"
                placeholder="Todas as cidades" data-nf-autosubmit />
        </x-ui.filters>
    @endcan

    @if ($clientes->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum cliente com estes filtros"
                    text="Ajuste a busca, a situação ou a cidade e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('clients.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhum cliente cadastrado nesta empresa"
                    text="Cadastre o primeiro cliente para conseguir abrir uma ordem de serviço para ele.">
                    @can('clients.create')
                        <x-ui.button variant="primary" size="sm" :href="route('clients.create')" icon="fa-solid fa-plus">
                            Cadastrar cliente
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Clientes desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Cliente" padrao="name" />
                            <x-ui.sort-link coluna="document" rotulo="Documento" />
                            <th scope="col">Contato</th>
                            <th scope="col">Endereço</th>
                            <x-ui.sort-link coluna="status" rotulo="Situação" />
                            <th scope="col" class="text-end">Ordens</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $cliente)
                            @php($endereco = $cliente->enderecoPrincipal())
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('clients.show', $cliente) }}">{{ $cliente->name }}</a>
                                    @if ($cliente->trade_name)
                                        <div class="nf-text-muted-2">{{ $cliente->trade_name }}</div>
                                    @endif
                                    @if ($cliente->trashed())
                                        <div class="nf-status nf-status-canceled">
                                            Excluído em {{ Formatters::date($cliente->deleted_at) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="nf-mono">{{ $cliente->document ?: Formatters::TIME_NULL }}</td>
                                <td>
                                    @if ($cliente->phone)
                                        <div class="nf-mono">{{ $cliente->phone }}</div>
                                    @endif
                                    <div class="nf-text-muted-2">{{ $cliente->email ?: 'Sem e-mail' }}</div>
                                </td>
                                <td>
                                    @if ($endereco)
                                        {{ $endereco->city }}{{ $endereco->state ? '/'.$endereco->state : '' }}
                                    @else
                                        <span class="nf-text-muted-2">Sem endereço</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('client', $cliente->status) }}">
                                        {{ StatusCatalog::label('client', $cliente->status) }}
                                    </span>
                                </td>
                                <td class="text-end nf-mono">{{ $cliente->service_orders_count }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        @unless ($cliente->trashed())
                                            @can('clients.update')
                                                <x-ui.button variant="ghost" size="sm" :href="route('clients.edit', $cliente)"
                                                    icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endcan
                                        @endunless

                                        @if ($cliente->trashed())
                                            @can('clients.delete')
                                                <x-ui.action-form :acao="route('clients.restore', $cliente)" metodo="PATCH"
                                                    rotulo="Restaurar" titulo="Restaurar {{ $cliente->name }}?"
                                                    texto="O cliente volta à carteira e fica visível nas listagens."
                                                    icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                                            @endcan
                                        @else
                                            <x-ui.button variant="ghost" size="sm" :href="route('clients.show', $cliente)"
                                                icon="fa-solid fa-eye">Abrir</x-ui.button>

                                            @can('clients.delete')
                                                <x-ui.action-form :acao="route('clients.destroy', $cliente)"
                                                    rotulo="Excluir" titulo="Excluir {{ $cliente->name }}?"
                                                    texto="Só é possível excluir quem ainda não gerou ordem, chamado ou lançamento. Inativar preserva o histórico." />
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$clientes" rotulo="clientes" />
        </x-ui.card>
    @endif
</x-layouts.app>
