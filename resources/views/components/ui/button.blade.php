{{-- Botão do design system. Renderiza <a> quando tem href, <button> caso contrário. --}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'disabled' => false,
    'wide' => false,
])

@php
    $classes = 'btn btn-' . $variant;
    $classes .= match ($size) {
        'sm' => ' btn-sm',
        'lg' => ' btn-lg',
        default => '',
    };
    $classes .= $wide ? ' w-100' : '';
    $classes .= $disabled ? ' disabled' : '';
@endphp

@if ($href)
    <a
        @if (! $disabled) href="{{ $href }}" @endif
        role="button"
        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
        class="{{ $classes }}"
        {{ $attributes->except('type') }}
    >
        @if ($icon)
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif
        <span class="nf-btn-label">{{ $slot }}</span>
    </a>
@else
    <button
        type="{{ $type }}"
        class="{{ $classes }}"
        @disabled($disabled)
        {{ $attributes }}
    >
        @if ($icon)
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif
        <span class="nf-btn-label">{{ $slot }}</span>
    </button>
@endif
