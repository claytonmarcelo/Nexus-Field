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

            <div class="d-flex align-items-center gap-2">
                <x-ui.theme-toggle />
                @if ($withLogin && Route::has('login'))
                    <x-ui.button href="{{ route('login') }}" variant="primary" size="sm" icon="fa-solid fa-right-to-bracket">
                        Entrar
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>
</header>

