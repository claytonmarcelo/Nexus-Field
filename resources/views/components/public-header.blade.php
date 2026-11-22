{{-- Cabeçalho da página inicial: marca à esquerda, tema no centro e a única
     porta de entrada à direita. O cadastro não vive aqui — ele é oferecido no
     rodapé da tela de entrada e no fecho da apresentação, como no site de
     referência. --}}
<header class="nf-public-header">
    <div class="container">
        <div class="nf-public-header-inner">
            <a class="nf-brand" href="{{ route('welcome') }}" aria-label="NEXUS-FIELD, página inicial">
                <x-ui.brand-mark lado="30" />
                <span class="nf-wordmark">
                    <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
                </span>
            </a>

            {{-- A chave volta ao centro da barra. Fora dela, o pedido anterior de
                 "topo direito" só disputava espaço com a ação de entrada. --}}
            <div class="nf-public-header-tema">
                <x-ui.theme-toggle />
            </div>

            <div class="nf-public-header-actions">
                @auth
                    {{-- Sessão aberta não tem motivo para ver a tela de entrada; o
                         caminho honesto é o painel. --}}
                    <x-ui.button href="{{ route('dashboard') }}" variant="primary" size="sm" icon="fa-solid fa-gauge-high">
                        Painel
                    </x-ui.button>
                @elseif (Route::has('login'))
                    <x-ui.button href="{{ route('login') }}" variant="primary" size="sm" icon="fa-solid fa-key">
                        Entrar
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>
</header>
