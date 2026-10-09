@use('App\Models\FinancialRecord')
@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')
@use('Illuminate\Support\Str')

@php
    $aberto = request('estado') === 'excluidos';
@endphp

<x-layouts.app
    title="Financeiro"
    subtitle="Contas a receber e a pagar. O estado de cada uma é a soma dos pagamentos lida do banco, não uma escolha de quem digitou."
    :trilha="['Operação' => null, 'Financeiro' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $totais['registros'] }} {{ $totais['registros'] === 1 ? 'conta' : 'contas' }}
            @if ($totais['registros'] > 0)
                · página {{ $lancamentos->currentPage() }} de {{ max(1, $lancamentos->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('financial.create')
                <x-ui.button variant="primary" size="sm" :href="route('financial.create')" icon="fa-solid fa-plus">
                    Nova conta
                </x-ui.button>
            @endcan

            @can('financial.export')
                <x-ui.button variant="ghost" size="sm" :href="route('financial.export', request()->query())"
                    icon="fa-solid fa-file-csv">Exportar CSV</x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('financial.index')" :limpar="route('financial.index')">
        <div class="nf-filters-largo">
            <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                placeholder="Descrição, observação, cliente ou número da ordem" data-nf-busca />
        </div>

        <x-ui.select label="Lado do caixa" name="tipo" :opcoes="$tipos" :value="request('tipo')"
            placeholder="Receita e despesa" data-nf-autosubmit />

        <x-ui.select label="Estado" name="estado" :opcoes="$estados" :value="request('estado')"
            placeholder="Qualquer estado" data-nf-autosubmit />

        <x-ui.select label="Categoria" name="categoria" :opcoes="$categorias" :value="request('categoria')"
            placeholder="Todas as categorias" data-nf-autosubmit />

        <x-ui.select label="Cliente" name="cliente" :opcoes="$clientes" :value="request('cliente')"
            placeholder="Carteira inteira" data-nf-autosubmit />

        <x-ui.input label="Vencendo a partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($lancamentos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhuma conta com estes filtros"
                    text="Ajuste a busca, o lado do caixa, o estado, a categoria, o cliente ou a janela de vencimento e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('financial.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="Carteira vazia"
                    text="Nenhuma conta a receber ou a pagar nesta empresa. A primeira cobrança nasce de uma ordem concluída: feche o serviço na ficha dele e emita a cobrança.">
                    @can('orders.view')
                        <x-ui.button variant="soft-primary" size="sm" :href="route('orders.index')"
                            icon="fa-solid fa-clipboard-list">Ordens de serviço</x-ui.button>
                    @endcan
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card title="Fechamento destes filtros"
            subtitle="Somado no MySQL sobre as mesmas condições da tabela — não sobre a empresa inteira.">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>Valor das contas</span>
                            <strong class="nf-mono">{{ Formatters::money($totais['bruto']) }}</strong>
                        </li>
                        <li>
                            <span>Já mudou de mão</span>
                            <strong class="nf-mono">{{ Formatters::money($totais['pago']) }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <dl class="nf-total-linha mb-0">
                        <dt>Em aberto</dt>
                        <dd>{{ Formatters::money($totais['em_aberto']) }}</dd>
                    </dl>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <dl class="nf-total-linha mb-0">
                        <dt>
                            Vencido
                            <span class="nf-text-muted-2">
                                ({{ $totais['vencido'] }} {{ $totais['vencido'] === 1 ? 'conta' : 'contas' }})
                            </span>
                        </dt>
                        <dd class="{{ $totais['vencido_valor'] > 0 ? 'nf-status-canceled' : '' }}">
                            {{ Formatters::money($totais['vencido_valor']) }}
                        </dd>
                    </dl>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <p class="nf-text-muted-2 small mb-0">
                        Vencido é conta em aberto — sem pagamento ou com pagamento parcial — cujo vencimento ficou
                        para trás. Quitada e cancelada não entram nesta soma, porque não devem mais nada.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="mt-3">
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">
                        Contas a receber e a pagar, com o valor, o que já foi pago e o saldo de cada uma
                    </caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="due_date" rotulo="Vencimento" padrao="due_date" />
                            <th scope="col">Conta</th>
                            <x-ui.sort-link coluna="amount" rotulo="Valor" />
                            <th scope="col" class="text-end">Pago</th>
                            <th scope="col" class="text-end">Saldo</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lancamentos as $lancamento)
                            @php
                                $pago = $lancamento->valorPago();
                                $atrasada = $lancamento->estaVencida();
                            @endphp
                            <tr>
                                <td class="nf-mono">
                                    {{ Formatters::date($lancamento->due_date) }}
                                    @if ($atrasada)
                                        <div class="nf-status nf-status-canceled small">
                                            {{ $lancamento->diasEmAtraso() }} {{ $lancamento->diasEmAtraso() === 1 ? 'dia' : 'dias' }} em atraso
                                        </div>
                                    @elseif ($lancamento->occurred_at)
                                        <div class="nf-text-muted-2 small">
                                            ocorreu em {{ Formatters::date($lancamento->occurred_at) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($lancamento->trashed())
                                        <span class="fw-semibold">{{ $lancamento->description }}</span>
                                        <div class="nf-status nf-status-canceled small">
                                            excluída em {{ Formatters::date($lancamento->deleted_at) }}
                                        </div>
                                    @else
                                        <a class="fw-semibold" href="{{ route('financial.show', $lancamento) }}">
                                            {{ $lancamento->description }}
                                        </a>
                                    @endif

                                    <div class="nf-text-muted-2 small">
                                        <span class="{{ StatusCatalog::badge('financial_type', $lancamento->type) }}">
                                            {{ StatusCatalog::label('financial_type', $lancamento->type) }}
                                        </span>
                                        · {{ FinancialRecord::rotuloCategoria($lancamento->category) }}
                                    </div>

                                    @if ($lancamento->client)
                                        <div class="nf-text-muted-2 small">{{ $lancamento->client->name }}</div>
                                    @elseif (! $lancamento->eReceita())
                                        <div class="nf-text-muted-2 small">Fornecedor não cadastrado</div>
                                    @endif

                                    @if ($lancamento->serviceOrder)
                                        <div class="nf-text-muted-2 small">
                                            <a class="nf-mono" href="{{ route('orders.show', $lancamento->serviceOrder) }}">
                                                {{ $lancamento->serviceOrder->number }}
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end nf-mono">{{ Formatters::money($lancamento->amount) }}</td>
                                <td class="text-end nf-mono">
                                    {{ $pago > 0 ? Formatters::money($pago) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono {{ $atrasada ? 'nf-status-canceled' : '' }}">
                                    {{ $lancamento->status === FinancialRecord::CANCELED ? Formatters::TIME_NULL : Formatters::money($lancamento->saldo()) }}
                                </td>
                                <td>
                                    <span class="{{ $lancamento->badgeEstado() }}">{{ $lancamento->rotuloEstado() }}</span>
                                </td>
                                <td>
                                    <div class="nf-table-actions">
                                        @if ($lancamento->trashed())
                                            @can('financial.delete')
                                                <x-ui.action-form :acao="route('financial.restore', $lancamento)" metodo="PATCH"
                                                    rotulo="Restaurar" titulo="Restaurar {{ Str::limit($lancamento->description, 40) }}?"
                                                    texto="A conta volta à carteira com os pagamentos que tinha."
                                                    icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                                            @endcan
                                        @else
                                            <x-ui.button variant="ghost" size="sm" :href="route('financial.show', $lancamento)"
                                                icon="fa-solid fa-eye">Abrir</x-ui.button>

                                            @can('financial.update')
                                                <x-ui.button variant="ghost" size="sm" :href="route('financial.edit', $lancamento)"
                                                    icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endcan

                                            @can('financial.delete')
                                                <x-ui.action-form :acao="route('financial.destroy', $lancamento)"
                                                    rotulo="Excluir" titulo="Excluir {{ Str::limit($lancamento->description, 40) }}?"
                                                    texto="Só sai da carteira a conta que nunca teve pagamento. Com dinheiro registrado, o caminho é estornar e cancelar." />
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$lancamentos" rotulo="contas" />
        </x-ui.card>

        <p class="nf-text-muted-2 small mt-3 mb-0">
            O saldo de cada linha vem da soma dos pagamentos daquela conta, calculada no mesmo SQL da tabela — é por
            isso que ele bate com o da ficha. Nenhum campo desta tela escreve "pago": quem quita é o registro do
            dinheiro, feito na ficha da conta.
            @if ($aberto)
                <br>
                Estas linhas estão fora da carteira: restaurar devolve a conta com os pagamentos que ela tinha.
            @endif
        </p>
    @endif
</x-layouts.app>
