{{-- Cabeçalho da página inicial: marca à esquerda e, na mesma fila à direita,
     a chave de tema seguida da única porta de entrada — a ordem e o alinhamento
     do site de referência. O cadastro não vive aqui: ele fica no rodapé da tela
     de entrada. --}}
<header class="nf-public-header">
    <div class="container">
        <div class="nf-public-header-inner">
            <a class="nf-brand" href="{{ route('welcome') }}" aria-label="NEXUS-FIELD, página inicial">
                <x-ui.brand-mark lado="30" />
                <span class="nf-wordmark">
                    <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
                </span>
            </a>

            <div class="nf-public-header-actions">
                {{-- A chave mora imediatamente antes da ação, como na referência:
                     os dois controles formam o mesmo grupo no canto direito. --}}
                <x-ui.theme-toggle class="nf-public-header-tema" />

                @auth
                    {{-- Sessão aberta não tem motivo para ver a tela de entrada; o
                         caminho honesto é o painel. --}}
                    <x-ui.button href="{{ route('dashboard') }}" variant="primary" size="sm" icon="fa-solid fa-gauge-high">
                        Painel
                    </x-ui.button>
                @elseif (Route::has('login'))
                    <x-ui.button href="{{ route('login') }}" variant="primary" size="sm" icon="fa-solid fa-key">
                        Acessar
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>
</header>
