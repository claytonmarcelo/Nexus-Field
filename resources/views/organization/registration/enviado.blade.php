<x-organization.cadastro-shell
    title="Cadastro enviado"
    subtitle="Seu pedido está em análise. Avisaremos por e-mail quando houver uma decisão."
    :step="4"
>
    <p class="nf-cadastro-protocolo">
        Protocolo: <strong>{{ $registro->protocol }}</strong>
    </p>

    <div class="nf-cadastro-aviso">
        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
        <p>Guarde o protocolo para acompanhar o andamento. Nenhuma senha é enviada por e-mail; após a aprovação você receberá o link para definir a sua.</p>
    </div>

    <p class="nf-auth-back">
        <a href="{{ route('login') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Ir para a entrada</a>
    </p>
</x-organization.cadastro-shell>
