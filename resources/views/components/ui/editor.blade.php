{{-- Texto longo com editor de texto rico. Tem o mesmo contrato de label, ajuda e
     erro do x-ui.textarea, porque a peça por baixo continua sendo uma textarea com
     o mesmo nome de campo: sem JavaScript ela aparece como área de texto comum e o
     formulário envia do mesmo jeito. O HTML que sai daqui é sanitizado no servidor
     antes de virar bytes no banco (App\Support\TextoSeguro). --}}
@props([
    'label',
    'name',
    'value' => null,
    'hint' => null,
    'placeholder' => null,
    'required' => false,
    'altura' => 220,
])

@php
    $id = $attributes->get('id', $name);
    $errorClass = $errors->has($name) ? ' is-invalid' : '';
@endphp

<div class="mb-3 nf-editor-campo">
    <label class="form-label" for="{{ $id }}">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden required-indicator">obrigatório</span>
        @endif
    </label>

    <textarea
        {{ $attributes->merge(['class' => 'form-control disable-adminlte-validations'.$errorClass, 'id' => $id, 'name' => $name]) }}
        data-nf-editor
        data-nf-editor-altura="{{ $altura }}"
        @if ($placeholder) data-nf-editor-placeholder="{{ $placeholder }}" @endif
        @if ($required) aria-required="true" @endif
        @if ($errorClass) aria-invalid="true" aria-describedby="{{ $id }}-erro" @elseif ($hint) aria-describedby="{{ $id }}-ajuda" @endif
    >{{ old($name, $value) }}</textarea>

    @if ($hint)
        <div class="form-text" id="{{ $id }}-ajuda">{{ $hint }}</div>
    @endif

    @error($name)
        <div class="invalid-feedback d-block" id="{{ $id }}-erro">{{ $message }}</div>
    @enderror
</div>
