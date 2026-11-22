<x-auth-shell
    title="Recuperar senha"
    subtitle="Informe o e‑mail da sua conta"
    :back="route('login')"
    label="Voltar para a entrada"
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
            placeholder="seu@email.com"
        />

        <x-ui.button type="submit" variant="primary" size="lg" wide>
            Enviar
        </x-ui.button>
    </form>

    <p class="nf-auth-back">
        <a href="{{ route('login') }}">Lembrou a senha? Entrar</a>
    </p>
</x-auth-shell>
