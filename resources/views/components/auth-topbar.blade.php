{{-- Barra enxuta das telas de acesso, no padrão do site de referência: a marca
     acompanha o voltar à esquerda e a chave de tema fica à direita. Voltar e tema
     continuam intocáveis — a marca é informação, então não vira outro link. --}}
@props(['back', 'label' => 'Voltar'])

<header class="nf-auth-topbar">
    <div class="nf-auth-topbar-marca">
        <a class="nf-auth-back-link" href="{{ $back }}" aria-label="{{ $label }}" title="{{ $label }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>

        <span class="nf-wordmark">
            <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
        </span>
    </div>

    <x-ui.theme-toggle />
</header>
