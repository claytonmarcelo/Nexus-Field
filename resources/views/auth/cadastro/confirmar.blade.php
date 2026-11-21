<x-cadastro-shell
    title="Confirme seu e-mail"
    subtitle="Enviamos um link de confirmação. Abra-o para liberar o envio dos documentos."
    :step="2"
>
    @if ($protocolo)
        <p class="nf-cadastro-protocolo">
            Protocolo: <strong>{{ $protocolo }}</strong>
        </p>
    @endif

    <div class="nf-cadastro-aviso">
        <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
        <p>Confira sua caixa de entrada e também o spam. O link expira em 48 horas.</p>
    </div>

    <p class="nf-auth-back">
        <a href="{{ route('cadastro.create') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Revisar dados</a>
    </p>
</x-cadastro-shell>
