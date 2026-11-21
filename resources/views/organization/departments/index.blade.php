@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Setores"
    subtitle="A árvore interna da empresa: filial, setor pai, profundidade e quem responde por cada área."
    :trilha="['Organização' => route('organization.index'), 'Setores' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $setores->total() }} {{ $setores->total() === 1 ? 'setor cadastrado' : 'setores cadastrados' }}
            @if ($setores->total() > 0)
                · página {{ $setores->currentPage() }} de {{ max(1, $setores->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('organization.create')
                <x-ui.button variant="primary" size="sm" :href="route('organization.departments.create')"
                    icon="fa-solid fa-plus">Novo setor</x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('organization.departments.index')" :limpar="route('organization.departments.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Nome, identificador ou descrição" data-nf-busca />
        </div>

        <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Todas" data-nf-autosubmit />

        <x-ui.select label="Filial" name="filial" :opcoes="$filiais" :value="request('filial')"
            placeholder="Todas as filiais" data-nf-autosubmit />
    </x-ui.filters>

    @if ($setores->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum setor com estes filtros"
                    text="Mude a busca, a situação ou a filial e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('organization.departments.index')"
                        icon="fa-solid fa-rotate-left">Limpar filtros</x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhum setor cadastrado"
                    text="Setores organizam as pessoas dentro de cada filial." />
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Setores desta empresa, na hierarquia e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Setor" padrao="name" />
                            <th scope="col">Filial</th>
                            <th scope="col">Setor pai</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Gestor</th>
                            <th scope="col" class="text-end">Nível</th>
                            <th scope="col" class="text-end">Cargos</th>
                            <th scope="col" class="text-end">Vínculos</th>
                            <th scope="col">Situação</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($setores as $setor)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('organization.departments.show', $setor) }}">
                                        {{ str_repeat('— ', (int) $setor->depth).$setor->name }}
                                    </a>
                                    <div class="nf-text-muted-2 small nf-mono">{{ $setor->slug }}</div>
                                </td>
                                <td>{{ $setor->branch?->name ?? 'Sem filial' }}</td>
                                <td>{{ $setor->parent?->name ?? 'Raiz' }}</td>
                                <td>{{ StatusCatalog::label('department_type', $setor->type) }}</td>
                                <td>{{ $setor->manager?->name ?? 'Sem gestor' }}</td>
                                <td class="text-end nf-mono">{{ $setor->depth }}</td>
                                <td class="text-end nf-mono">{{ $setor->positions_count }}</td>
                                <td class="text-end nf-mono">{{ $setor->employments_count }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('organization', $setor->active ? 'active' : 'inactive') }}">
                                        {{ StatusCatalog::label('organization', $setor->active ? 'active' : 'inactive') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="nf-table-actions">
                                        @can('organization.update')
                                            <x-ui.button variant="ghost" size="sm"
                                                :href="route('organization.departments.edit', $setor)"
                                                icon="fa-solid fa-pen">Editar</x-ui.button>
                                        @endcan

                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('organization.departments.show', $setor)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @can('organization.delete')
                                            <x-ui.action-form :acao="route('organization.departments.destroy', $setor)"
                                                rotulo="Excluir" titulo="Excluir {{ $setor->name }}?"
                                                texto="Só é possível excluir um setor vazio, sem filhos, cargos ou vínculos." />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$setores" rotulo="setores" />
        </x-ui.card>
    @endif
</x-layouts.app>
