<x-auth-shell
    title="Definir nova senha"
    subtitle="Escolha uma senha que só você saiba. O link usado aqui vale uma única vez."
>
    <form method="POST" action="{{ route('password.update') }}" data-nf-guard novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui.input
            label="E-mail"
            name="email"
            type="email"
            :value="$email"
            :required="true"
            autocomplete="username"
            inputmode="email"
        />

        <x-ui.input
            label="Nova senha"
            name="password"
            type="password"
            :required="true"
            autocomplete="new-password"
            hint="Mínimo de 10 caracteres, com letras e números."
        />

        <x-ui.input
            label="Confirmar nova senha"
            name="password_confirmation"
            type="password"
            :required="true"
            autocomplete="new-password"
        />

        <x-ui.button type="submit" variant="primary" size="lg" wide icon="fa-solid fa-shield-halved">
            Redefinir senha
        </x-ui.button>
    </form>

    <p class="nf-auth-back">
        <a href="{{ route('login') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar para a entrada</a>
    </p>
</x-auth-shell>
