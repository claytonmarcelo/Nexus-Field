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

    // `disable-adminlte-validations` é a saída que o AdminLTE oferece para o
    // campo não criar, por conta própria, um segundo balão de erro.
    $controlClass = 'form-control disable-adminlte-validations'.$errorClass;

    // Senha não volta para a tela: o value fica de fora mesmo em old().
    $secret = $type === 'password';
@endphp

<div class="mb-3">
    <label class="form-label" for="{{ $id }}">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden required-indicator">obrigatório</span>
        @endif
    </label>

    @if ($type === 'file')
        <input
            {{ $attributes->merge(['type' => 'file', 'class' => $controlClass, 'id' => $id, 'name' => $name]) }}
            @if ($required) required @endif
            @if ($errorClass) aria-invalid="true" aria-describedby="{{ $id }}-erro" @elseif ($hint) aria-describedby="{{ $id }}-ajuda" @endif
        >
    @elseif ($secret)
        {{-- O botão entra sobreposto ao campo, então o `is-invalid` mora também no
            invólucro: é o que faz a mensagem abaixo continuar aparecendo sem
            reescrever a regra do Bootstrap. --}}
        <div class="nf-password{{ $errorClass }}">
            <input
                {{ $attributes->merge(['type' => 'password', 'class' => $controlClass, 'id' => $id, 'name' => $name]) }}
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($required) required aria-required="true" @endif
                @if ($inputmode) inputmode="{{ $inputmode }}" @endif
                @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                @if ($errorClass) aria-invalid="true" aria-describedby="{{ $id }}-erro" @elseif ($hint) aria-describedby="{{ $id }}-ajuda" @endif
            >

            {{-- Ver a senha digitada é escolha do usuário, não comportamento padrão
                 do campo: o estado vai para aria-pressed e o ícone troca por CSS. --}}
            <button
                type="button"
                class="nf-password-toggle"
                data-nf-password-toggle
                aria-controls="{{ $id }}"
                aria-pressed="false"
                aria-label="Mostrar senha"
                title="Mostrar senha"
            >
                <i class="nf-password-icon-show fa-solid fa-eye" aria-hidden="true"></i>
                <i class="nf-password-icon-hide fa-solid fa-eye-slash" aria-hidden="true"></i>
            </button>
        </div>
    @else
        <input
            {{ $attributes->merge(['type' => $type, 'class' => $controlClass, 'id' => $id, 'name' => $name]) }}
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
