{{--
    Barra de filtros de listagem: um FORM GET por fora de tudo, porque filtro que
    não vive na query string não pode ser compartilhado por link nem volta com o
    botão do navegador. Sem JavaScript a tela continua funcionando — o botão Aplicar
    envia o formulário do mesmo jeito; o script só adianta o envio quando o
    usuário muda uma caixa de seleção.
--}}
@props([
    'rota',
    'limpar' => null,
    'preservar' => ['ordena', 'direcao', 'por_pagina'],
])

<form method="GET" action="{{ $rota }}" class="nf-filters" role="search" aria-label="Filtros da listagem">
    @foreach ($preservar as $campo)
        @if (filled(request($campo)))
            <input type="hidden" name="{{ $campo }}" value="{{ request($campo) }}">
        @endif
    @endforeach

    <div class="nf-filters-grid">
        {{ $slot }}
    </div>

    <div class="nf-filters-acoes">
        <x-ui.button type="submit" variant="primary" size="sm" icon="fa-solid fa-magnifying-glass">
            Aplicar
        </x-ui.button>

        @if ($limpar)
            <a class="btn btn-ghost btn-sm" href="{{ $limpar }}">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                <span class="nf-btn-label">Limpar</span>
            </a>
        @endif
    </div>
</form>
