{{--
    Paginação própria do projeto, montada sobre o LengthAwarePaginator que o
    controller trouxe do banco: os números de "mostrando X a Y de Z", a janela de
    páginas e o total por página vêm da consulta, não de conta feita no Blade.

    O seletor de por_pagina é um FORM GET paralelo ao dos filtros e carrega
    consigo todos os filtros atuais como campos ocultos — trocar o tamanho da
    página sem perder a busca é o mínimo que se espera de uma listagem filtrável.
    Ao trocar o tamanho, a página volta para 1 explicitamente: sem isso o usuário
    pararia numa página 4 que, com 50 por página, talvez nem exista.
--}}
@props([
    'paginador',
    'rotulo' => 'registros',
])

@php
    $pag = $paginador->withQueryString();
    $atual = $pag->currentPage();
    $ultima = max(1, $pag->lastPage());
    $total = $pag->total();
    $porPagina = $pag->perPage();

    $primeiro = $total === 0 ? 0 : ($atual - 1) * $porPagina + 1;
    $ultimo = min($total, $atual * $porPagina);

    // Janela curta ao redor da página atual, sempre com o começo e o fim à vista.
    $janela = $ultima > 1
        ? collect(range(max(1, $atual - 2), min($ultima, $atual + 2)))
            ->merge([1, $ultima])->filter(fn ($p) => $p >= 1 && $p <= $ultima)->unique()->sort()->values()
        : null;
    $filtros = collect(request()->query())->except(['page', 'por_pagina'])->all();
@endphp

<div class="nf-pager">
    <p class="nf-pager-resumo">
        @if ($total === 0)
            Nenhum {{ $rotulo }} para estes filtros.
        @else
            Mostrando <strong class="nf-mono">{{ $primeiro }}</strong>–<strong class="nf-mono">{{ $ultimo }}</strong>
            de <strong class="nf-mono">{{ $total }}</strong> {{ $rotulo }}
        @endif
    </p>

    <div class="nf-pager-lado">
        <form method="GET" action="{{ url()->current() }}" class="nf-pager-por">
            @foreach ($filtros as $campo => $valor)
                @if (is_array($valor))
                    @foreach ($valor as $item)
                        <input type="hidden" name="{{ $campo }}[]" value="{{ $item }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
                @endif
            @endforeach

            <label class="form-label visually-hidden" for="por_pagina">Registros por página</label>
            <select class="form-select form-select-sm" id="por_pagina" name="por_pagina" data-nf-autosubmit>
                @foreach (\App\Support\ListFilters::POR_PAGINA as $opcao)
                    <option value="{{ $opcao }}" @selected((int) $porPagina === $opcao)>{{ $opcao }} por página</option>
                @endforeach
            </select>
        </form>

        @if ($ultima > 1)
            <nav class="nf-pager-nav" aria-label="Paginação de {{ $rotulo }}">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $atual <= 1 ? 'disabled' : '' }}">
                        @if ($atual > 1)
                            <a class="page-link" href="{{ $pag->url($atual - 1) }}" aria-label="Página anterior">
                                <i class="fa-solid fa-angle-left" aria-hidden="true"></i>
                            </a>
                        @else
                            <span class="page-link" aria-disabled="true">
                                <i class="fa-solid fa-angle-left" aria-hidden="true"></i>
                            </span>
                        @endif
                    </li>

                    @php($anterior = 0)
                    @foreach ($janela as $pagina)
                        @if ($anterior !== 0 && $pagina > $anterior + 1)
                            <li class="page-item disabled" aria-hidden="true"><span class="page-link">…</span></li>
                        @endif

                        <li class="page-item {{ $pagina === $atual ? 'active' : '' }}">
                            @if ($pagina === $atual)
                                <span class="page-link" aria-current="page">{{ $pagina }}</span>
                            @else
                                <a class="page-link" href="{{ $pag->url($pagina) }}">{{ $pagina }}</a>
                            @endif
                        </li>

                        @php($anterior = $pagina)
                    @endforeach

                    <li class="page-item {{ $atual >= $ultima ? 'disabled' : '' }}">
                        @if ($atual < $ultima)
                            <a class="page-link" href="{{ $pag->url($atual + 1) }}" aria-label="Próxima página">
                                <i class="fa-solid fa-angle-right" aria-hidden="true"></i>
                            </a>
                        @else
                            <span class="page-link" aria-disabled="true">
                                <i class="fa-solid fa-angle-right" aria-hidden="true"></i>
                            </span>
                        @endif
                    </li>
                </ul>
            </nav>
        @endif
    </div>
</div>
