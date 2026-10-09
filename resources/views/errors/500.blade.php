{{-- 500: a porta de dentro. Nenhum detalhe técnico vaza nesta tela. --}}
<x-auth-shell
    title="Algo quebrou do lado de dentro"
    subtitle="O erro foi registrado do nosso lado — não do seu."
>
    <p class="nf-auth-subtitle">
        A plataforma capturou a falha, nenhum dado seu foi exposto e nada foi gravado pela
        metade. Tente de novo em instantes; se o erro se repetir, avise o administrador da
        sua empresa com a hora exata do pedido — a trilha de auditoria e o log dizem o resto.
    </p>

    <div class="nf-auth-row nf-auth-acoes">
        @auth
            <x-ui.button variant="primary" :href="route('dashboard')" icon="fa-solid fa-rotate-left">
                Tentar de novo
            </x-ui.button>
        @else
            <x-ui.button variant="primary" :href="route('login')" icon="fa-solid fa-key">
                Entrar na plataforma
            </x-ui.button>
        @endauth

        <x-ui.button variant="ghost" :href="route('welcome')">
            Página de apresentação
        </x-ui.button>
    </div>
</x-auth-shell>
