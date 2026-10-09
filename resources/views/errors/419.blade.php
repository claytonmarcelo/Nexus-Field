{{-- 419: o token de proteção venceu antes do envio. Nada foi gravado. --}}
<x-auth-shell
    title="O formulário expirou"
    subtitle="A tela ficou aberta demais e a proteção de origem venceu."
>
    <p class="nf-auth-subtitle">
        Este envio não chegou a tocar o banco — nada foi gravado nem perdido. Reabra a tela,
        confira se ainda está logado na empresa certa e envie de novo.
    </p>

    <div class="nf-auth-row nf-auth-acoes">
        @auth
            <x-ui.button variant="primary" href="{{ url()->previous(route('dashboard', absolute: false)) }}" icon="fa-solid fa-rotate-left">
                Voltar de onde eu estava
            </x-ui.button>
        @else
            <x-ui.button variant="primary" :href="route('login')" icon="fa-solid fa-key">
                Entrar de novo
            </x-ui.button>
        @endauth

        <x-ui.button variant="ghost" :href="route('welcome')">
            Página de apresentação
        </x-ui.button>
    </div>
</x-auth-shell>
