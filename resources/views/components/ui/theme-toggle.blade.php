{{-- Chave global de tema. O comportamento está em resources/js/nexusfield/theme.js. --}}
<button
    type="button"
    class="nf-theme-toggle"
    data-nf-theme-toggle
    aria-label="Alternar entre tema claro e tema escuro"
    title="Alternar tema"
    {{ $attributes }}
>
    <i class="nf-icon-sun fa-solid fa-sun" aria-hidden="true"></i>
    <i class="nf-icon-moon fa-solid fa-moon" aria-hidden="true"></i>
</button>
