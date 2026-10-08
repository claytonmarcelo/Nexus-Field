{{--
    Rodapé compartilhado das telas abertas. Três camadas: identidade, capacidades
    reais da plataforma (o que o banco e o RBAC já entregam hoje, nada de
    promessa) e o crédito. Os selos saem de uma lista única para o pé autenticado
    não reinventar fato.
--}}
@php
    $capacidades = [
        'Isolamento por empresa',
        'Permissões por papel',
        'Trilha de auditoria',
        'Fuso ' . config('app.timezone'),
    ];
@endphp

<footer class="nf-public-footer">
    <div class="container">
        <div class="nf-public-footer-top">
            <div class="nf-public-footer-brand">
                <x-ui.brand-mark />
                <div class="nf-public-footer-id">
                    <p class="nf-wordmark">
                        NEXUS<span class="nf-wordmark-accent">-FIELD</span>
                    </p>
                    <p class="nf-public-footer-tagline">
                        Gestão de operações em campo: ordem, agenda, check-in e custo no mesmo registro.
                    </p>
                </div>
            </div>

            <div class="nf-public-footer-side">
                <ul class="nf-public-footer-capacities" role="list">
                    @foreach ($capacidades as $capacidade)
                        <li class="nf-footer-chip">{{ $capacidade }}</li>
                    @endforeach
                </ul>

                <nav class="nf-public-footer-links" aria-label="Navegação do rodapé">
                    <a href="{{ route('welcome') }}">Início</a>

                    @auth
                        {{-- Quem já entrou não recebe link para a tela de entrada:
                             ela jogaria a pessoa de volta para o painel. --}}
                        <a href="{{ route('dashboard') }}">Painel</a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}">Entrar</a>
                        @endif

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}">Recuperar acesso</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </div>

        <div class="nf-public-footer-bottom">
            <x-signature />
            <p class="nf-footer-note mb-0">Acesso autenticado · dados isolados por empresa</p>
        </div>
    </div>
</footer>
