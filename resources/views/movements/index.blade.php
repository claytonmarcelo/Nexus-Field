@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')
@use('Illuminate\Support\Str')

@php
    $rotuloAlcance = $restrito
        ? 'A sua carga e o seu consumo: cada linha tem o seu nome, e é só isso que você movimenta por aqui.'
        : 'O livro-caixa do estoque: cada linha é um fato que aconteceu, com autor e hora do servidor.';
@endphp

<x-layouts.app
    title="Movimentações de estoque"
    :subtitle="$rotuloAlcance"
    :trilha="['Operação' => null, 'Estoque' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $movimentacoes->total() }} {{ $movimentacoes->total() === 1 ? 'movimentação registrada' : 'movimentações registradas' }}
            @if ($movimentacoes->total() > 0)
                · página {{ $movimentacoes->currentPage() }} de {{ max(1, $movimentacoes->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @canany(['stock.move', 'stock.adjust'])
                <x-ui.button variant="soft-primary" size="sm" :href="route('movements.create')"
                    icon="fa-solid fa-plus">Registrar movimentação</x-ui.button>
            @endcanany

            @can('stock.export')
                <x-ui.button variant="ghost" size="sm" :href="route('movements.export', request()->query())"
                    icon="fa-solid fa-file-csv">Exportar CSV</x-ui.button>
            @endcan

            <x-ui.button variant="ghost" size="sm" :href="route('products.index')" icon="fa-solid fa-boxes-stacked">
                Produtos
            </x-ui.button>
        </div>
    </div>

    <x-ui.filters :rota="route('movements.index')" :limpar="route('movements.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Nome ou SKU do produto, técnico, número da ordem, observação" data-nf-busca />
        </div>

        <x-ui.select label="Tipo" name="tipo" :opcoes="$tipos" :value="request('tipo')"
            placeholder="Compra, carga, consumo, devolução ou ajuste" data-nf-autosubmit />

        @unless ($restrito)
            <x-ui.select label="Produto" name="produto" :opcoes="$produtos" :value="request('produto')"
                placeholder="Qualquer produto do catálogo" data-nf-autosubmit />

            <x-ui.select label="Técnico" name="tecnico" :opcoes="$tecnicos" :value="request('tecnico')"
                placeholder="Qualquer técnico da escala" data-nf-autosubmit />
        @endunless

        <x-ui.input label="Registrada a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($movimentacoes->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma movimentação com estes filtros"
                    text="Ajuste a busca, o tipo, o produto, o técnico ou a janela de datas e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('movements.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @elseif ($restrito)
                <x-ui.state tone="empty" title="Nenhuma movimentação no seu alcance"
                    text="A primeira linha desta tela é uma carga: o escritório carrega você com produto, e a partir daí o consumo e a devolução saem do seu nome." />
            @else
                <x-ui.state tone="empty" title="Nenhuma movimentação de estoque nesta empresa"
                    text="Produto existe no catálogo, mas o saldo dele começa na primeira compra. Registre a entrada e o estoque central passa a ter de onde veio." />
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Movimentações de estoque com o efeito de cada uma no estoque central e na carga do técnico
                    </caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="recorded_at" rotulo="Quando" padrao="recorded_at" />
                            <x-ui.sort-link coluna="type" rotulo="Tipo" />
                            <th scope="col">Produto</th>
                            <x-ui.sort-link coluna="quantity" rotulo="Quantidade" />
                            <th scope="col">Efeito</th>
                            <th scope="col">Onde o fato aconteceu</th>
                            <th scope="col">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movimentacoes as $movimento)
                            @php
                                $produto = $movimento->product;
                                $central = $movimento->efeitoCentral();
                                $carga = $movimento->efeitoTecnico();
                                $unidade = Formatters::unidade($produto?->unit);
                            @endphp
                            <tr>
                                <td class="nf-mono">{{ Formatters::dateTime($movimento->recorded_at) }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('movement', $movimento->type) }}">
                                        {{ StatusCatalog::label('movement', $movimento->type) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($produto)
                                        {{-- O técnico lê o livro-caixa pela permissão de estoque, mas não
                                             lê o catálogo: sem esta guarda o link levaria a um 403. --}}
                                        @can('products.view')
                                            <a class="fw-semibold" href="{{ route('products.show', $produto) }}">{{ $produto->name }}</a>
                                        @else
                                            <span class="fw-semibold">{{ $produto->name }}</span>
                                        @endcan
                                        <div class="nf-text-muted-2 small nf-mono">{{ $produto->sku }}</div>
                                    @else
                                        <span class="nf-text-muted-2">Produto removido do cadastro</span>
                                    @endif
                                </td>
                                <td class="text-end nf-mono">
                                    {{ Formatters::decimal(abs((float) $movimento->quantity)) }}
                                    <span class="nf-text-muted-2 small">{{ $unidade }}</span>
                                </td>
                                <td class="nf-text-muted-2 small">
                                    <span class="nf-mono">
                                        @if ($central > 0)
                                            <span class="nf-status nf-status-done">+{{ Formatters::decimal($central) }}</span> central
                                        @elseif ($central < 0)
                                            <span class="nf-status nf-status-waiting">−{{ Formatters::decimal(abs($central)) }}</span> central
                                        @else
                                            <span class="nf-status nf-status-draft">sem efeito</span> no central
                                        @endif
                                    </span>

                                    @if ($carga !== null)
                                        <div class="mt-1 nf-mono">
                                            {{ $carga > 0 ? 'entrou' : 'saiu' }}
                                            {{ Formatters::decimal(abs($carga)) }} {{ $unidade }} na carga
                                        </div>
                                    @endif

                                    @if ($movimento->unit_cost !== null)
                                        <div class="mt-1 nf-mono">
                                            {{ Formatters::money($movimento->unit_cost) }} / {{ $unidade }}
                                            · {{ Formatters::money((float) $movimento->unit_cost * abs((float) $movimento->quantity)) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($movimento->technician)
                                        @can('technicians.view')
                                            <a href="{{ route('technicians.show', $movimento->technician) }}">{{ $movimento->technician->name }}</a>
                                        @else
                                            {{ $movimento->technician->name }}
                                        @endcan
                                    @else
                                        <span class="nf-text-muted-2">Estoque central</span>
                                    @endif

                                    @if ($movimento->serviceOrder)
                                        <div class="nf-text-muted-2 small">
                                            <a class="nf-mono" href="{{ route('orders.show', $movimento->serviceOrder) }}">
                                                {{ $movimento->serviceOrder->number }}
                                            </a>
                                            {{ Str::limit($movimento->serviceOrder->title, 40) }}
                                        </div>
                                    @endif

                                    @if ($movimento->note)
                                        <div class="nf-text-muted-2 small">{{ Str::limit($movimento->note, 90) }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $movimento->user?->name ?? 'sistema' }}
                                    <div class="nf-text-muted-2 small">
                                        linha <span class="nf-mono">#{{ $movimento->id }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$movimentacoes" rotulo="movimentações" />
        </x-ui.card>

        <p class="nf-text-muted-2 small mt-3 mb-0">
            Estas linhas não se editam nem se apagam: o que estava errado se corrige com outra movimentação que
            diga o que corrigiu, e o saldo central do produto é a soma de todas elas com o sinal de cada tipo —
            o consumo fica de fora do central porque a unidade já saiu dele quando a carga foi baixada. A hora
            de cada fato é a do servidor, e o autor é a conta que registrou.
        </p>
    @endif
</x-layouts.app>
