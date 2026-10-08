{{-- Interruptor de opção booleana. O input escondido com `false` garante que
     desligado também chegue no request: sem ele, a caixa desmarcada sumiria do
     payload e o campo não seria gravado como falso. --}}
@props([
    'label',
    'name',
    'checked' => false,
    'hint' => null,
])

@php($id = $attributes->get('id', $name))

<div class="form-check form-switch mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        {{ $attributes->merge(['type' => 'checkbox', 'class' => 'form-check-input', 'id' => $id, 'value' => '1']) }}
        @checked(old($name, $checked))
    >
    <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>

    @if ($hint)
        <div class="form-text" id="{{ $id }}-ajuda">{{ $hint }}</div>
    @endif
</div>
