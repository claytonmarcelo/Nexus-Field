@use('App\Models\FinancialRecord')
@use('App\Models\Payment')
@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')
@use('Illuminate\Support\Str')

@php
    $cancelada = $lancamento->status === FinancialRecord::CANCELED;
    $quitada = $lancamento->status === FinancialRecord::PAID;
    $podeReceberPagamento = ! $cancelada && $saldo > 0;
@endphp

<x-layouts.app
    :title="$lancamento->description"
    :subtitle="($lancamento->eReceita() ? 'Cobrança contra ' : 'Conta a pagar — ')
        .($lancamento->client?->name ?? ($lancamento->eReceita() ? 'cliente não definido' : 'fornecedor fora do cadastro'))"
    :trilha="['Operação' => route('financial.index'), 'Financeiro' => route('financial.index'), Str::limit($lancamento->description, 32) => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ $lancamento->badgeEstado() }}">{{ $lancamento->rotuloEstado() }}</span>
            <span class="{{ StatusCatalog::badge('financial_type', $lancamento->type) }} ms-2">
                {{ StatusCatalog::label('financial_type', $lancamento->type) }}
            </span>
            @if ($lancamento->estaVencida())
                <span class="nf-status nf-status-canceled ms-2">
                    {{ $lancamento->diasEmAtraso() }} {{ $lancamento->diasEmAtraso() === 1 ? 'dia' : 'dias' }} além do prazo
                </span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('financial.index')" icon="fa-solid fa-list">
                Voltar à carteira
            </x-ui.button>

            @unless ($cancelada)
                @can('financial.update')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('financial.edit', $lancamento)"
                        icon="fa-solid fa-pen">Editar conta</x-ui.button>
                @endcan
            @endunless

            @can('financial.delete')
                <x-ui.action-form :acao="route('financial.destroy', $lancamento)"
                    rotulo="Excluir" titulo="Excluir esta conta?"
                    texto="Só sai do banco a conta que nunca teve pagamento registrado. Com dinheiro lançado, o caminho é estornar e cancelar." />
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Caixa desta conta"
                :subtitle="$cancelada
                    ? 'Conta cancelada: o que estava previsto não entrou no caixa, e o motivo fica nas observações.'
                    : 'Pago e saldo são a soma das linhas de pagamento abaixo, lida do MySQL agora.'">
                <dl class="nf-total-linha">
                    <dt>Valor da conta</dt>
                    <dd>{{ Formatters::money($lancamento->amount) }}</dd>
                </dl>

                <ul class="nf-fact-list mb-0">
                    <li>
                        <span>{{ $lancamento->eReceita() ? 'Recebido' : 'Pago' }}</span>
                        <strong class="nf-mono">{{ Formatters::money($pago) }}</strong>
                    </li>
                    <li>
                        <span>{{ $cancelada ? 'Previsto (cancelado)' : 'Saldo' }}</span>
                        <strong class="nf-mono">{{ $cancelada ? Formatters::TIME_NULL : Formatters::money($saldo) }}</strong>
                    </li>
                    <li>
                        <span>{{ $lancamento->payments->count() === 1 ? 'Pagamento registrado' : 'Pagamentos registrados' }}</span>
                        <strong class="nf-mono">{{ $lancamento->payments->count() }}</strong>
                    </li>
                    <li>
                        <span>Vencimento previsto</span>
                        <strong class="nf-mono">{{ Formatters::date($lancamento->due_date) }}</strong>
                    </li>
                    <li>
                        <span>Data do fato</span>
                        <strong class="nf-mono">{{ Formatters::date($lancamento->occurred_at) }}</strong>
                    </li>
                </ul>

                <p class="nf-text-muted-2 small mb-0 mt-2">
                    A data do fato é a do último pagamento registrado, e por isso uma conta sem pagamento não tem data
                    de ocorrência: o que não aconteceu não entra no caixa do mês.
                </p>
            </x-ui.card>

            @if ($cancelada)
                <x-ui.card title="Conta cancelada" class="mt-3">
                    <x-ui.state tone="no-results" title="Nenhum pagamento entra aqui"
                        text="Pagamento em conta cancelada não aparece em carteira nenhuma. Reabra a conta primeiro, se o dinheiro realmente mudou de mão.">
                        @can('financial.approve')
                            <x-ui.action-form :acao="route('financial.reopen', $lancamento)" metodo="PATCH"
                                rotulo="Reabrir conta" titulo="Reabrir esta conta?"
                                texto="Ela volta para a carteira com o estado derivado dos pagamentos — sem nenhum, fica em aberto."
                                icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                        @endcan
                    </x-ui.state>
                </x-ui.card>
            @endif

            @if ($quitada)
                <x-ui.card title="{{ $lancamento->eReceita() ? 'Recebimento completo' : 'Pagamento completo' }}" class="mt-3">
                    <x-ui.state tone="empty" title="Conta quitada"
                        text="Não há saldo para lançar aqui. Se entrou dinheiro a mais, ele pertence a outra conta — estorne a linha errada em vez de criar um saldo que ninguém explica." />
                </x-ui.card>
            @endif

            @can('financial.create')
                @if ($podeReceberPagamento)
                    <x-ui.card title="{{ $lancamento->eReceita() ? 'Registrar recebimento' : 'Registrar pagamento' }}"
                        subtitle="O dinheiro que mudou de mão. É esta linha que muda o estado da conta." class="mt-3">
                        <form method="POST" action="{{ route('financial.payments.store', $lancamento) }}"
                            data-nf-guard novalidate>
                            @csrf

                            <div class="nf-form-grade">
                                <x-ui.input label="Valor (R$)" name="valor" type="number"
                                    :value="number_format($saldo, 2, '.', '')" step="0.01" min="0.01"
                                    inputmode="decimal" required
                                    hint="No máximo o saldo de {{ Formatters::money($saldo) }}: acima disso o servidor recusa, porque saldo negativo nesta tela é conta explicada errado." />

                                <x-ui.select label="Como o dinheiro mudou de mão" name="metodo" :opcoes="$metodos"
                                    required placeholder="PIX, cartão, dinheiro ou transferência"
                                    hint="É o que se confere no extrato." />

                                <x-ui.input label="Data do dinheiro" name="data" type="date"
                                    :value="now()->toDateString()" max="{{ now()->toDateString() }}" required
                                    hint="O PIX de ontem se registra hoje com a data de ontem: é por ela que a receita cai no mês certo." />

                                <x-ui.input label="Referência" name="referencia" :value="old('referencia')"
                                    inputmode="text" placeholder="Comprovante, autorizada, ID da transferência"
                                    hint="Opcional, e é o que economiza uma ligação para o banco." />
                            </div>

                            <x-ui.textarea label="Observação do lançamento" name="observacao" rows="2"
                                placeholder="Negociado desconto, pago em duas partes..." class="nf-form-largo" />

                            <div class="nf-form-acoes">
                                <x-ui.button type="submit" variant="primary" size="sm"
                                    icon="fa-solid fa-money-bill-transfer" data-loading="false">
                                    {{ $lancamento->eReceita() ? 'Registrar recebimento' : 'Registrar pagamento' }}
                                </x-ui.button>

                                <p class="nf-text-muted-2 mb-0 small">
                                    Não existe "marcar como pago": o estado vem desta linha, calculado com a conta
                                    travada dentro da transação.
                                </p>
                            </div>
                        </form>
                    </x-ui.card>
                @endif
            @endcan

            <x-ui.card title="Linhas de pagamento" subtitle="Cada linha é um fato: valor, método, data e quem registrou." class="mt-3">
                @if ($lancamento->payments->isEmpty())
                    <x-ui.state tone="empty" title="Nenhum pagamento registrado"
                        text="Enquanto não houver linha aqui, a conta está em aberto. O estado dela não é uma opção de formulário." />
                @else
                    <div class="table-responsive">
                        <table class="table nf-table align-middle mb-0">
                            <caption class="visually-hidden">
                                Pagamentos desta conta com valor, método, data e autor
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Quando</th>
                                    <th scope="col">Valor</th>
                                    <th scope="col">Método</th>
                                    <th scope="col">Referência</th>
                                    <th scope="col">Registrado por</th>
                                    <th scope="col" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lancamento->payments->sortByDesc('paid_at') as $pagamento)
                                    <tr>
                                        <td class="nf-mono">{{ Formatters::date($pagamento->paid_at) }}</td>
                                        <td class="nf-mono">{{ Formatters::money($pagamento->amount) }}</td>
                                        <td>
                                            <span class="{{ StatusCatalog::badge('payment_method', $pagamento->method) }}">
                                                {{ Payment::rotuloMetodo($pagamento->method) }}
                                            </span>
                                        </td>
                                        <td class="nf-text-muted-2 small">
                                            {{ $pagamento->reference ?: Formatters::TIME_NULL }}
                                            @if ($pagamento->note)
                                                <div>{{ Str::limit($pagamento->note, 80) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $pagamento->user?->name ?? 'sistema' }}
                                            <div class="nf-text-muted-2 small">
                                                lançado em {{ Formatters::dateTime($pagamento->created_at) }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="nf-table-actions">
                                                @can('financial.approve')
                                                    <x-ui.action-form :acao="route('financial.payments.destroy', [$lancamento, $pagamento])"
                                                        rotulo="Estornar" icon="fa-solid fa-rotate-left"
                                                        :perigo="false" variante="ghost"
                                                        titulo="Estornar este pagamento de {{ Formatters::money($pagamento->amount) }}?"
                                                        texto="A linha sai do caixa e o estado da conta é recalculado com a soma que sobrar. A auditoria guarda quem desfez." />
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="nf-text-muted-2 small mb-0">
                        A soma destas linhas é o "Pago" do cartão acima e o da coluna da listagem: os três leem a mesma
                        expressão no MySQL, por isso não há como divergir.
                    </p>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Dados da conta" subtitle="O que está gravado nesta linha do banco.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Descrição</dt>
                        <dd>{{ $lancamento->description }}</dd>
                    </div>
                    <div>
                        <dt>Lado do caixa</dt>
                        <dd>{{ StatusCatalog::label('financial_type', $lancamento->type) }}</dd>
                    </div>
                    <div>
                        <dt>Categoria</dt>
                        <dd>{{ FinancialRecord::rotuloCategoria($lancamento->category) }}</dd>
                    </div>
                    <div>
                        <dt>Cliente</dt>
                        <dd>
                            @if ($lancamento->client)
                                @can('clients.view')
                                    <a href="{{ route('clients.show', $lancamento->client) }}">{{ $lancamento->client->name }}</a>
                                @else
                                    {{ $lancamento->client->name }}
                                @endcan
                            @else
                                {{ $lancamento->eReceita() ? 'Sem cliente' : 'Despesa não se cobra de cliente' }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Ordem de serviço</dt>
                        <dd>
                            @if ($lancamento->serviceOrder)
                                @can('orders.view')
                                    <a class="nf-mono" href="{{ route('orders.show', $lancamento->serviceOrder) }}">
                                        {{ $lancamento->serviceOrder->number }}
                                    </a>
                                    <span class="nf-text-muted-2">{{ Str::limit($lancamento->serviceOrder->title, 40) }}</span>
                                @else
                                    <span class="nf-mono">{{ $lancamento->serviceOrder->number }}</span>
                                @endcan
                            @else
                                {{ Formatters::TIME_NULL }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Aberta em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($lancamento->created_at) }}</dd>
                    </div>
                    <div>
                        <dt>Última alteração</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($lancamento->updated_at) }}</dd>
                    </div>
                </dl>

                @if ($lancamento->notes)
                    <div class="nf-form-secao">
                        <h2>Observações</h2>
                        <p class="mb-0 nf-pre-line">{{ $lancamento->notes }}</p>
                    </div>
                @endif
            </x-ui.card>

            @unless ($cancelada)
                @can('financial.approve')
                    <x-ui.card title="Cancelar a conta" subtitle="Decisão, não quitação: é o verbo de quem responde pelo caixa." class="mt-3">
                        <form method="POST" action="{{ route('financial.cancel', $lancamento) }}" data-nf-confirm
                            data-confirm-titulo="Cancelar esta conta?"
                            data-confirm-texto="Ela sai da carteira de em aberto e deixa de contar no vencido. Só é permitido enquanto não houver pagamento registrado."
                            data-confirm-perigo="true" data-confirm-rotulo="Cancelar conta" novalidate>
                            @csrf
                            @method('PATCH')

                            <x-ui.textarea label="Motivo do cancelamento" name="motivo" rows="3" required
                                class="nf-form-largo" placeholder="Cliente desistiu, cobrança emitida em duplicidade, serviço não realizado..."
                                hint="Obrigatório: é o texto que a auditoria e quem abrir a ficha depois vão ler." />

                            <div class="nf-form-acoes">
                                <x-ui.button type="submit" variant="ghost" size="sm" icon="fa-solid fa-ban"
                                    data-loading="false">Cancelar conta</x-ui.button>

                                @if ($lancamento->temPagamento())
                                    <p class="nf-text-muted-2 mb-0 small">
                                        Esta conta tem {{ Formatters::money($pago) }} registrado: o cancelamento vai ser
                                        recusado até que os pagamentos sejam estornados.
                                    </p>
                                @endif
                            </div>
                        </form>
                    </x-ui.card>
                @endcan
            @endunless

            <x-ui.card title="Estado derivado" subtitle="Por que a conta mostra o que mostra." class="mt-3">
                <ul class="nf-fact-list mb-0">
                    <li>
                        <span>Em aberto</span>
                        <strong class="nf-mono">sem pagamento</strong>
                    </li>
                    <li>
                        <span>Parcial</span>
                        <strong class="nf-mono">0 &lt; pago &lt; valor</strong>
                    </li>
                    <li>
                        <span>Quitado</span>
                        <strong class="nf-mono">pago ≥ valor</strong>
                    </li>
                    <li>
                        <span>Vencido</span>
                        <strong class="nf-mono">em aberto e prazo atrás</strong>
                    </li>
                    <li>
                        <span>Cancelado</span>
                        <strong class="nf-mono">decisão, zero pagamento</strong>
                    </li>
                </ul>

                <p class="nf-text-muted-2 small mb-0 mt-2">
                    Nenhum formulário desta tela escreve o estado. Ele é recalculado a partir da soma dos pagamentos,
                    com a linha travada dentro da transação — dois registros no mesmo segundo não fecham a conta com
                    metade do valor.
                </p>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
