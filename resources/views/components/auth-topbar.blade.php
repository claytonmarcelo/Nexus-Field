{{-- Barra enxuta das telas de acesso, no padrão do site de referência: voltar
     à esquerda, chave de tema à direita, sem marca e sem porta de cadastro no
     topo. O destino de voltar é passado por quem abre a tela. --}}
@props(['back', 'label' => 'Voltar'])

<header class="nf-auth-topbar">
    <a class="nf-auth-back-link" href="{{ $back }}" aria-label="{{ $label }}" title="{{ $label }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    </a>

    <x-ui.theme-toggle />
</header>
