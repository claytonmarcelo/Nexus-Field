<x-auth-shell
    title="Entrar na plataforma"
    subtitle="Acesso restrito às empresas da plataforma."
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
            placeholder="seu.email@empresa.com.br"
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
                <label class="form-check-label" for="remember">Manter conectado</label>
            </div>

            @if (Route::has('password.request'))
                <a class="nf-auth-link" href="{{ route('password.request') }}">Esqueci minha senha</a>
            @endif
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" wide icon="fa-solid fa-key">
            Entrar
        </x-ui.button>
    </form>

    <p class="nf-auth-back">
        <a href="{{ route('welcome') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar para a apresentação</a>
    </p>
</x-auth-shell>
