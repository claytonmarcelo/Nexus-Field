{{-- Campo longo de formulário, com o mesmo contrato de erro e ajuda do x-ui.input. --}}
@props([
    'label',
    'name',
    'value' => null,
    'hint' => null,
    'placeholder' => null,
    'required' => false,
    'rows' => 4,
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
            <span class="visually-hidden required-indicator">obrigatório</span>
        @endif
    </label>

    <textarea
        {{ $attributes->merge(['class' => 'form-control disable-adminlte-validations'.$errorClass, 'id' => $id, 'name' => $name, 'rows' => $rows]) }}
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required aria-required="true" @endif
        @if ($errorClass) aria-invalid="true" aria-describedby="{{ $id }}-erro" @elseif ($hint) aria-describedby="{{ $id }}-ajuda" @endif
    >{{ old($name, $value) }}</textarea>

    @if ($hint)
        <div class="form-text" id="{{ $id }}-ajuda">{{ $hint }}</div>
    @endif

    @error($name)
        <div class="invalid-feedback" id="{{ $id }}-erro">{{ $message }}</div>
    @enderror
</div>
