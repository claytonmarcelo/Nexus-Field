<x-layouts.app
    title="Papéis"
    subtitle="O que cada tipo de acesso enxerga. Os cinco de sistema vêm do catálogo; personalizados nascem aqui."
    :trilha="['Gestão' => null, 'Papéis' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $papeis->count() }} {{ $papeis->count() === 1 ? 'papel na empresa' : 'papéis na empresa' }}
            · {{ $sistema }} de sistema
        </p>

        <div class="nf-list-acoes">
            @can('users.view')
                <x-ui.button variant="ghost" size="sm" :href="route('users.index')" icon="fa-solid fa-user-shield">
                    Usuários
                </x-ui.button>
            @endcan

            @can('roles.create')
                <x-ui.button variant="primary" size="sm" :href="route('roles.create')" icon="fa-solid fa-plus">
                    Novo papel
                </x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('roles.index')" :limpar="route('roles.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Nome ou slug do papel" data-nf-busca />
        </div>
    </x-ui.filters>

    @if ($papeis->isEmpty())
        <x-ui.card title="Nada para listar">
            <x-ui.state tone="empty" title="Nenhum papel nesta empresa"
                text="Sem papel não existe conta com alcance. A sincronização do sistema cria os cinco de catálogo." />
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Papéis desta empresa e o que cada um alcança</caption>
                    <thead>
                        <tr>
                            <th scope="col">Papel</th>
                            <th scope="col" class="text-end">Permissões</th>
                            <th scope="col" class="text-end">Contas</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($papeis as $papel)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('roles.show', $papel) }}">{{ $papel->name }}</a>
                                    <div class="nf-mono nf-text-muted-2">{{ $papel->slug }}</div>
                                    @if ($papel->is_system)
                                        <span class="nf-status nf-status-open">sistema</span>
                                    @else
                                        <span class="nf-status nf-status-draft">personalizado</span>
                                    @endif
                                    @if ($papel->description)
                                        <div class="nf-text-muted-2">{{ $papel->description }}</div>
                                    @endif
                                </td>
                                <td class="text-end nf-mono">{{ $papel->permissions_count }}</td>
                                <td class="text-end nf-mono">{{ $papel->users_count }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        <x-ui.button variant="ghost" size="sm" :href="route('roles.show', $papel)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @if (! $papel->is_system)
                                            @can('roles.update')
                                                <x-ui.button variant="ghost" size="sm" :href="route('roles.edit', $papel)"
                                                    icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endcan

                                            @can('roles.delete')
                                                <x-ui.action-form :acao="route('roles.destroy', $papel)"
                                                    rotulo="Excluir" titulo="Excluir o papel {{ $papel->name }}?"
                                                    texto="Só exclui papel que nenhuma conta usa. As permissões concedidas por ele somem junto." />
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
</x-layouts.app>
