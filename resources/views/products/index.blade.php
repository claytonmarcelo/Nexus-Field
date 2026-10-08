@use('App\Models\Product')
@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Produtos"
    subtitle="Catálogo do estoque: SKU, custo, preço e o saldo que as movimentações montam no banco."
    :trilha="['Catálogo' => null, 'Produtos' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $produtos->total() }} {{ $produtos->total() === 1 ? 'produto cadastrado' : 'produtos cadastrados' }}
            @if ($produtos->total() > 0)
                · página {{ $produtos->currentPage() }} de {{ max(1, $produtos->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('stock.view')
                {{-- A tela de movimentações é a fase 16; o botão só aparece quando
                     o aplicativo desenha o destino. --}}
                @if (Route::has('movements.index'))
                    <x-ui.button variant="ghost" size="sm" :href="route('movements.index')"
                        icon="fa-solid fa-arrow-right-arrow-left">Movimentações</x-ui.button>
                @endif
            @endcan

            @can('products.create')
                <x-ui.button variant="primary" size="sm" :href="route('products.create')" icon="fa-solid fa-plus">
                    Novo produto
                </x-ui.button>
            @endcan
        </div>
    </div>

    @can('products.view')
        <x-ui.filters :rota="route('products.index')" :limpar="route('products.index')">
            <div class="nf-filters-largo">
                <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                    placeholder="Nome, SKU ou descrição do produto" data-nf-busca />
            </div>

            <x-ui.select label="Situação" name="situacao" :opcoes="$situacoes" :value="request('situacao')"
                placeholder="Todas as ativas e inativas" data-nf-autosubmit />

            <x-ui.select label="Estoque central" name="estoque" :opcoes="$estoques" :value="request('estoque')"
                placeholder="Saldo indiferente" data-nf-autosubmit />

            <x-ui.select label="Unidade" name="unidade" :opcoes="$unidades" :value="request('unidade')"
                placeholder="Todas as unidades" data-nf-autosubmit />
        </x-ui.filters>
    @endcan

    @if ($produtos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum produto com estes filtros"
                    text="Ajuste a busca, a situação, o estoque ou a unidade e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('products.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Nenhum produto cadastrado nesta empresa"
                    text="Produto é item de estoque: peça, gás refrigerante, material de consumo. Sem ele, a carga do técnico e a movimentação não têm o que contar.">
                    @can('products.create')
                        <x-ui.button variant="primary" size="sm" :href="route('products.create')" icon="fa-solid fa-plus">
                            Cadastrar produto
                        </x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Produtos desta empresa, com o saldo que o banco calcula</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="name" rotulo="Produto" padrao="name" />
                            <x-ui.sort-link coluna="sku" rotulo="SKU" />
                            <th scope="col">Unidade</th>
                            <x-ui.sort-link coluna="cost" rotulo="Custo" />
                            <x-ui.sort-link coluna="price" rotulo="Preço" />
                            <th scope="col" class="text-end">Saldo central</th>
                            <x-ui.sort-link coluna="reorder_point" rotulo="Ponto de reposição" />
                            <x-ui.sort-link coluna="status" rotulo="Situação" />
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($produtos as $produto)
                            @php
                                $saldo = (float) $produto->central_balance;
                                $ponto = (float) $produto->reorder_point;
                                $abaixo = $ponto > 0 && $saldo < $ponto;
                            @endphp
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('products.show', $produto) }}">{{ $produto->name }}</a>
                                    @if ($produto->trashed())
                                        <div class="nf-status nf-status-canceled">
                                            Excluído em {{ Formatters::date($produto->deleted_at) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="nf-mono">{{ $produto->sku }}</td>
                                <td>{{ Product::UNIDADES[$produto->unit] ?? $produto->unit }}</td>
                                <td class="nf-mono">{{ Formatters::money($produto->cost) }}</td>
                                <td class="nf-mono">{{ Formatters::money($produto->price) }}</td>
                                <td class="text-end">
                                    <span class="nf-mono {{ $abaixo ? 'nf-status nf-status-progress' : '' }}">
                                        {{ Formatters::decimal($saldo) }}
                                    </span>
                                    @if ($abaixo)
                                        <div class="nf-status nf-status-progress small">abaixo do ponto</div>
                                    @endif
                                </td>
                                <td class="text-end nf-mono">{{ Formatters::decimal($ponto) }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('catalogo', $produto->status) }}">
                                        {{ StatusCatalog::label('catalogo', $produto->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="nf-table-actions">
                                        @unless ($produto->trashed())
                                            @can('products.update')
                                                <x-ui.button variant="ghost" size="sm"
                                                    :href="route('products.edit', $produto)" icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endcan
                                        @endunless

                                        @if ($produto->trashed())
                                            @can('products.delete')
                                                <x-ui.action-form :acao="route('products.restore', $produto)" metodo="PATCH"
                                                    rotulo="Restaurar" titulo="Restaurar {{ $produto->name }}?"
                                                    texto="O produto volta ao catálogo e o saldo das movimentações volta a ser lido."
                                                    icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                                            @endcan
                                        @else
                                            <x-ui.button variant="ghost" size="sm" :href="route('products.show', $produto)"
                                                icon="fa-solid fa-eye">Abrir</x-ui.button>

                                            @can('products.delete')
                                                <x-ui.action-form :acao="route('products.destroy', $produto)"
                                                    rotulo="Excluir" titulo="Excluir {{ $produto->name }}?"
                                                    texto="Só sai do catálogo quem nunca foi movimentado nem cobrado. Inativar preserva o saldo." />
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$produtos" rotulo="produtos" />
        </x-ui.card>
    @endif
</x-layouts.app>
