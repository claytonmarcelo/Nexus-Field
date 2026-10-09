@use('App\Support\Formatters')

@php($editando = $produto->exists)

<x-layouts.app
    :title="$editando ? 'Editar produto' : 'Novo produto'"
    :subtitle="$editando
        ? 'Ajuste a ficha de '.$produto->name.' — SKU, custos e o ponto que dispara a reposição.'
        : 'Uma linha do catálogo de estoque: o SKU é único da empresa e é por ele que a movimentação encontra o produto.'"
    :trilha="['Catálogo' => route('products.index'), 'Produtos' => route('products.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST" action="{{ $editando ? route('products.update', $produto) : route('products.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome do produto" name="name" :value="$produto->name"
                        placeholder="Gás refrigerante R-410a" required />

                    <x-ui.input label="SKU" name="sku" :value="$produto->sku" inputmode="text"
                        placeholder="GS-R410A-1" required
                        hint="Único dentro desta empresa. É o código que a movimentação e a etiqueta usam." />

                    <x-ui.select label="Unidade de medida" name="unit" :opcoes="$unidades" :value="$produto->unit" required
                        hint="Toda quantidade do produto — estoque, carga, consumo — é contada nesta unidade." />

                    <x-ui.select label="Situação no catálogo" name="status" :opcoes="$situacoes" :value="$produto->status" required />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Custos e reposição</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Custo unitário (R$)" name="cost" type="number" :value="$produto->cost"
                        step="0.01" min="0" inputmode="decimal" placeholder="0,00" required
                        hint="Quanto a empresa paga por uma unidade." />

                    <x-ui.input label="Preço de venda (R$)" name="price" type="number" :value="$produto->price"
                        step="0.01" min="0" inputmode="decimal" placeholder="0,00" required
                        hint="Valor cobrado quando o produto entra como item da ordem de serviço." />

                    <x-ui.input label="Ponto de reposição" name="reorder_point" type="number"
                        :value="$produto->reorder_point" step="0.0001" min="0" inputmode="decimal"
                        placeholder="0" required hint="Abaixo deste saldo o produto aparece no alerta da listagem." />
                </div>

                @if ($editando)
                    <p class="nf-text-muted-2 small mb-0">
                        Margem atual:
                        <span class="nf-mono">
                            {{ Formatters::money((float) $produto->price - (float) $produto->cost) }}
                        </span>
                        por unidade. O saldo não é digitado aqui — ele é a soma das movimentações.
                    </p>
                @endif
            </div>

            <div class="nf-form-secao">
                <h2>Descrição</h2>

                <x-ui.textarea label="Para que serve este produto" name="description" :value="$produto->description"
                    rows="4" class="nf-form-largo"
                    placeholder="Aplicação, fabricante, validade, cuidado de armazenagem..."
                    hint="Aparece na ficha e ajuda o técnico a não confundir SKU parecido." />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar produto' : 'Cadastrar produto' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('products.show', $produto) : route('products.index')">
                    Cancelar
                </x-ui.button>

                @unless ($editando)
                    <p class="nf-text-muted-2 mb-0 small">
                        O produto nasce com saldo zero: o estoque existe a partir da primeira movimentação registrada.
                    </p>
                @endunless
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Situação no catálogo" subtitle="O que cada escolha muda na operação.">
        <ul class="nf-fact-list mb-0">
            @foreach ($situacoes as $slug => $rotulo)
                <li>
                    <span>{{ $rotulo }}</span>
                    <strong class="nf-mono">
                        {{ match ($slug) {
                            'active' => 'pode ser movimentado e cobrado',
                            default => 'some da escolha, saldo intacto',
                        } }}
                    </strong>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</x-layouts.app>
