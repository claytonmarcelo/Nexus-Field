{{-- Cabeçalho compartilhado das telas abertas. --}}
@props(['withLogin' => true])

<header class="nf-public-header">
    <div class="container">
        <div class="nf-public-header-inner">
            <a class="nf-brand" href="{{ route('welcome') }}" aria-label="NEXUS-FIELD, página inicial">
                <x-ui.brand-mark />
                <span class="nf-wordmark">
                    NEXUS<span class="nf-wordmark-accent">-FIELD</span>
                </span>
            </a>

            <div class="nf-public-header-actions">
                <x-ui.theme-toggle />

                @auth
                    {{-- Sessão aberta não tem motivo para ver a tela de entrada; o
                         caminho honesto é o painel. --}}
                    <x-ui.button href="{{ route('dashboard') }}" variant="primary" size="sm" icon="fa-solid fa-gauge-high">
                        Painel
                    </x-ui.button>
                @elseif ($withLogin && Route::has('login'))
                    <x-ui.button href="{{ route('login') }}" variant="primary" size="sm" icon="fa-solid fa-key">
                        Entrar
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>
</header>
