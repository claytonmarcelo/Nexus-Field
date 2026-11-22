{{-- Cabeçalho compartilhado das telas abertas. --}}
@props(['withLogin' => true])

<header class="nf-public-header">
    <div class="container">
        <div class="nf-public-header-inner">
            <a class="nf-brand" href="{{ route('welcome') }}" aria-label="NEXUS-FIELD, página inicial">
                <x-ui.brand-mark />
                <span class="nf-wordmark">
                    <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
                </span>
            </a>

            <div class="nf-public-header-actions">
                {{-- Padrão de mercado: a chave de tema abre o grupo de ações no canto
                     superior direito, antes de criar conta/entrar; a marca fica sozinha
                     à esquerda. --}}
                <x-ui.theme-toggle />

                @auth
                    {{-- Sessão aberta não tem motivo para ver a tela de entrada; o
                         caminho honesto é o painel. --}}
                    <x-ui.button href="{{ route('dashboard') }}" variant="primary" size="sm" icon="fa-solid fa-gauge-high">
                        Painel
                    </x-ui.button>
                @elseif ($withLogin && Route::has('register'))
                    <x-ui.button href="{{ route('register') }}" variant="outline-primary" size="sm" icon="fa-solid fa-user-plus">
                        Criar conta
                    </x-ui.button>

                    @if (Route::has('login'))
                        <x-ui.button href="{{ route('login') }}" variant="primary" size="sm" icon="fa-solid fa-key">
                            Entrar
                        </x-ui.button>
                    @endif
                @endif
            </div>
        </div>
    </div>
</header>
