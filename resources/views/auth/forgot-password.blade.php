<x-auth-shell
    title="Recuperar acesso"
    subtitle="Informe o e-mail da sua conta. Enviaremos um link de redefinição."
    :with-login="true"
>
    <form method="POST" action="{{ route('password.email') }}" data-nf-guard novalidate>
        @csrf

        <x-ui.input
            label="E-mail"
            name="email"
            type="email"
            :required="true"
            autocomplete="username"
            inputmode="email"
            placeholder="seu.email@empresa.com.br"
        />

        <x-ui.button type="submit" variant="primary" size="lg" wide icon="fa-solid fa-paper-plane">
            Enviar link de redefinição
        </x-ui.button>
    </form>

    <p class="nf-auth-back">
        <a href="{{ route('login') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar para a entrada</a>
    </p>
</x-auth-shell>
