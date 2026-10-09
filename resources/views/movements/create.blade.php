@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $tipoAtual = old('tipo', $movimento->type);
    $precisaDeTecnico = in_array($tipoAtual, ['load', 'consume', 'return'], true);
    $precisaDeOrdem = $tipoAtual === 'consume';
    $ajuste = $tipoAtual === 'adjustment';

    $rotuloTecnico = $restrito
        ? ($usuario->technician?->name ?? 'sua ficha')
        : null;
@endphp

<x-layouts.app
    title="Registrar movimentação de estoque"
    subtitle="Uma linha no livro-caixa: o que entrou, o que saiu, para onde foi e quem registrou."
    :trilha="[
        'Operação' => null,
        'Estoque' => route('movements.index'),
        'Registrar' => null,
    ]"
>
    <x-ui.card>
        <form method="POST" action="{{ route('movements.store') }}" data-nf-guard novalidate>
            @csrf
            <div class="nf-form-secao">
                <h2>O que aconteceu com a unidade</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Tipo de movimentação" name="tipo" :opcoes="$tipos"
                        :value="$movimento->type" required
                        hint="É o tipo que decide o sinal: ele entra no saldo do banco, não é rótulo escolhido depois." />

                    <x-ui.select label="Produto" name="produto_id" :opcoes="$produtos"
                        :value="old('produto_id')" required
                        placeholder="Escolha o produto"
                        hint="Só o catálogo ativo da sua empresa aparece aqui, com o saldo central à vista." />

                    <x-ui.input label="Quantidade" name="quantidade" type="number" :value="old('quantidade')"
                        step="0.0001" inputmode="decimal" placeholder="0,0000" required
                        :hint="$ajuste
                            ? 'No ajuste, o sinal é da quantidade: negativo baixa o inventário, positivo acha. Zero não move nada.'
                            : 'A unidade é a do produto (un, kg, l…). Aceita quatro casas decimais.'" />

                    <x-ui.input label="Custo unitário (R$)" name="custo_unitario" type="number"
                        :value="old('custo_unitario')" step="0.01" min="0" inputmode="decimal" placeholder="0,00"
                        hint="Opcional. É o que a empresa pagou por esta unidade; alimenta a avaliação do estoque." />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Quem carrega e onde foi gasto</h2>

                <div class="nf-form-grade">
                    @if ($restrito)
                        <div class="nf-form-largo">
                            <p class="mb-0">
                                A carga é a de <span class="fw-semibold">{{ $rotuloTecnico }}</span> — você.
                                O formulário não oferece o campo porque ele não é escolha: a conta responde pela
                                própria ficha.
                            </p>
                            <p class="nf-text-muted-2 small mb-0">
                                @if ($usuario->technician)
                                    Carga atual no nome dele:
                                    <span class="nf-mono">{{ Formatters::decimal($usuario->technician->stocks()->comSaldo()->sum('quantity')) }}</span>
                                    itens.
                                @else
                                    Esta conta não tem ficha de técnico.
                                @endif
                            </p>
                        </div>
                    @else
                        <x-ui.select label="Técnico" name="tecnico_id" :opcoes="$tecnicos"
                            :value="old('tecnico_id')" placeholder="Estoque central (sem técnico)"
                            :required="$precisaDeTecnico"
                            hint="Carga, consumo e devolução acontecem na mala de alguém. Compra e ajuste não têm técnico." />

                        <x-ui.select label="Ordem de serviço" name="ordem_id" :opcoes="$ordens"
                            :value="old('ordem_id')" placeholder="Nenhuma ordem"
                            :required="$precisaDeOrdem"
                            hint="Obrigatório no consumo: é a ordem que explica para onde a unidade foi." />
                    @endif

                    <div class="nf-form-largo">
                        <x-ui.textarea label="Observação" name="observacao" :value="old('observacao')" :rows="3"
                            placeholder="Fornecedor e nota da compra, o que faltava no inventário, o que a carga veio resolver"
                            hint="500 caracteres. É aqui que um fato de ontem ganha explicação, porque a hora gravada é a de agora." />
                    </div>
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>O que cada tipo faz com os dois saldos</h2>

                <div class="table-responsive">
                    <table class="table nf-table align-middle mb-0">
                        <caption class="visually-hidden">Efeito de cada tipo de movimentação sobre o estoque central e sobre a carga do técnico</caption>
                        <thead>
                            <tr>
                                <th scope="col">Tipo</th>
                                <th scope="col">Estoque central</th>
                                <th scope="col">Carga do técnico</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sinais as $tipo => $info)
                                <tr @class(['table-active' => $tipo === $tipoAtual])>
                                    <td>
                                        <span class="{{ StatusCatalog::badge('movement', $tipo) }}">{{ $info['rotulo'] }}</span>
                                    </td>
                                    <td class="nf-mono">
                                        {{ match (true) {
                                            $info['central'] > 0 => 'entra a quantidade',
                                            $info['central'] < 0 => 'sai a quantidade',
                                            default => 'o ajuste carrega o sinal na quantidade',
                                        } }}
                                    </td>
                                    <td class="nf-mono">
                                        {{ match (true) {
                                            $info['tecnico'] === null => 'não mexe',
                                            $info['tecnico'] > 0 => 'entra a quantidade',
                                            default => 'sai a quantidade',
                                        } }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="nf-text-muted-2 small mb-3">
                    O consumo não baixa o estoque central de novo: a unidade já saiu dele quando a carga foi
                    baixada. Contar as duas coisas seria o mesmo parafuso descontado duas vezes.
                </p>
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary" icon="fa-solid fa-floppy-disk">
                    Registrar movimentação
                </x-ui.button>

                <x-ui.button variant="ghost" :href="route('movements.index')" icon="fa-solid fa-ban">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <p class="nf-text-muted-2 small mt-3 mb-0">
        A hora da linha é a do servidor no minuto em que ela entra, e o autor é a conta que está logada
        ({{ $usuario->name }}). Nenhum dos dois vem do formulário. Depois de gravada, a movimentação não se
        edita nem se apaga: corrige-se com outra linha que diga o que foi corrigido.
    </p>
</x-layouts.app>
