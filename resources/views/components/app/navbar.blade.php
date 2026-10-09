{{-- Navbar do layout autenticado. O hambúrguer usa o contrato do AdminLTE 4
     ([data-lte-toggle="sidebar"], estado no <body>), e sair é POST com CSRF:
     logout por GET é o que permite deslogar alguém com uma imagem quebrada.

     O sino mora entre o toggle de tema e o menu da conta, e lê a bandeja de quem
     está logado — a consulta filtra por `user_id` antes de qualquer coisa, então
     a contagem da barra é sempre da pessoa, nunca da empresa. Ele não some para
     papel nenhum: `notifications.view` é de todos os papéis, e um sino que não
     existe para quem atende no campo é aviso que chega tarde. --}}
@props(['user'])

<nav class="app-header navbar navbar-expand">
    <div class="container-fluid nf-content">
        <ul class="navbar-nav">
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    data-lte-toggle="sidebar"
                    aria-label="Abrir ou fechar o menu lateral"
                    title="Abrir ou fechar o menu lateral"
                >
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto">
            <li class="nav-item nf-nav-item-toggle">
                <x-ui.theme-toggle />
            </li>

            @if ($user->hasPermission('notifications.view') && Route::has('notifications.index'))
                @php
                    $pendentes = \App\Models\Notification::query()
                        ->where('user_id', $user->id)
                        ->unread()
                        ->count();

                    $recentes = \App\Models\Notification::query()
                        ->where('user_id', $user->id)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->limit(6)
                        ->get();
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
