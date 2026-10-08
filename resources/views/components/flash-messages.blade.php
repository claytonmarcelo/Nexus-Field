{{--
    Recado do servidor para o toast da tela. O texto fica escondido aqui e é
    lido por resources/js/nexusfield/flash.js; o toast escapa o conteúdo, então
    uma mensagem que veio do banco não vira HTML.
--}}
@if (session('status'))
    <div hidden data-nf-flash="success">{{ session('status') }}</div>
@endif
