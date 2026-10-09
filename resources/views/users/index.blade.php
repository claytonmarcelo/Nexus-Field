@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Usuários"
    subtitle="Folha de ponto do acesso: quem entra nesta empresa, com que papéis e pela última vez quando."
    :trilha="['Gestão' => null, 'Usuários' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $contas->total() }} {{ $contas->total() === 1 ? 'conta ativa na folha' : 'contas na folha' }}
            @if ($teto !== null)
                · plano permite {{ $teto }} {{ $teto === 1 ? 'conta' : 'contas' }}
            @endif
            @if ($contas->total() > 0)
                · página {{ $contas->currentPage() }} de {{ max(1, $contas->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('roles.view')
                <x-ui.button variant="ghost" size="sm" :href="route('roles.index')" icon="fa-solid fa-key">
                    Papéis
                </x-ui.button>
            @endcan

            @can('users.create')
                <x-ui.button variant="primary" size="sm" :href="route('users.create')" icon="fa-solid fa-plus">
                    Nova conta
                </x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('users.index')" :limpar="route('users.index')">
        <div class="nf-filters-largo">
            <x-ui.input
                label="Buscar"
                name="busca"
                :value="request('busca')"
                placeholder="Nome ou e-mail"
                data-nf-busca
            />
        </div>

        <x-ui.select label="Papel" name="papel" :opcoes="$papeis" :value="request('papel')"
            placeholder="Qualquer papel" data-nf-autosubmit />

        <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Todas" data-nf-autosubmit />

        <x-ui.input label="Criada a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($contas->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma conta com estes filtros"
                    text="Ajuste a busca, o papel, a situação ou o período e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('users.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhuma conta nesta empresa"
                    text="Sem conta, ninguém além da raiz entra no sistema. Cadastre a primeira.">
                    @can('users.create')
                        <x-ui.button variant="primary" size="sm" :href="route('users.create')" icon="fa-solid fa-plus">
                            Cadastrar conta
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Contas desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Conta" padrao="name" />
                            <x-ui.sort-link coluna="email" rotulo="E-mail" />
                            <th scope="col">Papéis</th>
                            <x-ui.sort-link coluna="status" rotulo="Situação" />
                            <x-ui.sort-link coluna="last_login_at" rotulo="Último acesso" />
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contas as $conta)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('users.show', $conta) }}">{{ $conta->name }}</a>
                                    @if ($conta->is(request()->user()))
                                        <div class="nf-text-muted-2">é você</div>
                                    @endif
                                    @if ($conta->technician)
                                        <div class="nf-text-muted-2">ficha de técnico: {{ $conta->technician->name }}</div>
                                    @endif
                                </td>
                                <td class="nf-mono">{{ $conta->email }}</td>
                                <td>
                                    @if ($conta->roles->isEmpty())
                                        <span class="nf-status nf-status-draft">sem papel</span>
                                    @else
                                        @foreach ($conta->roles as $papel)
                                            <span class="nf-status nf-status-draft">{{ $papel->name }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('user', $conta->status) }}">
                                        {{ StatusCatalog::label('user', $conta->status) }}
                                    </span>
                                </td>
                                <td class="nf-mono">
                            {{ $conta->last_login_at ? Formatters::dateTime($conta->last_login_at) : 'nunca entrou' }}
                                </td>
                                <td>
                                    <div class="nf-table-actions">
                                        <x-ui.button variant="ghost" size="sm" :href="route('users.show', $conta)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>

                                        @if (! $conta->isRoot())
                                            @can('users.update')
                                                <x-ui.button variant="ghost" size="sm" :href="route('users.edit', $conta)"
                                                    icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endcan

                                            @can('users.delete')
                                                @unless ($conta->is(request()->user()))
                                                    <x-ui.action-form :acao="route('users.destroy', $conta)"
                                                        rotulo="Excluir" titulo="Excluir a conta de {{ $conta->name }}?"
                                                        texto="A conta sai do acesso, mas o que ela registrou fica. Se houver ficha de técnico vinculada, desvincule antes." />
                                                @endunless
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$contas" rotulo="contas" />
        </x-ui.card>
    @endif
</x-layouts.app>
