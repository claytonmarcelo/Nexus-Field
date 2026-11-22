<x-auth-shell
    title="Criar sua conta"
    subtitle="Nome, e-mail e senha para começar a usar a plataforma."
>
    <form method="POST" action="{{ route('register.store') }}" data-nf-guard novalidate>
        @csrf

        <x-ui.input
            label="Nome completo"
            name="name"
            required
            autocomplete="name"
            placeholder="Como você quer ser chamado"
        />

        <x-ui.input
            label="E-mail"
            name="email"
            type="email"
            required
            autocomplete="username"
            inputmode="email"
            placeholder="seu.email@empresa.com.br"
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

        <x-ui.button type="submit" variant="primary" size="lg" wide icon="fa-solid fa-user-plus">
            Criar conta
        </x-ui.button>
    </form>

    <p class="nf-cadastro-dica">
        Depois de entrar, o cadastro da sua empresa fica em <strong>Cadastros › Empresas</strong>, no painel.
    </p>

    <p class="nf-auth-back">
        <a href="{{ route('login') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Já tenho conta: entrar</a>
    </p>
</x-auth-shell>
