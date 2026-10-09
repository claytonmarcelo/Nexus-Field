@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="$conta->name"
    :subtitle="'Conta de acesso: papéis, carteira vinculada e o último registro de entrada.'"
    :trilha="['Gestão' => route('users.index'), 'Usuários' => route('users.index'), $conta->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('user', $conta->status) }}">
                {{ StatusCatalog::label('user', $conta->status) }}
            </span>
            @if ($conta->isRoot())
                <span class="nf-status nf-status-open ms-2">conta raiz</span>
            @endif
            @if ($conta->is(request()->user()))
                <span class="nf-status nf-status-done ms-2">é você</span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('users.index')" icon="fa-solid fa-list">
                Voltar à folha
            </x-ui.button>

            @if (! $conta->isRoot())
                @can('users.update')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('users.edit', $conta)"
                        icon="fa-solid fa-pen">Editar conta</x-ui.button>
                @endcan

                @can('users.delete')
                    @unless ($conta->is(request()->user()))
                        <x-ui.action-form :acao="route('users.destroy', $conta)" rotulo="Excluir"
                            titulo="Excluir a conta de {{ $conta->name }}?"
                            texto="A conta sai do acesso, mas o que ela registrou fica. Ficha de técnica vinculada precisa ser desvinculada antes." />
                    @endunless
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Cadastro" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $conta->name }}</dd>
                    </div>
                    <div>
                        <dt>E-mail</dt>
                        <dd class="nf-mono">{{ $conta->email }}</dd>
                    </div>
                    <div>
                        <dt>Telefone</dt>
                        <dd class="nf-mono">{{ $conta->phone ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Último acesso</dt>
                        <dd class="nf-mono">
                            {{ $conta->last_login_at ? Formatters::dateTime($conta->last_login_at) : 'nunca entrou' }}
                        </dd>
                    </div>
                    <div>
                        <dt>Criada em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($conta->created_at) }}</dd>
                    </div>
                </dl>

                @if ($conta->isRoot())
                    <p class="nf-text-muted-2 small mb-0 mt-3">
                        Esta é a identidade administrativa do sistema: o e-mail, a empresa e a situação dela não se
                        trocam por tela nenhuma, nem por quem for administrador. A guarda mora no modelo.
                    </p>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Papéis" subtitle="O alcance desta conta é a união das permissões deles.">
                @if ($conta->roles->isEmpty())
                    <x-ui.state tone="empty" title="Nenhum papel"
                        text="A conta entra no sistema e não enxerga nada: todo papel precisa ser concedido." />
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($conta->roles as $papel)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        <a href="{{ route('roles.show', $papel) }}">{{ $papel->name }}</a>
                                        @if ($papel->is_system)
                                            <span class="nf-status nf-status-draft">sistema</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small nf-mono">{{ $papel->slug }}</p>
                                </div>
                                <span class="nf-mono">{{ $papel->permissions->count() }} permissões</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Vínculos" subtitle="O que mais esta conta é, além de um acesso." class="mt-3">
                <ul class="nf-fact-list mb-0">
                    <li>
                        <span>Carteira de cliente</span>
                        <strong>
                            @if ($conta->client)
                                <a href="{{ route('clients.show', $conta->client) }}">{{ $conta->client->name }}</a>
                            @else
                                {{ Formatters::TIME_NULL }}
                            @endif
                        </strong>
                    </li>
                    <li>
                        <span>Ficha de técnico</span>
                        <strong>
                            @if ($conta->technician)
                                <a href="{{ route('technicians.show', $conta->technician) }}">{{ $conta->technician->name }}</a>
                            @else
                                {{ Formatters::TIME_NULL }}
                            @endif
                        </strong>
                    </li>
                    <li>
                        <span>Empresa</span>
                        <strong>{{ $conta->company?->name ?? Formatters::TIME_NULL }}</strong>
                    </li>
                    <li>
                        <span>Plano da empresa</span>
                        <strong>{{ $conta->company?->plan?->name ?? Formatters::TIME_NULL }}</strong>
                    </li>
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
