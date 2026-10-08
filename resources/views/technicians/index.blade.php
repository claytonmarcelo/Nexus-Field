@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Técnicos"
    subtitle="Quem atende em campo: escala, região, especialidade e a última localização medida."
    :trilha="['Cadastros' => null, 'Técnicos' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $tecnicos->total() }} {{ $tecnicos->total() === 1 ? 'técnico na escala' : 'técnicos na escala' }}
            @if ($tecnicos->total() > 0)
                · página {{ $tecnicos->currentPage() }} de {{ max(1, $tecnicos->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('technicians.create')
                <x-ui.button variant="primary" size="sm" :href="route('technicians.create')" icon="fa-solid fa-plus">
                    Novo técnico
                </x-ui.button>
            @endcan
        </div>
    </div>

    @can('technicians.view')
        <x-ui.filters :rota="route('technicians.index')" :limpar="route('technicians.index')">
            <div class="nf-filters-largo">
                <x-ui.input
                    label="Buscar"
                    name="busca"
                    :value="request('busca')"
                    placeholder="Nome, documento, e-mail, telefone ou região"
                    data-nf-busca
                />
            </div>

            <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
                placeholder="Todas" data-nf-autosubmit />

            <x-ui.select label="Região" name="regiao" :opcoes="$regioes" :value="request('regiao')"
                placeholder="Todas as regiões" data-nf-autosubmit />

            <x-ui.select label="Especialidade" name="especialidade" :opcoes="$especialidades"
                :value="request('especialidade')" placeholder="Qualquer uma" data-nf-autosubmit />
        </x-ui.filters>
    @endcan

    @if ($tecnicos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum técnico com estes filtros"
                    text="Ajuste a busca, a situação, a região ou a especialidade e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('technicians.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhum técnico cadastrado nesta empresa"
                    text="Sem técnico na escala não há para quem distribuir uma ordem de serviço.">
                    @can('technicians.create')
                        <x-ui.button variant="primary" size="sm" :href="route('technicians.create')" icon="fa-solid fa-plus">
                            Cadastrar técnico
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Técnicos desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Técnico" padrao="name" />
                            <th scope="col">Especialidades</th>
                            <th scope="col">Contato</th>
                            <x-ui.sort-link coluna="region" rotulo="Região" />
                            <x-ui.sort-link coluna="status" rotulo="Situação" />
                            <th scope="col">Última localização</th>
                            <th scope="col" class="text-end">Equipes</th>
                            <th scope="col" class="text-end">Ordens</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tecnicos as $tecnico)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('technicians.show', $tecnico) }}">{{ $tecnico->name }}</a>
                                    <div class="nf-text-muted-2 nf-mono">{{ $tecnico->document ?: Formatters::TIME_NULL }}</div>
                                    @if ($tecnico->trashed())
                                        <div class="nf-status nf-status-canceled">
                                            Excluído em {{ Formatters::date($tecnico->deleted_at) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($tecnico->specialties as $especialidade)
                                        <span class="nf-status nf-status-open">{{ $especialidade->name }}</span>
                                    @empty
                                        <span class="nf-text-muted-2">Sem especialidade</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($tecnico->phone)
                                        <div class="nf-mono">{{ $tecnico->phone }}</div>
                                    @endif
                                    <div class="nf-text-muted-2">{{ $tecnico->email ?: 'Sem e-mail' }}</div>
                                </td>
                                <td>{{ $tecnico->region ?: Formatters::TIME_NULL }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('technician', $tecnico->status) }}">
                                        {{ StatusCatalog::label('technician', $tecnico->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($tecnico->last_location_at)
                                        <div class="nf-mono small">
                                            {{ Formatters::decimal($tecnico->latitude, 5) }},
                                            {{ Formatters::decimal($tecnico->longitude, 5) }}
                                        </div>
                                        <div class="nf-text-muted-2 small">{{ Formatters::dateTime($tecnico->last_location_at) }}</div>
                                    @else
                                        <span class="nf-text-muted-2">Nunca registrada</span>
                                    @endif
                                </td>
                                <td class="text-end nf-mono">{{ $tecnico->teams_count }}</td>
                                <td class="text-end nf-mono">{{ $tecnico->service_orders_count }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        @unless ($tecnico->trashed())
                                            @can('technicians.update')
                                                <x-ui.button variant="ghost" size="sm" :href="route('technicians.edit', $tecnico)"
                                                    icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endcan
                                        @endunless

                                        @if ($tecnico->trashed())
                                            @can('technicians.delete')
                                                <x-ui.action-form :acao="route('technicians.restore', $tecnico)" metodo="PATCH"
                                                    rotulo="Restaurar" titulo="Restaurar {{ $tecnico->name }}?"
                                                    texto="O técnico volta à escala e pode receber ordem de novo."
                                                    icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                                            @endcan
                                        @else
                                            <x-ui.button variant="ghost" size="sm" :href="route('technicians.show', $tecnico)"
                                                icon="fa-solid fa-eye">Abrir</x-ui.button>

                                            @can('technicians.delete')
                                                <x-ui.action-form :acao="route('technicians.destroy', $tecnico)"
                                                    rotulo="Excluir" titulo="Excluir {{ $tecnico->name }}?"
                                                    texto="Só é possível excluir quem ainda não gerou ordem, check-in ou compromisso. Inativar preserva o histórico." />
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$tecnicos" rotulo="técnicos" />
        </x-ui.card>
    @endif
</x-layouts.app>
