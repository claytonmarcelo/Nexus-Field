@use('App\Support\StatusCatalog')

@php
    // O quadro é desenhado pelo JavaScript a partir do JSON do banco, mas nada aqui
    // depende dele para estar certo: sem script a tela continua dizendo o alcance,
    // os filtros seguem sendo um FORM GET de verdade e o botão de marcar continua
    // levando ao formulário que grava.
    $rotuloAlcance = $restrito
        ? 'A sua escala: só o que é seu sai do banco — nem compromisso, nem a janela marcada de uma ordem.'
        : 'A escala da empresa num quadro só: o que o escritório marcou e a janela que cada ordem já pediu.';
@endphp

<x-layouts.app
    title="Agenda"
    :subtitle="$rotuloAlcance"
    :trilha="['Operação' => null, 'Agenda' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            Compromissos marcados aqui, ordens agendadas ali embaixo
            @if ($restrito)
                · seus
            @else
                · da empresa
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('agenda.create')
                <x-ui.button variant="primary" size="sm" :href="route('agenda.create')" icon="fa-solid fa-plus">
                    Novo compromisso
                </x-ui.button>
            @endcan
        </div>
    </div>

    @unless ($restrito)
        <x-ui.filters :rota="route('agenda.index')" :limpar="route('agenda.index')">
            <x-ui.select label="Técnico" name="tecnico" :opcoes="$tecnicos" :value="$tecnico"
                placeholder="A escala inteira" data-nf-autosubmit
                hint="Filtra o quadro pelo banco, não pelo que o navegador já desenhou." />

            <x-ui.select label="Tipo" name="tipo" :opcoes="$tipos" :value="$tipo"
                placeholder="Todos os tipos" data-nf-autosubmit />

            <div class="nf-filters-largo">
                <p class="nf-text-muted-2 small mb-0">
                    O calendário lê a janela que está na tela. Mude de mês, de semana ou de dia e a
                    consulta ao banco acontece de novo, com o filtro junto.
                </p>
            </div>
        </x-ui.filters>
    @endunless

    <x-ui.card :title="$restrito ? 'Sua agenda' : 'Agenda da operação'"
        :subtitle="$podeMover
            ? 'Arraste um compromisso para remarcar a janela; a ordem agendada não se move daqui — ela é movida na ficha dela, onde o motivo fica registrado.'
            : 'Esta conta lê a agenda. Remarcar uma janela é do escritório, de quem conduz a escala.'">
        <div class="nf-agenda" data-nf-agenda
            data-feed="{{ $rotaFeed }}"
            data-nova="{{ $rotaNovo }}"
            data-criar="{{ $podeCriar ? '1' : '0' }}"
            data-mover="{{ $podeMover ? '1' : '0' }}"
            data-csrf="{{ csrf_token() }}"
            data-filtro="{{ json_encode((object) array_filter(['tecnico' => $tecnico, 'tipo' => $tipo], fn ($v) => $v !== null && $v !== '')) }}"
            role="application"
            aria-label="Calendário da agenda, com os compromissos e as ordens agendadas"
        >
            {{-- O quadro substitui este bloco quando o script monta. Sem ele a frase
                 abaixo é a verdade da tela, e não um calendário vazio fingindo. --}}
            <x-ui.state tone="empty" title="A agenda não se desenha sem JavaScript"
                text="Os números continuam no banco: o painel mostra a agenda de hoje, e cada compromisso tem ficha própria pelo menu de Ordens e Chamados." />
        </div>
    </x-ui.card>

    <div class="row g-3 mt-0">
        <div class="col-12 col-lg-5">
            <x-ui.card title="O que cada cor significa" subtitle="Rótulo e tom vêm do catálogo de estados do banco.">
                <ul class="nf-legenda mb-0">
                    @foreach ($tipos as $slug => $rotulo)
                        <li>
                            <span class="nf-legenda-marca nf-fc-t--{{ StatusCatalog::tone('appointment_type', $slug) }}"></span>
                            {{ $rotulo }}
                        </li>
                    @endforeach

                    <li>
                        <span class="nf-legenda-marca nf-fc-event--ordem"></span>
                        Ordem com janela marcada — somente leitura
                    </li>
                </ul>

                <p class="nf-text-muted-2 small mb-0 mt-2">
                    Um compromisso concluído ou cancelado continua no quadro: a agenda é o diário da
                    escala, e o que passou é o que responde “o que foi feito naquele dia”.
                </p>
            </x-ui.card>
        </div>

        <div class="col-12 col-lg-7">
            <x-ui.card title="Como a agenda é lida" subtitle="Nenhum número aqui é escrito na tela: todos saem da mesma consulta do calendário.">
                <ul class="nf-fact-list mb-0">
                    <li>
                        <span>Janela</span>
                        <strong class="nf-mono">início e fim gravados no banco, no relógio da operação</strong>
                    </li>
                    <li>
                        <span>Dono da janela</span>
                        <strong class="nf-mono">técnico da escala, ou ninguém — e aí ela aparece só no escritório</strong>
                    </li>
                    <li>
                        <span>Ordem agendada</span>
                        <strong class="nf-mono">lida de scheduled_starts_at / scheduled_ends_at da própria ordem</strong>
                    </li>
                    <li>
                        <span>Arraste</span>
                        <strong class="nf-mono">manda a nova janela ao servidor, que confere alcance e estado antes de gravar</strong>
                    </li>
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
