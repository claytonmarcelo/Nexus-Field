{{-- Navbar do layout autenticado. O hambúrguer usa o contrato do AdminLTE 4
     ([data-lte-toggle="sidebar"], estado no <body>), e sair é POST com CSRF:
     logout por GET é o que permite deslogar alguém com uma imagem quebrada.

     As três barras são desenhadas em CSS, e não um glifo: elas moram num ícone com
     o rótulo `Menu` ao lado no desktop, e a dobra existe para o estado ter para onde
     ir. Quem lê o `aria-expanded` é o menu.js, que só espelha a classe que o template
     já pôs no <body> — ver resources/js/nexusfield/menu.js.

     A chave de tema fica no grupo de ações à direita aqui no painel; no cabeçalho
     da página inicial ela entra antes da porta de acesso. O sino lê a bandeja de
     quem está logado — a consulta filtra por `user_id` antes de qualquer coisa,
     então a contagem da barra é sempre da pessoa, nunca da empresa. Ele não some
     para papel nenhum: `notifications.view` é de todos os papéis, e um sino que
     não existe para quem atende no campo é aviso que chega tarde. O botão de
     cache é global de verdade: só a raiz o vê, porque o efeito alcança todas as
     empresas; o `*` do administrador de uma empresa não chega aqui. --}}
@props(['user'])

<nav class="app-header navbar navbar-expand">
    <div class="container-fluid nf-content">
        <ul class="navbar-nav">
            <li class="nav-item d-flex align-items-center">
                <button
                    type="button"
                    class="nf-nav-toggle"
                    data-lte-toggle="sidebar"
                    data-nf-menu-toggle
                    aria-controls="navigation"
                    aria-expanded="true"
                    aria-label="Recolher o menu lateral"
                    title="Recolher o menu lateral"
                >
                    <span class="nf-nav-toggle-icone">
                        <span class="nf-nav-toggle-bar" aria-hidden="true"></span>
                        <span class="nf-nav-toggle-bar" aria-hidden="true"></span>
                        <span class="nf-nav-toggle-bar" aria-hidden="true"></span>
                    </span>
                    <span class="nf-nav-toggle-texto" aria-hidden="true">Menu</span>
                </button>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto d-flex align-items-center">
            <li class="nav-item nf-nav-item-toggle d-flex align-items-center">
                <x-ui.theme-toggle />
            </li>

            @if ($user->isRoot() && Route::has('system.cache.clear'))
                <li class="nav-item d-flex align-items-center">
                    <x-ui.action-form
                        :acao="route('system.cache.clear')"
                        metodo="POST"
                        rotulo="Limpar cache"
                        titulo="Limpar o cache global?"
                        texto="Nenhum dado de empresa, cliente, ordem ou arquivo será apagado. Somente a configuração, as rotas, os eventos e as views compiladas serão reconstruídos."
                        :perigo="false"
                        variante="ghost"
                        icon="fa-solid fa-broom"
                        button-class="nf-cache-toggle"
                        button-title="Limpar cache global"
                        button-aria-label="Limpar cache global"
                    />
                </li>
            @endif

            @if ($user->hasPermission('notifications.view') && Route::has('notifications.index'))
                @php
                    $sino = \App\Support\NotificationBadge::para($user);
                    $pendentes = $sino['pendentes'];
                    $recentes = $sino['recentes'];
                @endphp

                <li class="nav-item dropdown nf-nav-bell">
                    <button type="button" class="nav-link nf-bell-toggle" data-bs-toggle="dropdown"
                        aria-expanded="false" title="Notificações"
                        aria-label="{{ $pendentes > 0 ? "Notificações: {$pendentes} pendentes de leitura" : 'Notificações: nada pendente de leitura' }}">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        @if ($pendentes > 0)
                            <span class="nf-bell-count" aria-hidden="true">{{ $pendentes > 99 ? '99+' : $pendentes }}</span>
                        @endif
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end nf-bell-menu">
                        <li class="nf-bell-head">
                            <span class="nf-bell-head-title">Notificações</span>
                            <span class="nf-bell-head-count">{{ $pendentes }} {{ $pendentes === 1 ? 'pendente' : 'pendentes' }}</span>
                        </li>

                        @forelse ($recentes as $aviso)
                            <li>
                                <a class="nf-bell-item{{ $aviso->read_at === null ? ' nf-bell-nova' : '' }}"
                                    href="{{ $aviso->link ?? route('notifications.index') }}">
                                    <span class="{{ \App\Support\StatusCatalog::badge('notification', $aviso->type) }}">
                                        {{ \App\Support\StatusCatalog::label('notification', $aviso->type) }}
                                    </span>
                                    <span class="nf-bell-item-title">{{ $aviso->title }}</span>
                                    <span class="nf-bell-item-when">{{ \App\Support\Formatters::dateTime($aviso->created_at) }}</span>
                                </a>
                            </li>
                        @empty
                            <li>
                                <p class="nf-bell-empty">
                                    Nenhum aviso ainda. O sino toca quando um fato da operação pede a
                                    sua leitura: ordem aberta ou atribuída, chamado novo, peça abaixo
                                    do mínimo, vencimento chegando.
                                </p>
                            </li>
                        @endforelse

                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="nf-bell-foot" href="{{ route('notifications.index') }}">
                                <i class="fa-solid fa-inbox me-2" aria-hidden="true"></i>
                                Centro de notificações
                            </a>
                        </li>
                    </ul>
                </li>
            @endif

            <li class="nav-item dropdown">
                <button
                    type="button"
                    class="nav-link nf-nav-user"
                    data-bs-toggle="dropdown"
                    data-bs-display="static"
                    aria-expanded="false"
                >
                    <span class="nf-avatar nf-avatar-sm" aria-hidden="true">{{ $user->initials() }}</span>
                    <span class="nf-nav-user-name">{{ $user->name }}</span>
                    <i class="fa-solid fa-angle-down ms-1" aria-hidden="true"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end nf-user-menu">
                    <li>
                        <div class="nf-user-menu-id">
                            <span class="nf-avatar nf-avatar-lg" aria-hidden="true">{{ $user->initials() }}</span>
                            <div class="nf-user-menu-text">
                                <p class="nf-user-menu-name">{{ $user->name }}</p>
                                <p class="nf-user-menu-mail">{{ $user->email }}</p>
                                <p class="nf-user-menu-org">
                                    {{ $user->company?->name ?? 'Sem empresa vinculada' }}
                                </p>
                                @if (($logoEmpresa = $user->company?->logo_path) !== null)
                                    <img class="nf-nav-logo"
                                        src="{{ asset('storage/'.mb_substr($logoEmpresa, strlen('storage/'))) }}"
                                        alt="Marca de {{ $user->company->name }}"
                                        title="{{ $user->company->name }}">
                                @endif
                            </div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <p class="nf-user-menu-roles">
                            {{ $user->roles->pluck('name')->join(', ') ?: 'Sem papel atribuído' }}
                        </p>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.show') }}">
                            <i class="fa-regular fa-id-badge me-2" aria-hidden="true"></i>
                            Meu perfil
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item nf-user-menu-out">
                                <i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>
                                Sair
                            </button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
