{{-- Select de formulário com label, ajuda e erro do servidor. As opções vêm do
     banco ou do catálogo de estados, nunca escritas dentro da tela. --}}
@props([
    'label',
    'name',
    'opcoes' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);
    $atual = old($name, $value);
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

    <select
        {{ $attributes->merge(['class' => 'form-control disable-adminlte-validations'.$errorClass, 'id' => $id, 'name' => $name]) }}
        @if ($required) required aria-required="true" @endif
        @if ($errorClass) aria-invalid="true" aria-describedby="{{ $id }}-erro" @elseif ($hint) aria-describedby="{{ $id }}-ajuda" @endif
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($opcoes as $valor => $rotulo)
            <option value="{{ $valor }}" @selected((string) $atual === (string) $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>

    @if ($hint)
        <div class="form-text" id="{{ $id }}-ajuda">{{ $hint }}</div>
    @endif

    @error($name)
        <div class="invalid-feedback" id="{{ $id }}-erro">{{ $message }}</div>
    @enderror
</div>
