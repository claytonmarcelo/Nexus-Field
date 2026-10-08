{{-- Estado de interface: carregando, vazio, sem resultado, erro, sem acesso. --}}
@props([
    'tone' => 'empty',
    'title',
    'text' => null,
])

@php
    // Sem `<i>` no estado de carregamento: o spinner é CSS puro, porque o
    // FontAwesome só desenha quando a família de fonte terminou de baixar.
    $icons = [
        'empty' => 'fa-regular fa-folder-open',
        'no-results' => 'fa-solid fa-magnifying-glass',
        'error' => 'fa-solid fa-triangle-exclamation',
        'denied' => 'fa-solid fa-lock',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'nf-state']) }} data-tone="{{ $tone }}" role="status">
    <div class="nf-state-icon" aria-hidden="true">
        @if ($icon = $icons[$tone] ?? null)
            <i class="{{ $icon }}"></i>
        @endif
    </div>

    <p class="nf-state-title">{{ $title }}</p>

    @if ($text)
        <p class="nf-state-text">{{ $text }}</p>
    @endif

    @unless ($slot->isEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endunless
</div>
