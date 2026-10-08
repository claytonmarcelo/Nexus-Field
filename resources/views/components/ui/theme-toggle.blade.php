{{--
    Chave de tema no formato pílula: a trilha é o estado, o knob carrega o ícone
    do tema atual. A posição do knob e a troca sun/moon são CSS puro guiado por
    [data-bs-theme] no <html>, que o script de pré-pintura já decidiu — assim o
    botão nasce correto, sem piscar. O clique continua no contrato de
    resources/js/nexusfield/theme.js ([data-nf-theme-toggle]).
--}}
<button
    type="button"
    class="nf-theme-toggle"
    data-nf-theme-toggle
    aria-label="Alternar entre tema claro e tema escuro"
    aria-pressed="false"
    title="Alternar tema"
    {{ $attributes }}
>
    <span class="nf-theme-toggle-track" aria-hidden="true">
        <span class="nf-theme-toggle-knob">
            <i class="nf-icon-sun fa-solid fa-sun"></i>
            <i class="nf-icon-moon fa-solid fa-moon"></i>
        </span>
    </span>
</button>
