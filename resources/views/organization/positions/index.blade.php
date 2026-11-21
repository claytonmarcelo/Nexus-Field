@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Cargos"
    subtitle="A função na estrutura. O cargo sugere um papel; quem concede acesso é o papel da conta."
    :trilha="['Organização' => route('organization.index'), 'Cargos' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $cargos->total() }} {{ $cargos->total() === 1 ? 'cargo cadastrado' : 'cargos cadastrados' }}
            @if ($cargos->total() > 0)
                · página {{ $cargos->currentPage() }} de {{ max(1, $cargos->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('organization.create')
                <x-ui.button variant="primary" size="sm" :href="route('organization.positions.create')"
                    icon="fa-solid fa-plus">Novo cargo</x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('organization.positions.index')" :limpar="route('organization.positions.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Nome do cargo ou descrição" data-nf-busca />
        </div>

        <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Todas" data-nf-autosubmit />

        <x-ui.select label="Setor" name="setor" :opcoes="$setores" :value="request('setor')"
            placeholder="Todos os setores" data-nf-autosubmit />
    </x-ui.filters>

    @if ($cargos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum cargo com estes filtros"
                    text="Mude a busca, a situação ou o setor e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('organization.positions.index')"
                        icon="fa-solid fa-rotate-left">Limpar filtros</x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhum cargo cadastrado"
                    text="Cargos dão nome à função de cada pessoa na estrutura." />
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Cargos desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Cargo" padrao="name" />
                            <x-ui.sort-link coluna="level" rotulo="Nível" />
                            <th scope="col">Setor</th>
                            <th scope="col">Papel sugerido</th>
                            <th scope="col" class="text-end">Vínculos</th>
                            <th scope="col">Situação</th>
                            <x-ui.sort-link coluna="created_at" rotulo="Cadastrado em" />
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cargos as $cargo)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('organization.positions.show', $cargo) }}">
                                        {{ $cargo->name }}
                                    </a>
                                    @if ($cargo->description)
                                        <div class="nf-text-muted-2 small">{{ $cargo->description }}</div>
                                    @endif
                                </td>
                                <td class="text-end nf-mono">{{ (int) $cargo->level }}</td>
                                <td>
                                    @if ($cargo->department)
                                        <a href="{{ route('organization.departments.show', $cargo->department) }}">
                                            {{ $cargo->department->name }}
                                        </a>
                                    @else
                                        <span class="nf-text-muted-2">Sem setor</span>
                                    @endif
                                </td>
                                <td>{{ $cargo->suggestedRole?->name ?? 'Nenhum' }}</td>
                                <td class="text-end nf-mono">{{ $cargo->employments_count }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('organization', $cargo->active ? 'active' : 'inactive') }}">
                                        {{ StatusCatalog::label('organization', $cargo->active ? 'active' : 'inactive') }}
                                    </span>
                                </td>
                                <td class="nf-mono">{{ Formatters::date($cargo->created_at) }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        @can('organization.update')
                                            <x-ui.button variant="ghost" size="sm"
                                                :href="route('organization.positions.edit', $cargo)"
                                                icon="fa-solid fa-pen">Editar</x-ui.button>
                                        @endcan

                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('organization.positions.show', $cargo)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @can('organization.delete')
                                            <x-ui.action-form :acao="route('organization.positions.destroy', $cargo)"
                                                rotulo="Excluir" titulo="Excluir {{ $cargo->name }}?"
                                                texto="Só é possível excluir um cargo que não aparece em nenhum vínculo." />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$cargos" rotulo="cargos" />
        </x-ui.card>
    @endif
</x-layouts.app>
