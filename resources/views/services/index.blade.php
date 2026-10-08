@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')
@use('Illuminate\Support\Str')

<x-layouts.app
    title="Serviços"
    subtitle="O que esta empresa cobra: preço, duração estimada e o grupo a que o serviço pertence."
    :trilha="['Catálogo' => null, 'Serviços' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $servicos->total() }} {{ $servicos->total() === 1 ? 'serviço cadastrado' : 'serviços cadastrados' }}
            @if ($servicos->total() > 0)
                · página {{ $servicos->currentPage() }} de {{ max(1, $servicos->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('services.view')
                <x-ui.button variant="ghost" size="sm" :href="route('categories.index')" icon="fa-solid fa-layer-group">
                    Categorias
                </x-ui.button>
            @endcan

            @can('services.create')
                <x-ui.button variant="primary" size="sm" :href="route('services.create')" icon="fa-solid fa-plus">
                    Novo serviço
                </x-ui.button>
            @endcan
        </div>
    </div>

    @can('services.view')
        <x-ui.filters :rota="route('services.index')" :limpar="route('services.index')">
            <div class="nf-filters-largo">
                <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                    placeholder="Nome, código ou descrição do serviço" data-nf-busca />
            </div>

            <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
                placeholder="Todas as ativas e inativas" data-nf-autosubmit />

            <x-ui.select label="Categoria" name="categoria" :opcoes="$categorias" :value="request('categoria')"
                placeholder="Todas as categorias" data-nf-autosubmit />
        </x-ui.filters>
    @endcan

    @if ($servicos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum serviço com estes filtros"
                    text="Ajuste a busca, a situação ou a categoria e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('services.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhum serviço cadastrado nesta empresa"
                    text="Serviço é o que a empresa vende: instalação, manutenção, limpeza de caixa. Sem catálogo, a ordem de serviço não tem o que cobrar.">
                    @can('services.create')
                        <x-ui.button variant="primary" size="sm" :href="route('services.create')" icon="fa-solid fa-plus">
                            Cadastrar serviço
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Serviços desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Serviço" padrao="name" />
                            <x-ui.sort-link coluna="code" rotulo="Código" />
                            <th scope="col">Categoria</th>
                            <x-ui.sort-link coluna="price" rotulo="Preço" />
                            <x-ui.sort-link coluna="estimated_minutes" rotulo="Duração" />
                            <x-ui.sort-link coluna="status" rotulo="Situação" />
                            <th scope="col" class="text-end">Uso</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servicos as $servico)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('services.show', $servico) }}">{{ $servico->name }}</a>
                                    @if ($servico->description)
                                        <div class="nf-text-muted-2">{{ Str::limit($servico->description, 70) }}</div>
                                    @endif
                                    @if ($servico->trashed())
                                        <div class="nf-status nf-status-canceled">
                                            Excluído em {{ Formatters::date($servico->deleted_at) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="nf-mono">{{ $servico->code ?: Formatters::TIME_NULL }}</td>
                                <td>{{ $servico->category?->name ?: 'Sem categoria' }}</td>
                                <td class="nf-mono">{{ Formatters::money($servico->price) }}</td>
                                <td class="nf-mono">{{ Formatters::duration($servico->estimated_minutes) }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('catalogo', $servico->status) }}">
                                        {{ StatusCatalog::label('catalogo', $servico->status) }}
                                    </span>
                                </td>
                                <td class="text-end nf-mono">
                                    {{ $servico->service_orders_count }}
                                    {{ $servico->service_orders_count === 1 ? 'ordem' : 'ordens' }}
                                    <div class="nf-text-muted-2">
                                        {{ $servico->items_count }}
                                        {{ $servico->items_count === 1 ? 'item cobrado' : 'itens cobrados' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="nf-table-actions">
                                        @unless ($servico->trashed())
                                            @can('services.update')
                                                <x-ui.button variant="ghost" size="sm" :href="route('services.edit', $servico)"
                                                    icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endcan
                                        @endunless

                                        @if ($servico->trashed())
                                            @can('services.delete')
                                                <x-ui.action-form :acao="route('services.restore', $servico)" metodo="PATCH"
                                                    rotulo="Restaurar" titulo="Restaurar {{ $servico->name }}?"
                                                    texto="O serviço volta ao catálogo e pode ser escolhido em novas ordens."
                                                    icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                                            @endcan
                                        @else
                                            <x-ui.button variant="ghost" size="sm" :href="route('services.show', $servico)"
                                                icon="fa-solid fa-eye">Abrir</x-ui.button>

                                            @can('services.delete')
                                                <x-ui.action-form :acao="route('services.destroy', $servico)"
                                                    rotulo="Excluir" titulo="Excluir {{ $servico->name }}?"
                                                    texto="Só sai do catálogo quem nunca apareceu em ordem nem foi cobrado. Inativar preserva o histórico." />
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$servicos" rotulo="serviços" />
        </x-ui.card>
    @endif
</x-layouts.app>
