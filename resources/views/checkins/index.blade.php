@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')
@use('Illuminate\Support\Str')

@php
    $rotuloAlcance = $restrito
        ? 'A sua presença em campo: cada linha é um carimbo que o servidor deu quando você registrou a chegada.'
        : 'Quem esteve onde, quando e por quanto tempo: a medida do campo, lida do banco desta empresa.';

    $raio = Formatters::decimal($raio);
@endphp

<x-layouts.app
    title="Visitas de campo"
    :subtitle="$rotuloAlcance"
    :trilha="['Operação' => null, 'Visitas de campo' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $visitas->total() }} {{ $visitas->total() === 1 ? 'visita registrada' : 'visitas registradas' }}
            @if ($visitas->total() > 0)
                · página {{ $visitas->currentPage() }} de {{ max(1, $visitas->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('orders.export')
                <x-ui.button variant="soft-primary" size="sm" :href="route('checkins.export', request()->query())"
                    icon="fa-solid fa-file-csv">Exportar CSV</x-ui.button>
            @endcan

            <x-ui.button variant="ghost" size="sm" :href="route('orders.index')" icon="fa-solid fa-clipboard-list">
                Ordens de serviço
            </x-ui.button>
        </div>
    </div>

    <x-ui.filters :rota="route('checkins.index')" :limpar="route('checkins.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Número da ordem, título, técnico ou relato" data-nf-busca />
        </div>

        <x-ui.select label="Passagem" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
            placeholder="Abertas e encerradas" data-nf-autosubmit />

        @unless ($restrito)
            <x-ui.select label="Técnico" name="tecnico" :opcoes="$tecnicos" :value="request('tecnico')"
                placeholder="Qualquer técnico da escala" data-nf-autosubmit />

            <x-ui.select label="Cliente" name="cliente" :opcoes="$clientes" :value="request('cliente')"
                placeholder="Qualquer cliente" data-nf-autosubmit />
        @endunless

        <x-ui.select label="Posição" name="sem_posicao" :opcoes="['1' => 'Só sem posição lida']"
            :value="request('sem_posicao')" placeholder="Com ou sem GPS" data-nf-autosubmit />

        <x-ui.input label="Chegada a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($visitas->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma visita com estes filtros"
                    text="Ajuste a busca, a passagem, o técnico, o cliente, a posição ou a janela de chegada e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('checkins.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @elseif ($restrito)
                <x-ui.state tone="empty" title="Nenhuma visita no seu alcance"
                    text="A primeira linha desta tela nasce numa ordem de serviço: abra a ficha e registre a chegada ao endereço. Enquanto o campo não medir, não há o que listar." />
            @else
                <x-ui.state tone="empty" title="Nenhuma visita de campo nesta empresa"
                    text="Chegada e saída são medidas na ficha da ordem de serviço. Uma ordem aberta com técnico responsável é o ponto de partida." />
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Passagens do campo pelas ordens, com a distância medida entre a posição lida e o endereço
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Ordem</th>
                            <th scope="col">Cliente</th>
                            <th scope="col">Técnico</th>
                            <x-ui.sort-link coluna="checkin_at" rotulo="Chegada" padrao="checkin_at" />
                            <x-ui.sort-link coluna="checkout_at" rotulo="Saída" />
                            <x-ui.sort-link coluna="status" rotulo="Passagem" />
                            <th scope="col">Posição medida</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visitas as $visita)
                            @php
                                $ordem = $visita->serviceOrder;
                                $fora = $visita->foraDoRaio();
                            @endphp
                            <tr>
                                <td class="nf-mono">
                                    @if ($ordem)
                                        <a class="fw-semibold" href="{{ route('orders.show', $ordem) }}">{{ $ordem->number }}</a>
                                        <div class="nf-text-muted-2 small">{{ $ordem->title }}</div>
                                    @else
                                        <span class="nf-text-muted-2">Ordem removida</span>
                                    @endif
                                </td>
                                <td>{{ $ordem?->client?->name ?? Formatters::TIME_NULL }}</td>
                                <td>{{ $visita->technician?->name ?? 'Técnico sem ficha' }}</td>
                                <td>
                                    <span class="nf-mono">{{ Formatters::dateTime($visita->checkin_at) }}</span>
                                    @if ($visita->checkin_distance !== null)
                                        <div class="nf-text-muted-2 small">
                                            <span class="nf-mono">{{ Formatters::decimal($visita->checkin_distance) }} m</span>
                                            do endereço
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($visita->checkout_at)
                                        <span class="nf-mono">{{ Formatters::dateTime($visita->checkout_at) }}</span>
                                        <div class="nf-text-muted-2 small">
                                            no local: <span class="nf-mono">{{ Formatters::duration($visita->duracaoMinutos()) }}</span>
                                            @if ($visita->checkout_distance !== null)
                                                · saída a <span class="nf-mono">{{ Formatters::decimal($visita->checkout_distance) }} m</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="nf-text-muted-2 nf-mono">{{ Formatters::TIME_NULL }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('checkin', $visita->status) }}">
                                        {{ StatusCatalog::label('checkin', $visita->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($fora === null)
                                        <span class="nf-status nf-status-draft">sem posição lida</span>
                                    @elseif ($fora)
                                        <span class="nf-status nf-status-waiting">fora do raio de {{ $raio }} m</span>
                                    @else
                                        <span class="nf-status nf-status-done">dentro do raio</span>
                                    @endif

                                    @if ($visita->checkin_latitude !== null && $visita->checkin_longitude !== null)
                                        <div class="nf-text-muted-2 small nf-mono">
                                            {{ Formatters::decimal($visita->checkin_latitude, 6) }},
                                            {{ Formatters::decimal($visita->checkin_longitude, 6) }}
                                        </div>
                                    @endif

                                    @if ($visita->observation)
                                        <div class="nf-text-muted-2 small">{{ Str::limit($visita->observation, 90) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$visitas" rotulo="visitas" />
        </x-ui.card>

        <p class="nf-text-muted-2 small mt-3 mb-0">
            A hora de cada carimbo é a do servidor, e a distância é medida entre a posição enviada e o endereço
            gravado na ordem. O raio aceito por esta empresa é de {{ $raio }} m: fora dele a visita é marcada,
            nunca recusada — GPS falha em prédio e em subsolo, e um registro sem medida vale mais que uma
            coordenada inventada.
        </p>
    @endif
</x-layouts.app>
