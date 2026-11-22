<x-auth-shell
    title="Criar conta"
    subtitle="Inicie a sua jornada conosco"
    :back="route('login')"
    label="Voltar para a entrada"
>
    <form method="POST" action="{{ route('register.store') }}" data-nf-guard novalidate>
        @csrf

        <x-ui.input
            label="Nome completo"
            name="name"
            required
            autocomplete="name"
            placeholder="Seu nome"
        />

        <x-ui.input
            label="E-mail"
            name="email"
            type="email"
            required
            autocomplete="username"
            inputmode="email"
            placeholder="seu@email.com"
        />

        <x-ui.input
            label="Senha"
            name="password"
            type="password"
            required
            autocomplete="new-password"
            hint="Mínimo de 10 caracteres, com letras e números."
        />

        <x-ui.input
            label="Confirmar senha"
            name="password_confirmation"
            type="password"
            required
            autocomplete="new-password"
        />

        <x-ui.button type="submit" variant="primary" size="lg" wide>
            Criar conta
        </x-ui.button>
    </form>

    <p class="nf-cadastro-dica">
        O cadastro da empresa vem depois, em <strong>Cadastros › Empresas</strong>, dentro do painel.
    </p>

    <p class="nf-auth-back">
        <a href="{{ route('login') }}">Já tem uma conta? Entrar</a>
    </p>
</x-auth-shell>
