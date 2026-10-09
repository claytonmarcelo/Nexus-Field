@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Notificações"
    subtitle="A sua bandeja: cada linha é um aviso que a operação deu a esta conta, no momento em que o fato aconteceu."
    :trilha="['Gestão' => null, 'Notificações' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $avisos->total() }} {{ $avisos->total() === 1 ? 'aviso' : 'avisos' }}
            @if ($avisos->total() > 0)
                · página {{ $avisos->currentPage() }} de {{ max(1, $avisos->lastPage()) }}
            @endif
            @if ($pendentes > 0)
                · <span class="nf-status nf-status-open">{{ $pendentes }} {{ $pendentes === 1 ? 'pendente de leitura' : 'pendentes de leitura' }}</span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <form method="POST" action="{{ route('notifications.mark-all') }}" class="nf-inline-form">
                @csrf
                @method('PATCH')
                <x-ui.button type="submit" variant="soft-primary" size="sm" icon="fa-solid fa-check-double"
                    :disabled="$pendentes === 0">
                    Marcar todos como lidos
                </x-ui.button>
            </form>
        </div>
    </div>

    <x-ui.filters :rota="route('notifications.index')" :limpar="route('notifications.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')" placeholder="Título ou texto do aviso" data-nf-busca />
        </div>

        <x-ui.select label="Tipo de aviso" name="tipo" :opcoes="$tipos" :value="request('tipo')"
            placeholder="Qualquer tipo" data-nf-autosubmit />

        <x-ui.select label="Leitura" name="estado" :opcoes="['pendente' => 'Só pendentes', 'lida' => 'Só lidas']"
            :value="request('estado')" placeholder="Pendentes e lidas" data-nf-autosubmit />

        <x-ui.input label="Avisado a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($avisos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum aviso com estes filtros"
                    text="Ajuste a busca, o tipo, a leitura ou a janela de datas e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('notifications.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Sino silencioso até agora"
                    text="Avisos nascem dos fatos da operação: ordem aberta ou atribuída, chamado novo, peça abaixo do ponto de reposição, vencimento chegando. Quando a primeira acontecer, a primeira linha desta tela aparece aqui." />
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Avisos desta conta, com tipo, texto, origem, momento de chegada e de leitura
                    </caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="type" rotulo="Tipo" />
                            <x-ui.sort-link coluna="title" rotulo="Aviso" />
                            <th scope="col">Origem</th>
                            <x-ui.sort-link coluna="created_at" rotulo="Quando" padrao="created_at" />
                            <x-ui.sort-link coluna="read_at" rotulo="Leitura" />
                            <th scope="col">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($avisos as $aviso)
                            <tr>
                                <td>
                                    <span class="{{ StatusCatalog::badge('notification', $aviso->type) }}">
                                        {{ StatusCatalog::label('notification', $aviso->type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $aviso->title }}</span>
                                    @if ($aviso->body)
                                        <div class="nf-text-muted-2 small">{{ $aviso->body }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($aviso->link)
                                        <a class="small" href="{{ $aviso->link }}">Abrir a origem</a>
                                    @else
                                        <span class="nf-text-muted-2">{{ Formatters::TIME_NULL }}</span>
                                    @endif
                                </td>
                                <td><span class="nf-mono">{{ Formatters::dateTime($aviso->created_at) }}</span></td>
                                <td>
                                    @if ($aviso->read_at === null)
                                        <span class="nf-status nf-status-open">pendente</span>
                                    @else
                                        <span class="nf-status nf-status-draft">lida</span>
                                        <div class="nf-text-muted-2 small nf-mono">{{ Formatters::dateTime($aviso->read_at) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($aviso->read_at === null)
                                        <form method="POST" action="{{ route('notifications.mark', $aviso) }}" class="nf-inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.button type="submit" variant="ghost" size="sm" icon="fa-solid fa-check">
                                                Marcar lida
                                            </x-ui.button>
                                        </form>
                                    @else
                                        <span class="nf-text-muted-2 small">{{ Formatters::TIME_NULL }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$avisos" rotulo="avisos" />
        </x-ui.card>

        <p class="nf-text-muted-2 small mt-3 mb-0">
            Aviso não se apaga: o que a operação contou para esta conta fica na bandeja com o
            carimbo da chegada e o da leitura. O sino da barra mostra as pendentes; esta tela é a
            história inteira, e a única decisão que ela pede é quando ler.
        </p>
    @endif
</x-layouts.app>
