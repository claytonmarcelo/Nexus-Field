@use('App\Models\Product')
@use('App\Models\StockMovement')
@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $saldo = (float) $produto->central_balance;
    $ponto = (float) $produto->reorder_point;
    $unidade = Product::UNIDADES[$produto->unit] ?? $produto->unit;

    $tomSaldo = match (true) {
        $ponto > 0 && $saldo < $ponto => 'nf-status-canceled',
        default => 'nf-status-done',
    };
@endphp

<x-layouts.app
    :title="$produto->name"
    :subtitle="'SKU '.$produto->sku.' · saldo central de '.Formatters::decimal($saldo).' '.$unidade"
    :trilha="['Catálogo' => route('products.index'), 'Produtos' => route('products.index'), $produto->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('catalogo', $produto->status) }}">
                {{ StatusCatalog::label('catalogo', $produto->status) }}
            </span>
            @if ($ponto > 0 && $saldo < $ponto)
                <span class="nf-status nf-status-canceled ms-2">abaixo do ponto de reposição</span>
            @endif
            @if ($produto->trashed())
                <span class="nf-status nf-status-canceled ms-2">
                    Excluído em {{ Formatters::date($produto->deleted_at) }}
                </span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('products.index')" icon="fa-solid fa-list">
                Voltar ao catálogo
            </x-ui.button>

            @unless ($produto->trashed())
                @can('products.update')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('products.edit', $produto)"
                        icon="fa-solid fa-pen">Editar produto</x-ui.button>
                @endcan

                @can('products.delete')
                    <x-ui.action-form :acao="route('products.destroy', $produto)" rotulo="Excluir"
                        titulo="Excluir {{ $produto->name }}?"
                        texto="Só sai do catálogo quem nunca foi movimentado nem cobrado. Inativar preserva o saldo." />
                @endcan
            @else
                @can('products.delete')
                    <x-ui.action-form :acao="route('products.restore', $produto)" metodo="PATCH" rotulo="Restaurar"
                        titulo="Restaurar {{ $produto->name }}?"
                        texto="O produto volta ao catálogo e o saldo das movimentações volta a ser lido."
                        icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                @endcan
            @endunless
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Ficha" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $produto->name }}</dd>
                    </div>
                    <div>
                        <dt>SKU</dt>
                        <dd class="nf-mono">{{ $produto->sku }}</dd>
                    </div>
                    <div>
                        <dt>Unidade</dt>
                        <dd>{{ $unidade }} <span class="nf-text-muted-2 nf-mono">({{ $produto->unit }})</span></dd>
                    </div>
                    <div>
                        <dt>Custo unitário</dt>
                        <dd class="nf-mono">{{ Formatters::money($produto->cost) }}</dd>
                    </div>
                    <div>
                        <dt>Preço de venda</dt>
                        <dd class="nf-mono">{{ Formatters::money($produto->price) }}</dd>
                    </div>
                    <div>
                        <dt>Margem por unidade</dt>
                        <dd class="nf-mono">{{ Formatters::money((float) $produto->price - (float) $produto->cost) }}</dd>
                    </div>
                    <div>
                        <dt>Cadastrado em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($produto->created_at) }}</dd>
                    </div>
                </dl>

                @if ($produto->description)
                    <div class="nf-form-secao">
                        <h2>Descrição</h2>
                        <p class="mb-0 nf-pre-line">{{ $produto->description }}</p>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Saldo no estoque central"
                subtitle="Nada aqui é digitado: é a soma das movimentações, com o sinal de cada tipo.">
                <div class="mb-3">
                    <p class="nf-kpi-label mb-1">Saldo central</p>
                    <p class="nf-kpi-value mb-0 {{ $tomSaldo }}">
                        {{ Formatters::decimal($saldo) }}
                        <span class="nf-kpi-hint">{{ $unidade }}</span>
                    </p>
                    <p class="nf-kpi-hint mb-0 mt-2">
                        ponto de reposição em <span class="nf-mono">{{ Formatters::decimal($ponto) }}</span>
                    </p>
                </div>

                <ul class="nf-fact-list mb-0 mt-3">
                    @foreach ($historico as $linha)
                        <li>
                            <span>{{ $linha['total'] === 1 ? $linha['singular'] : $linha['plural'] }}</span>
                            <strong class="nf-mono">{{ $linha['total'] }}</strong>
                        </li>
                    @endforeach
                </ul>

                @if (array_sum(array_column($historico, 'total')) === 0)
                    <p class="nf-text-muted-2 small mb-0 mt-2">
                        Sem movimentação registrada: o saldo é zero porque nada entrou nem saiu.
                    </p>
                @endif
            </x-ui.card>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <x-ui.card title="Movimentações mais recentes"
                subtitle="Oito últimas pelo que cada tipo faz com o estoque central.">
                @if ($movimentacoes->isEmpty())
                    <x-ui.state tone="empty" title="Nenhuma movimentação deste produto"
                        text="Compra, carga para o técnico, consumo e devolução chegam na tela de estoque; é por lá que o saldo se forma." />
                @else
                    <div class="table-responsive">
                        <table class="table nf-table align-middle mb-0">
                            <caption class="visually-hidden">Movimentações de estoque registradas para este produto</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Quando</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col" class="text-end">Quantidade</th>
                                    <th scope="col" class="text-end">Efeito no central</th>
                                    <th scope="col">Origem</th>
                                    <th scope="col">Observação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($movimentacoes as $mov)
                                    @php
                                        // O ajuste carrega o sinal na própria quantidade, então o efeito no
                                        // estoque central é o produto do sinal do tipo pela quantidade.
                                        $sinal = StockMovement::CENTRAL_SIGN[$mov->type] ?? 0;
                                        $delta = $sinal * (float) $mov->quantity;

                                        $efeito = match (true) {
                                            $sinal === 0 => Formatters::TIME_NULL,
                                            $delta > 0 => '+'.Formatters::decimal(abs($delta)),
                                            $delta < 0 => '−'.Formatters::decimal(abs($delta)),
                                            default => Formatters::decimal(0),
                                        };
                                    @endphp
                                    <tr>
                                        <td class="nf-mono">{{ Formatters::dateTime($mov->recorded_at) }}</td>
                                        <td>
                                            <span class="{{ StatusCatalog::badge('movement', $mov->type) }}">
                                                {{ StatusCatalog::label('movement', $mov->type) }}
                                            </span>
                                        </td>
                                        <td class="text-end nf-mono">{{ Formatters::decimal($mov->quantity) }}</td>
                                        <td class="text-end nf-mono">{{ $efeito }}</td>
                                        <td>
                                            @if ($mov->technician)
                                                {{ $mov->technician->name }}
                                            @elseif ($mov->user)
                                                {{ $mov->user->name }}
                                            @else
                                                <span class="nf-text-muted-2">Sistema</span>
                                            @endif
                                            @if ($mov->serviceOrder)
                                                <div class="nf-text-muted-2 small nf-mono">{{ $mov->serviceOrder->number }}</div>
                                            @endif
                                        </td>
                                        <td class="nf-text-muted-2 small">{{ $mov->note ?: Formatters::TIME_NULL }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
