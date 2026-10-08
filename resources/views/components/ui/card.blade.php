{{-- Cartão na estrutura oficial do AdminLTE 4. --}}
@props([
    'title' => null,
    'subtitle' => null,
    'variant' => null,
])

<div {{ $attributes->merge(['class' => 'card' . ($variant ? ' card-' . $variant : '')]) }}>
    @if ($title || $subtitle || isset($tools))
        <div class="card-header">
            @if ($title)
                <h3 class="card-title">{{ $title }}</h3>
            @endif
            @if ($subtitle)
                <div class="card-subtitle">{{ $subtitle }}</div>
            @endif
            @isset($tools)
                <div class="card-tools">{{ $tools }}</div>
            @endisset
        </div>
    @endif

    <div class="card-body">{{ $slot }}</div>

    @isset($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endisset
</div>
