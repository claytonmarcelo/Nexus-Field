{{--
    Ação destrutiva ou de estado num FORM POST próprio. É formulário e não link
    por dois motivos: a rota exige método que escreve, e o CSRF só viaja em POST.
    O pedido de confirmação vem de resources/js/nexusfield/confirm.js, que desenha
    a caixa com SweetAlert2 — sem JS o envio acontece direto, então a ação continua
    possível (e o servidor continua recusando quem não tem a permissão).
--}}
@props([
    'acao',
    'metodo' => 'DELETE',
    'rotulo',
    'icon' => 'fa-solid fa-trash-can',
    'titulo',
    'texto' => null,
    'perigo' => true,
    'variante' => 'ghost',
])

<form method="POST" action="{{ $acao }}" class="nf-inline-form" data-nf-confirm
    data-confirm-titulo="{{ $titulo }}"
    @if ($texto) data-confirm-texto="{{ $texto }}" @endif
    data-confirm-perigo="{{ $perigo ? 'true' : 'false' }}"
    data-confirm-rotulo="{{ $rotulo }}"
>
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif

    {{ $slot }}

    <x-ui.button type="submit" :variant="$variante" size="sm" :icon="$icon" class="nf-btn-acao-perigo">
        {{ $rotulo }}
    </x-ui.button>
</form>
