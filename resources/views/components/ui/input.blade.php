{{-- Campo de formulário com label, ajuda e erro de validação do servidor. --}}
@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'placeholder' => null,
    'required' => false,
    'inputmode' => null,
    'autocomplete' => null,
])

@php
    $id = $attributes->get('id', $name);
    $errorClass = $errors->has($name) ? ' is-invalid' : '';
@endphp

<div class="mb-3">
    <label class="form-label" for="{{ $id }}">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden">obrigatório</span>
        @endif
    </label>

    @if ($type === 'file')
        <input
            {{ $attributes->merge(['type' => 'file', 'class' => 'form-control'.$errorClass, 'id' => $id, 'name' => $name]) }}
            @if ($required) required @endif
            @if ($errorClass) aria-invalid="true" aria-describedby="{{ $id }}-erro" @elseif ($hint) aria-describedby="{{ $id }}-ajuda" @endif
        >
    @else
        <input
            {{ $attributes->merge(['type' => $type, 'class' => 'form-control'.$errorClass, 'id' => $id, 'name' => $name]) }}
            value="{{ old($name, $value) }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($inputmode) inputmode="{{ $inputmode }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($errorClass) aria-invalid="true" aria-describedby="{{ $id }}-erro" @elseif ($hint) aria-describedby="{{ $id }}-ajuda" @endif
        >
    @endif

    @if ($hint)
        <div class="form-text" id="{{ $id }}-ajuda">{{ $hint }}</div>
    @endif

    @error($name)
        <div class="invalid-feedback" id="{{ $id }}-erro">{{ $message }}</div>
    @enderror
</div>
