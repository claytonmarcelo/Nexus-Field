@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Vínculos"
    subtitle="Quem ocupa qual cargo em cada filial e setor. Encerrar uma passagem nunca apaga o histórico."
    :trilha="['Organização' => route('organization.index'), 'Vínculos' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $vinculos->total() }} {{ $vinculos->total() === 1 ? 'passagem listada' : 'passagens listadas' }}
            @if ($vinculos->total() > 0)
                · página {{ $vinculos->currentPage() }} de {{ max(1, $vinculos->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('organization.create')
                <x-ui.button variant="primary" size="sm" :href="route('organization.employments.create')"
                    icon="fa-solid fa-plus">Novo vínculo</x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('organization.employments.index')" :limpar="route('organization.employments.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Código da passagem ou nome da conta" data-nf-busca />
        </div>

        <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Todas" data-nf-autosubmit />

        <x-ui.select label="Filial" name="filial" :opcoes="$filiais" :value="request('filial')"
            placeholder="Todas as filiais" data-nf-autosubmit />

        <x-ui.select label="Setor" name="setor" :opcoes="$setores" :value="request('setor')"
            placeholder="Todos os setores" data-nf-autosubmit />
    </x-ui.filters>

    @if ($vinculos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma passagem com estes filtros"
                    text="Mude a busca, a situação, a filial ou o setor e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('organization.employments.index')"
                        icon="fa-solid fa-rotate-left">Limpar filtros</x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhum vínculo registrado"
                    text="Vínculos ligam uma conta da empresa a filial, setor e cargo." />
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Passagens organizacionais, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="employee_code" rotulo="Código" padrao="employee_code" />
                            <th scope="col">Conta</th>
                            <th scope="col">Filial</th>
                            <th scope="col">Setor</th>
                            <th scope="col">Cargo</th>
                            <th scope="col">Tipo</th>
                            <x-ui.sort-link coluna="status" rotulo="Situação" />
                            <x-ui.sort-link coluna="started_at" rotulo="Início" />
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vinculos as $vinculo)
                            <tr>
                                <td>
                                    <a class="nf-mono fw-semibold"
                                        href="{{ route('organization.employments.show', $vinculo) }}">
                                        {{ $vinculo->employee_code }}
                                    </a>
                                </td>
                                <td>{{ $vinculo->user?->name ?? 'Conta removida' }}</td>
                                <td>{{ $vinculo->branch?->name ?? 'Sem filial' }}</td>
                                <td>{{ $vinculo->department?->name ?? 'Sem setor' }}</td>
                                <td>{{ $vinculo->position?->name ?? 'Sem cargo' }}</td>
                                <td>{{ StatusCatalog::label('employment_type', $vinculo->employment_type) }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('employment', $vinculo->status) }}">
                                        {{ StatusCatalog::label('employment', $vinculo->status) }}
                                    </span>
                                </td>
                                <td class="nf-mono">{{ Formatters::date($vinculo->started_at) }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        @can('organization.update')
                                            <x-ui.button variant="ghost" size="sm"
                                                :href="route('organization.employments.edit', $vinculo)"
                                                icon="fa-solid fa-pen">Editar</x-ui.button>
                                        @endcan

                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('organization.employments.show', $vinculo)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @can('organization.delete')
                                            <x-ui.action-form :acao="route('organization.employments.destroy', $vinculo)"
                                                rotulo="Encerrar" icon="fa-solid fa-user-minus" :perigo="false"
                                                titulo="Encerrar a passagem de {{ $vinculo->employee_code }}?"
                                                texto="A linha fica marcada como desligada e permanece no histórico. Nada é apagado." />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$vinculos" rotulo="vínculos" />
        </x-ui.card>
    @endif
</x-layouts.app>
