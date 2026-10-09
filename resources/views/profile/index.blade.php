@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $raiz = $conta->isRoot();
    $temBandeja = $conta->hasPermission('notifications.view');
@endphp

<x-layouts.app
    title="Meu perfil"
    subtitle="A sua conta por dentro: como você se apresenta, qual é a sua chave e com o que ela se abre."
    :trilha="['Minha conta' => route('profile.show'), 'Perfil' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('user', $conta->status) }}">
                {{ StatusCatalog::label('user', $conta->status) }}
            </span>
            @if ($raiz)
                <span class="nf-status nf-status-open ms-2">conta raiz</span>
            @endif
            @if ($conta->technician)
                <span class="nf-status nf-status-done ms-2">conduz campo</span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('dashboard')" icon="fa-solid fa-gauge-high">
                Voltar ao painel
            </x-ui.button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Como você se apresenta" subtitle="Nome e telefone: o que o time e o cliente leem de você.">
                <form method="POST" action="{{ route('profile.update') }}" data-nf-guard novalidate>
                    @csrf
                    @method('PUT')

                    <div class="nf-form-grade">
                        <x-ui.input label="Nome" name="name" :value="$conta->name" required
                            placeholder="Como você quer aparecer" autocomplete="name" />

                        <x-ui.input label="Telefone" name="phone" :value="$conta->phone" inputmode="tel"
                            placeholder="(11) 4000-0000" autocomplete="tel"
                            hint="O número que a empresa liga para você. Fica vazio se você apagar." />
                    </div>

                    <div class="nf-form-acoes">
                        <x-ui.button type="submit" variant="primary" icon="fa-solid fa-floppy-disk" data-loading="false">
                            Salvar dados
                        </x-ui.button>

                        <p class="nf-text-muted-2 mb-0 small">
                            O que mudar aqui sai carimbado na auditoria, com o antes e o depois.
                        </p>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card title="Chave de acesso" subtitle="O e-mail é o que se digita no login: mexer nele exige a senha atual."
                class="mt-3">
                @if ($raiz)
                    <x-ui.state tone="denied" title="A raiz não troca de chave"
                        text="Esta é a identidade administrativa do sistema. O e-mail dela é a última porta, e a guarda que impede a troca mora no modelo, não na tela: nem o administrador consegue por aqui." />
                @else
                    <form method="POST" action="{{ route('profile.email') }}" data-nf-guard novalidate>
                        @csrf
                        @method('PUT')

                        <div class="nf-form-grade">
                            <x-ui.input label="E-mail atual" name="email" type="email" :value="$conta->email" required
                                autocomplete="username" hint="É ele que abre a porta a partir da troca." />

                            <x-ui.input label="Senha atual" name="senha_atual" type="password" required
                                autocomplete="current-password" hint="Prova de que quem está aqui é você." />
                        </div>

                        <div class="nf-form-acoes">
                            <x-ui.button type="submit" variant="soft-primary" icon="fa-solid fa-key">
                                Trocar minha chave
                            </x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="Senha" subtitle="Renovar a senha fecha as outras sessões da conta na mesma hora."
                class="mt-3">
                <form method="POST" action="{{ route('profile.password') }}" data-nf-guard novalidate>
                    @csrf
                    @method('PUT')

                    <div class="nf-form-grade">
                        <x-ui.input label="Senha atual" name="senha_atual" type="password" required
                            autocomplete="current-password" />

                        <x-ui.input label="Senha nova" name="password" type="password" required
                            autocomplete="new-password" hint="Mínimo de 10 caracteres, com letras e números." />

                        <x-ui.input label="Repita a senha nova" name="password_confirmation" type="password" required
                            autocomplete="new-password" />
                    </div>

                    <div class="nf-form-acoes">
                        <x-ui.button type="submit" variant="primary" icon="fa-solid fa-shield-halved">
                            Renovar senha
                        </x-ui.button>

                        <p class="nf-text-muted-2 mb-0 small">
                            A senha não aparece em log, na auditoria nem em tela: o que fica registrado é o ato.
                        </p>
                    </div>
                </form>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Quem você é nesta casa" subtitle="O que a empresa sabe da sua conta, lido do banco.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Empresa</dt>
                        <dd>
                            @can('settings.view')
                                <a href="{{ route('settings.index') }}">{{ $conta->company?->name ?? 'Sem empresa vinculada' }}</a>
                            @else
                                {{ $conta->company?->name ?? 'Sem empresa vinculada' }}
                            @endcan
                        </dd>
                    </div>
                    <div>
                        <dt>Plano</dt>
                        <dd>{{ $conta->company?->plan?->name ?? Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Papéis</dt>
                        <dd>{{ $conta->roles->pluck('name')->join(', ') ?: 'nenhum concedido' }}</dd>
                    </div>
                    <div>
                        <dt>Chave de acesso</dt>
                        <dd class="nf-mono">{{ $conta->email }}</dd>
                    </div>
                    <div>
                        <dt>Telefone</dt>
                        <dd class="nf-mono">{{ $conta->phone ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Último acesso</dt>
                        <dd class="nf-mono">
                            {{ $conta->last_login_at ? Formatters::dateTime($conta->last_login_at) : 'esta é a primeira vez' }}
                        </dd>
                    </div>
                    <div>
                        <dt>Conta aberta em</dt>
                        <dd class="nf-mono">{{ Formatters::date($conta->created_at) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Aparência" subtitle="A chave global mora no cabeçalho; aqui ela aparece de novo, grande."
                class="mt-3">
                <p class="nf-text-muted-2">
                    Dia claro ou noite bonita: a escolha é sua, fica guardada neste navegador e vale para todas as
                    telas da casa — painel, fichas, calendário e relatórios.
                </p>

                <div class="d-flex align-items-center gap-3">
                    <x-ui.theme-toggle />
                    <span class="nf-mono small nf-text-muted-2">clique para virar a chave</span>
                </div>
            </x-ui.card>

            @if ($conta->technician)
                <x-ui.card title="Suas próximas janelas" subtitle="A mesma agenda que o calendário mostra, na sua mão."
                    class="mt-3">
                    @if ($janelas->isEmpty())
                        <x-ui.state tone="empty" title="Escala livre"
                            text="Nenhuma janela marcada para você a partir de agora. O calendário é o lugar de ver o que já passou." />
                    @else
                        <ul class="nf-itens mb-0">
                            @foreach ($janelas as $janela)
                                <li class="nf-item-linha nf-item-linha-lado">
                                    <div>
                                        <p class="mb-0 fw-semibold">
                                            @can('agenda.view')
                                                <a href="{{ route('agenda.show', $janela) }}">{{ $janela->title }}</a>
                                            @else
                                                {{ $janela->title }}
                                            @endcan
                                        </p>
                                        <p class="mb-0 nf-text-muted-2 small">
                                            {{ $janela->client?->name ?? 'Sem cliente preso' }}
                                            @if ($janela->serviceOrder && $conta->hasPermission('orders.view'))
                                                ·
                                                <a href="{{ route('orders.show', $janela->serviceOrder) }}">
                                                    {{ $janela->serviceOrder->number }}
                                                </a>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="nf-mono">{{ Formatters::dateTime($janela->starts_at) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            @endif

            @if ($conta->client || $conta->technician)
                <x-ui.card title="Onde mais esta conta existe" subtitle="Vínculos de ficha: cada um tem sua própria tela."
                    class="mt-3">
                    <ul class="nf-fact-list mb-0">
                        @if ($conta->technician)
                            <li>
                                <span>Ficha de técnico</span>
                                <strong>
                                    @can('technicians.view')
                                        <a href="{{ route('technicians.show', $conta->technician) }}">{{ $conta->technician->name }}</a>
                                    @else
                                        {{ $conta->technician->name }}
                                    @endcan
                                </strong>
                            </li>
                        @endif

                        @if ($conta->client)
                            <li>
                                <span>Carteira de cliente</span>
                                <strong>
                                    @can('clients.view')
                                        <a href="{{ route('clients.show', $conta->client) }}">{{ $conta->client->name }}</a>
                                    @else
                                        {{ $conta->client->name }}
                                    @endcan
                                </strong>
                            </li>
                        @endif
                    </ul>
                </x-ui.card>
            @endif

            @if ($temBandeja)
                <x-ui.card title="Sino" subtitle="O que já foi dito a você, e o que ainda espera leitura." class="mt-3">
                    <p class="nf-text-muted-2">
                        {{ \App\Models\Notification::query()->where('user_id', $conta->id)->unread()->count() }}
                        aviso(s) pendente(s) na sua bandeja.
                    </p>

                    <x-ui.button variant="ghost" size="sm" :href="route('notifications.index')" icon="fa-regular fa-bell">
                        Abrir o centro de notificações
                    </x-ui.button>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
