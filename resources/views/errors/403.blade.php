{{-- 403: o servidor recusou antes de qualquer botão existir na tela. --}}
@php
    // O motivo escrito pelo abort() vem à frente: foi ele que o servidor escolheu
    // contar. O que o framework inventa fica do lado de fora (MotivoErro).
    $motivo = App\Support\MotivoErro::exibivel($exception ?? null);
@endphp
<x-auth-shell
    title="Acesso negado"
    :subtitle="$motivo !== '' ? $motivo : 'Esta conta não tem o degrau que o pedido exigia.'"
>
    <p class="nf-auth-subtitle">
        A permissão para esta tela não existe nesta conta — e a recusa aconteceu no servidor,
        não no navegador. Se isso parece engano, peça ao administrador da sua empresa para
        revisar os papéis desta conta.
    </p>

    <div class="nf-auth-row nf-auth-acoes">
        @auth
            <x-ui.button variant="primary" :href="route('dashboard')" icon="fa-solid fa-gauge-high">
                Voltar ao painel
            </x-ui.button>
        @else
            <x-ui.button variant="primary" :href="route('login')" icon="fa-solid fa-key">
                Entrar com outra conta
            </x-ui.button>
        @endauth

        <x-ui.button variant="ghost" :href="route('welcome')">
            Página de apresentação
        </x-ui.button>
    </div>
</x-auth-shell>
