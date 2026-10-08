{{-- Navbar do layout autenticado. O hambúrguer usa o contrato do AdminLTE 4
     ([data-lte-toggle="sidebar"], estado no <body>), e sair é POST com CSRF:
     logout por GET é o que permite deslogar alguém com uma imagem quebrada. --}}
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
