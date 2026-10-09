@props(['percentual' => 0, 'tom' => 'done'])

@php
    // O anel é o eco do número escrito ao lado dele, não um segundo número: por
    // isso entra escondido da leitura de tela — quem lê por fora já recebeu o
    // percentual no texto. A circunferência de r 15,5 é 97,39, e o arco coberto é
    // a fatia que ele conta.
    $circunferencia = 97.39;
    $fatia = max(0, min(100, (float) $percentual));
    $arco = round($circunferencia * $fatia / 100, 2);
@endphp

<span class="nf-gauge tone-{{ $tom }}" aria-hidden="true">
    <svg viewBox="0 0 36 36" focusable="false">
        <circle class="nf-gauge-track" cx="18" cy="18" r="15.5"></circle>
        <circle class="nf-gauge-fill" cx="18" cy="18" r="15.5"
                stroke-dasharray="{{ number_format($arco, 2, '.', '') }} {{ number_format($circunferencia, 2, '.', '') }}"></circle>
    </svg>
    <span class="nf-gauge-centro">{{ $slot }}</span>
</span>
