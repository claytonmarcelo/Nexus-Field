{{--
    Recado do servidor para o toast da tela. O texto fica escondido aqui e é
    lido por resources/js/nexusfield/flash.js; o toast escapa o conteúdo, então
    uma mensagem que veio do banco não vira HTML.

    Além do `status` (sucesso), a camada lê os canais de erro, aviso e informação
    e, quando a validação voltou com erros, mostra a primeira deles: quem corrigiu
    um formulário e recebeu a tela de volta com o erro lá embaixo, em texto cinza,
    não recebeu um aviso — recebeu uma adivinhação.
--}}
@php
    $canais = [
        'status' => 'success',
        'sucesso' => 'success',
        'erro' => 'error',
        'aviso' => 'warning',
        'info' => 'info',
    ];
@endphp

@foreach ($canais as $chave => $tom)
    @if (filled(session($chave)))
        <div hidden data-nf-flash="{{ $tom }}">{{ session($chave) }}</div>
    @endif
@endforeach

@if (isset($errors) && $errors->any() && ! filled(session('erro')))
    <div hidden data-nf-flash="error">{{ $errors->first() }}</div>
@endif
