<x-auth-shell
    title="Seja bem‑vindo"
    subtitle="Insira os seus dados para continuar"
    :back="route('welcome')"
    label="Voltar para a apresentação"
>
    <form method="POST" action="{{ route('login') }}" data-nf-guard novalidate>
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

        <x-ui.input
            label="Senha"
            name="password"
            type="password"
            :required="true"
            autocomplete="current-password"
        />

        <div class="nf-auth-row">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Lembrar de mim</label>
            </div>

            @if (Route::has('password.request'))
                <a class="nf-auth-link" href="{{ route('password.request') }}">Esqueceu a senha?</a>
            @endif
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" wide>
            Entrar
        </x-ui.button>
    </form>

    <p class="nf-auth-back">
        <a href="{{ route('register') }}">Não tem uma conta? Criar conta</a>
    </p>
</x-auth-shell>
