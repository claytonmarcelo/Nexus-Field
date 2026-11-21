@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Filiais"
    subtitle="Matriz e filiais da empresa: a base da estrutura, com seus setores e vínculos."
    :trilha="['Organização' => route('organization.index'), 'Filiais' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $filiais->total() }} {{ $filiais->total() === 1 ? 'unidade cadastrada' : 'unidades cadastradas' }}
            @if ($filiais->total() > 0)
                · página {{ $filiais->currentPage() }} de {{ max(1, $filiais->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('organization.create')
                <x-ui.button variant="primary" size="sm" :href="route('organization.branches.create')"
                    icon="fa-solid fa-plus">Nova filial</x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('organization.branches.index')" :limpar="route('organization.branches.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Nome ou identificador da unidade" data-nf-busca />
        </div>

        <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Todas" data-nf-autosubmit />
    </x-ui.filters>

    @if ($filiais->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma filial com estes filtros"
                    text="Mude a busca ou a situação e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('organization.branches.index')"
                        icon="fa-solid fa-rotate-left">Limpar filtros</x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhuma filial cadastrada"
                    text="Cadastre a matriz para começar a montar a estrutura da empresa." />
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Filiais desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Unidade" padrao="name" />
                            <th scope="col">Tipo</th>
                            <th scope="col">Gestor</th>
                            <th scope="col" class="text-end">Setores</th>
                            <th scope="col" class="text-end">Vínculos</th>
                            <th scope="col">Situação</th>
                            <x-ui.sort-link coluna="created_at" rotulo="Cadastrada em" />
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filiais as $filial)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('organization.branches.show', $filial) }}">
                                        {{ $filial->name }}
                                    </a>
                                    <div class="nf-text-muted-2 small nf-mono">{{ $filial->slug }}</div>
                                </td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('branch_type', $filial->type) }}">
                                        {{ StatusCatalog::label('branch_type', $filial->type) }}
                                    </span>
                                </td>
                                <td>{{ $filial->manager?->name ?? 'Sem gestor' }}</td>
                                <td class="text-end nf-mono">{{ $filial->departments_count }}</td>
                                <td class="text-end nf-mono">{{ $filial->employments_count }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('organization', $filial->active ? 'active' : 'inactive') }}">
                                        {{ StatusCatalog::label('organization', $filial->active ? 'active' : 'inactive') }}
                                    </span>
                                </td>
                                <td class="nf-mono">{{ Formatters::date($filial->created_at) }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        @can('organization.update')
                                            <x-ui.button variant="ghost" size="sm"
                                                :href="route('organization.branches.edit', $filial)"
                                                icon="fa-solid fa-pen">Editar</x-ui.button>
                                        @endcan

                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('organization.branches.show', $filial)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @can('organization.delete')
                                            <x-ui.action-form :acao="route('organization.branches.destroy', $filial)"
                                                rotulo="Excluir" titulo="Excluir {{ $filial->name }}?"
                                                texto="Só é possível excluir uma unidade sem setores e sem vínculos." />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$filiais" rotulo="filiais" />
        </x-ui.card>
    @endif
</x-layouts.app>
