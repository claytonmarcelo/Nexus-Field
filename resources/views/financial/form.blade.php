@use('App\Models\FinancialRecord')
@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $tipoAtual = strval(old('tipo', $lancamento->type));
    $receita = $tipoAtual === FinancialRecord::REVENUE;

    $opcoesCategoria = collect($categorias)
        ->mapWithKeys(fn (array $itens, string $tipo) => [StatusCatalog::label('financial_type', $tipo) => $itens])
        ->all();

    $temPagamento = $editando && $lancamento->temPagamento();
@endphp

<x-layouts.app
    :title="$editando ? 'Editar conta' : 'Nova conta'"
    :subtitle="$editando
        ? 'Ajuste os dados de '.$lancamento->description.' — o estado continua sendo a soma dos pagamentos.'
        : 'Uma conta que a empresa tem a receber ou a pagar. Ela nasce em aberto: ninguém marca como pago, o pagamento é que fecha.'"
    :trilha="['Operação' => route('financial.index'), 'Financeiro' => route('financial.index'), ($editando ? 'Editar' : 'Nova conta') => null]"
>
    <x-ui.card>
        <form method="POST"
            action="{{ $editando ? route('financial.update', $lancamento) : route('financial.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>De que lado do caixa esta conta entra</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Lado do caixa" name="tipo" :opcoes="$tipos" :value="$lancamento->type" required
                        hint="Receita é dinheiro que entra e se cobra de um cliente; despesa é dinheiro que sai." />

                    <x-ui.select label="Categoria" name="categoria" :opcoes="$opcoesCategoria"
                        :value="$lancamento->category" required
                        placeholder="Escolha dentro do tipo escolhido"
                        hint="Vocabulário fechado: é por esta coluna que o relatório soma. Categoria do tipo errado o servidor recusa." />

                    <x-ui.input label="Descrição" name="descricao" :value="$lancamento->description"
                        placeholder="Cobrança da OS-2026-0004 — manutenção preventiva" required
                        hint="O que o cliente vai ler na cobrança, ou o que a empresa pagou." />

                    <x-ui.input label="Valor (R$)" name="valor" type="number" :value="$lancamento->amount"
                        step="0.01" min="0.01" inputmode="decimal" placeholder="0,00" required
                        hint="Duas casas decimais, como o extrato." />

                    <x-ui.input label="Vencimento" name="vencimento" type="date"
                        :value="$lancamento->due_date?->format('Y-m-d')" required
                        hint="É desta data que sai o “vencido” da carteira: o prazo previsto, não o fato." />

                    @if ($receita)
                        <x-ui.select label="Cliente" name="cliente_id" :opcoes="$clientes"
                            :value="$lancamento->client_id" required placeholder="Cobrar de quem?"
                            hint="Receita se cobra de alguém: só aparece cliente ativo desta empresa." />
                    @else
                        <div class="nf-form-largo">
                            <p class="mb-0 nf-text-muted-2 small">
                                Despesa não se cobra de cliente, então este formulário não oferece o campo — se um
                                <span class="nf-mono">cliente_id</span> chegar mesmo assim, o servidor descarta.
                                Fornecedor ainda não é cadastro desta versão.
                            </p>
                        </div>
                    @endif

                    <x-ui.select label="Ordem vinculada" name="ordem_id" :opcoes="$ordens"
                        :value="$lancamento->service_order_id" placeholder="Nenhuma ordem vinculada"
                        hint="Quando a conta nasce de um serviço, vincule a OS: é ela que amarra a cobrança ao que foi feito." />
                </div>

                @if ($temPagamento)
                    <p class="nf-text-muted-2 small mb-0">
                        Esta conta já tem {{ Formatters::money($lancamento->valorPago()) }} registrado. Valor e lado do
                        caixa estão travados: mexer neles agora é mover a régua debaixo do dinheiro que já mudou de
                        mão. O caminho honesto é estornar o pagamento e ajustar, ou cancelar e reabrir.
                    </p>
                @endif
            </div>

            <div class="nf-form-secao">
                <h2>Observações</h2>

                <x-ui.textarea label="Recado para quem abre a ficha" name="observacao" rows="4"
                    :value="$lancamento->notes" class="nf-form-largo"
                    placeholder="Condição combinada, número do boleto, quem autorizou..."
                    hint="Aqui entram também o motivo do cancelamento e da reabertura, com data." />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar conta' : 'Abrir conta' }}
                </x-ui.button>

                <x-ui.button variant="ghost"
                    :href="$editando ? route('financial.show', $lancamento) : route('financial.index')">
                    Cancelar
                </x-ui.button>

                @unless ($editando)
                    <p class="nf-text-muted-2 mb-0 small">
                        Não há campo de estado: a conta nasce <span class="fw-semibold">em aberto</span> e passa a
                        parcial ou quitada quando o dinheiro for registrado na ficha dela.
                    </p>
                @endunless
            </div>
        </form>
    </x-ui.card>

    @if ($editando)
        <x-ui.card title="O que a tabela diz hoje" subtitle="Derivado dos pagamentos, lido do banco agora — nada aqui é editável.">
            <ul class="nf-fact-list mb-0">
                <li>
                    <span>Estado</span>
                    <strong><span class="{{ $lancamento->badgeEstado() }}">{{ $lancamento->rotuloEstado() }}</span></strong>
                </li>
                <li>
                    <span>Valor da conta</span>
                    <strong class="nf-valor nf-mono">{{ Formatters::money($lancamento->amount) }}</strong>
                </li>
                <li>
                    <span>Já pago</span>
                    <strong class="nf-valor nf-mono">{{ Formatters::money($lancamento->valorPago()) }}</strong>
                </li>
                <li>
                    <span>Saldo</span>
                    <strong class="nf-valor nf-mono">{{ Formatters::money($lancamento->saldo()) }}</strong>
                </li>
                <li>
                    <span>Data da ocorrência</span>
                    <strong class="nf-valor nf-mono">{{ Formatters::date($lancamento->occurred_at) }}</strong>
                </li>
            </ul>

            <p class="nf-text-muted-2 small mb-0 mt-2">
                A ocorrência é o dia do dinheiro, não o dia em que alguém lembrou de lançar: sem pagamento
                registrado, ela continua em branco porque o fato ainda não aconteceu.
            </p>
        </x-ui.card>
    @endif
</x-layouts.app>
