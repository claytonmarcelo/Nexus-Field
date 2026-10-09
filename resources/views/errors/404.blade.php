{{-- 404: endereço que não é desta casa, ou registro que saiu dela. --}}
@php
    // Uma 404 nossa (registro que saiu da casa) traz o motivo no abort(); a 404 do
    // roteador traz o nome da rota pedida, e isso a tela não mostra.
    $motivo = App\Support\MotivoErro::exibivel($exception ?? null);
@endphp
<x-auth-shell
    title="Página não encontrada"
    :subtitle="$motivo !== '' ? $motivo : 'O endereço cobrado não existe — ou o registro saiu da casa.'"
>
    <p class="nf-auth-subtitle">
        Nada foi inventado para responder aqui. Se o link veio de um e-mail antigo ou de um
        registro excluído, volte para a lista do módulo e procure de novo: a busca da plataforma
        acha o que está vivo.
    </p>

    <div class="nf-auth-row nf-auth-acoes">
        @auth
            <x-ui.button variant="primary" :href="route('dashboard')" icon="fa-solid fa-gauge-high">
                Voltar ao painel
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
