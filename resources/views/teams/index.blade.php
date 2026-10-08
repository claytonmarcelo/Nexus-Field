@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Equipes"
    subtitle="Quem trabalha junto na mesma região, sob a liderança de um técnico."
    :trilha="['Cadastros' => null, 'Equipes' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $equipes->total() }} {{ $equipes->total() === 1 ? 'equipe montada' : 'equipes montadas' }}
            @if ($equipes->total() > 0)
                · página {{ $equipes->currentPage() }} de {{ max(1, $equipes->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('teams.create')
                <x-ui.button variant="primary" size="sm" :href="route('teams.create')" icon="fa-solid fa-plus">
                    Nova equipe
                </x-ui.button>
            @endcan
        </div>
    </div>

    @can('teams.view')
        <x-ui.filters :rota="route('teams.index')" :limpar="route('teams.index')">
            <div class="nf-filters-largo">
                <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                    placeholder="Nome da equipe ou região" data-nf-busca />
            </div>

            <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
                placeholder="Todas" data-nf-autosubmit />

            <x-ui.select label="Região" name="regiao" :opcoes="$regioes" :value="request('regiao')"
                placeholder="Todas as regiões" data-nf-autosubmit />
        </x-ui.filters>
    @endcan

    @if ($equipes->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma equipe com estes filtros"
                    text="Mude a busca, a situação ou a região e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('teams.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhuma equipe montada"
                    text="Equipe é o agrupamento de técnicos por região; a distribuição de ordem funciona sem ela, mas com equipe a carga divide melhor.">
                    @can('teams.create')
                        <x-ui.button variant="primary" size="sm" :href="route('teams.create')" icon="fa-solid fa-plus">
                            Cadastrar equipe
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Equipes desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Equipe" padrao="name" />
                            <th scope="col">Líder</th>
                            <x-ui.sort-link coluna="region" rotulo="Região" />
                            <th scope="col" class="text-end">No quadro</th>
                            <x-ui.sort-link coluna="status" rotulo="Situação" />
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($equipes as $equipe)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('teams.show', $equipe) }}">{{ $equipe->name }}</a>
                                    <div class="nf-text-muted-2 small">
                                        montada em {{ Formatters::date($equipe->created_at) }}
                                    </div>
                                </td>
                                <td>
                                    @if ($equipe->leader)
                                        <a href="{{ route('technicians.show', $equipe->leader) }}">{{ $equipe->leader->name }}</a>
                                    @else
                                        <span class="nf-text-muted-2">Sem líder</span>
                                    @endif
                                </td>
                                <td>{{ $equipe->region ?: Formatters::TIME_NULL }}</td>
                                <td class="text-end nf-mono">{{ $equipe->membros_count }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('team', $equipe->status) }}">
                                        {{ StatusCatalog::label('team', $equipe->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="nf-table-actions">
                                        @can('teams.update')
                                            <x-ui.button variant="ghost" size="sm" :href="route('teams.edit', $equipe)"
                                                icon="fa-solid fa-pen">Editar</x-ui.button>
                                        @endcan

                                        <x-ui.button variant="ghost" size="sm" :href="route('teams.show', $equipe)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @can('teams.delete')
                                            <x-ui.action-form :acao="route('teams.destroy', $equipe)" rotulo="Excluir"
                                                titulo="Excluir {{ $equipe->name }}?"
                                                texto="Só é possível excluir equipe com o quadro vazio. Inativar preserva o histórico." />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$equipes" rotulo="equipes" />
        </x-ui.card>
    @endif
</x-layouts.app>
