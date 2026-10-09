@use('App\Models\FinancialRecord')
@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $totais = $ordem->totais();
    $encerrada = $ordem->estaEncerrada();

    // Cancelada não conta como cobrança: a carteira dela foi desfeita, e emitir
    // outra contra a mesma OS é decisão de quem responde pelo caixa, não atalho.
    $cobrancaAtiva = $cobrancas->first(fn (FinancialRecord $conta) => $conta->status !== FinancialRecord::CANCELED);
    $emitirCobranca = $ordem->status === 'completed' && $cobrancaAtiva === null;
    $podeEditarLinha = ! $encerrada;
    $atrasada = in_array($ordem->status, ['open', 'in_progress'], true)
        && $ordem->scheduled_ends_at !== null
        && $ordem->scheduled_ends_at->isPast();
    $local = collect([
        $ordem->street ? $ordem->street.($ordem->number_address ? ', '.$ordem->number_address : '') : null,
        $ordem->complement,
        $ordem->neighborhood,
        $ordem->city ? $ordem->city.($ordem->state ? '/'.$ordem->state : '') : null,
        $ordem->zip_code,
    ])->filter()->all();
@endphp

<x-layouts.app
    :title="$ordem->number.' · '.$ordem->title"
    :subtitle="$ordem->client->name.' — a ficha que o técnico abre no campo e o escritório cobra depois.'"
    :trilha="['Operação' => route('orders.index'), 'Ordens de serviço' => route('orders.index'), $ordem->number => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="nf-mono">{{ $ordem->number }}</span>
            <span class="{{ StatusCatalog::badge('order', $ordem->status) }}">
                {{ StatusCatalog::label('order', $ordem->status) }}
            </span>
            <span class="{{ StatusCatalog::badge('priority', $ordem->priority) }}">
                {{ StatusCatalog::label('priority', $ordem->priority) }}
            </span>
            @if ($atrasada)
                <span class="nf-status nf-status-canceled">prazo vencido</span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('orders.index')" icon="fa-solid fa-list">
                Voltar às ordens
            </x-ui.button>

            @can('orders.update')
                @unless ($encerrada)
                    <x-ui.button variant="soft-primary" size="sm" :href="route('orders.edit', $ordem)"
                        icon="fa-solid fa-pen">Editar ordem</x-ui.button>
                @endunless
            @endcan

            @if ($ordem->status === 'draft')
                @can('orders.delete')
                    <x-ui.action-form :acao="route('orders.destroy', $ordem)" rotulo="Apagar rascunho"
                        :titulo="'Apagar '.$ordem->number.'?'"
                        texto="Só o rascunho que nunca virou trabalho pode ser apagado. Ordem aberta se cancela com o motivo registrado." />
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="A ordem" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Cliente</dt>
                        <dd>
                            @can('clients.view')
                                <a href="{{ route('clients.show', $ordem->client) }}">{{ $ordem->client->name }}</a>
                            @else
                                {{ $ordem->client->name }}
                            @endcan
                        </dd>
                    </div>
                    <div>
                        <dt>Serviço</dt>
                        <dd>
                            @if ($ordem->service)
                                {{ $ordem->service->name }}
                                <span class="nf-text-muted-2 small">
                                    · duração {{ Formatters::duration($ordem->service->estimated_minutes) }}
                                </span>
                            @else
                                {{ Formatters::TIME_NULL }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Responsável</dt>
                        <dd>
                            @if ($ordem->technician)
                                @can('technicians.view')
                                    <a href="{{ route('technicians.show', $ordem->technician) }}">{{ $ordem->technician->name }}</a>
                                @else
                                    {{ $ordem->technician->name }}
                                @endcan
                            @else
                                <span class="nf-status nf-status-waiting">sem técnico definido</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Equipe</dt>
                        <dd>{{ $ordem->team?->name ?? Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Agendado</dt>
                        <dd>
                            @if ($ordem->scheduled_starts_at)
                                <span class="nf-mono">{{ Formatters::dateTime($ordem->scheduled_starts_at) }}</span>
                                @if ($ordem->scheduled_ends_at)
                                    <span class="nf-text-muted-2">até {{ Formatters::time($ordem->scheduled_ends_at) }}</span>
                                @endif
                            @else
                                <span class="nf-status nf-status-waiting">sem agendamento</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Início real</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($ordem->started_at) }}</dd>
                    </div>
                    <div>
                        <dt>Conclusão</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($ordem->completed_at) }}</dd>
                    </div>
                    @if ($ordem->cancelled_at)
                        <div>
                            <dt>Cancelada em</dt>
                            <dd class="nf-mono">{{ Formatters::dateTime($ordem->cancelled_at) }}</dd>
                        </div>
                        <div>
                            <dt>Motivo</dt>
                            <dd class="nf-pre-line">{{ $ordem->cancellation_reason ?: Formatters::TIME_NULL }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Aberta em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($ordem->created_at) }}</dd>
                    </div>
                </dl>

                @if ($ordem->description)
                    <div class="nf-form-secao">
                        <h2>O que foi pedido</h2>
                        <p class="mb-0 nf-pre-line">{{ $ordem->description }}</p>
                    </div>
                @endif

                @if ($ordem->execution_notes)
                    <div class="nf-form-secao">
                        <h2>Notas de execução</h2>
                        <p class="mb-0 nf-pre-line">{{ $ordem->execution_notes }}</p>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Local do serviço" subtitle="Snapshot do dia: o endereço viaja junto com a ordem." class="mt-3">
                @if ($local === [])
                    <x-ui.state tone="empty" title="Sem endereço nesta ordem"
                        text="Sem local, o técnico depende do cliente para saber onde é e o check-in não tem coordenada de referência." />
                @else
                    <p class="mb-0">
                        {{ implode(' · ', $local) }}
                    </p>
                    <p class="nf-text-muted-2 small mb-0 mt-1">
                        @if ($ordem->latitude && $ordem->longitude)
                            <span class="nf-mono">
                                {{ Formatters::decimal($ordem->latitude, 6) }},
                                {{ Formatters::decimal($ordem->longitude, 6) }}
                            </span>
                        @else
                            <span class="nf-status nf-status-waiting">sem coordenadas</span>
                        @endif
                    </p>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Conta" subtitle="Somados no banco, linha por linha, agora.">
                @if ($ordem->items->isEmpty())
                    <x-ui.state tone="empty" title="Nenhuma linha cobrada"
                        text="A ordem existe, mas ainda não cobra nada. Adicione o serviço feito ou o produto consumido." />
                @else
                    <ul class="nf-fact-list mb-0">
                        <li>
                            <span>{{ $ordem->items->count() === 1 ? '1 linha cobrada' : $ordem->items->count().' linhas cobradas' }}</span>
                            <strong class="nf-mono">{{ Formatters::money($totais['bruto']) }}</strong>
                        </li>
                        <li>
                            <span>Desconto das linhas</span>
                            <strong class="nf-mono">{{ Formatters::money($totais['bruto'] - $totais['liquido']) }}</strong>
                        </li>
                        <li>
                            <span>Desconto da ordem</span>
                            <strong class="nf-mono">{{ Formatters::money($ordem->discount) }}</strong>
                        </li>
                    </ul>

                    <dl class="nf-total-linha mb-0">
                        <dt>Total a cobrar</dt>
                        <dd class="{{ $totais['total'] < 0 ? 'nf-status-canceled' : '' }}">
                            {{ Formatters::money($totais['total']) }}
                        </dd>
                    </dl>
                @endif

                @if ($cobrancas->isNotEmpty())
                    <div class="nf-form-secao">
                        <h2>Cobrança desta ordem na carteira</h2>
                        <ul class="nf-itens mb-0">
                            @foreach ($cobrancas as $cobranca)
                                <li class="nf-item-linha nf-item-linha-lado">
                                    <div>
                                        <p class="mb-0">
                                            @can('financial.view')
                                                <a class="fw-semibold" href="{{ route('financial.show', $cobranca) }}">
                                                    {{ $cobranca->description }}
                                                </a>
                                            @else
                                                <span class="fw-semibold">{{ $cobranca->description }}</span>
                                            @endcan
                                        </p>
                                        <p class="mb-0 nf-text-muted-2 small nf-mono">
                                            vence {{ Formatters::date($cobranca->due_date) }}
                                            · {{ Formatters::money($cobranca->amount) }}
                                            · pago {{ Formatters::money($cobranca->valorPago()) }}
                                        </p>
                                    </div>
                                    <span class="{{ $cobranca->badgeEstado() }}">{{ $cobranca->rotuloEstado() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($emitirCobranca)
                    @can('financial.create')
                    <div class="nf-form-secao">
                        <h2>Emitir a cobrança</h2>

                        @if ($totais['total'] <= 0)
                            <p class="nf-text-muted-2 small mb-0">
                                A ordem terminou, mas a conta fecha em {{ Formatters::money($totais['total']) }}: sem
                                linha cobrada não há o que emitir. Registre o serviço ou o produto consumido acima.
                            </p>
                        @else
                            <form method="POST" action="{{ route('orders.charge', $ordem) }}" data-nf-guard novalidate>
                                @csrf

                                <div class="nf-form-grade">
                                    <x-ui.input label="Vencimento da cobrança" name="vencimento" type="date"
                                        :value="now()->addDays(15)->toDateString()" required
                                        hint="É dele que sai o “vencido” da carteira financeira." />

                                    <x-ui.select label="Categoria da receita" name="categoria"
                                        :opcoes="$categoriasCobranca" required
                                        placeholder="Como este serviço entra no caixa"
                                        hint="Vocabulário fechado: é por esta coluna que o relatório soma as receitas." />
                                </div>

                                <x-ui.textarea label="Observação da cobrança" name="observacao" rows="2"
                                    class="nf-form-largo"
                                    placeholder="Boleto em duas parcelas, combinado por telefone, nota fiscal..." />

                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="primary" size="sm"
                                        icon="fa-solid fa-file-invoice-dollar" data-loading="false">
                                        Emitir cobrança de {{ Formatters::money($totais['total']) }}
                                    </x-ui.button>

                                    <p class="nf-text-muted-2 mb-0 small">
                                        O valor não é digitado aqui: o servidor soma as linhas desta ordem agora e grava
                                        o resultado. Cobrança duplicada nesta OS é recusada.
                                    </p>
                                </div>
                            </form>
                        @endif
                    </div>
                    @endcan
                @elseif ($ordem->items->isNotEmpty() && ! $encerrada)
                    @can('financial.view')
                        <p class="nf-text-muted-2 small mb-0 mt-3">
                            A cobrança nasce quando a ordem é <span class="fw-semibold">concluída</span>: enquanto o
                            serviço está em aberto, o que se vê aqui é a conta prevista, não a emitida.
                        </p>
                    @endcan
                @endif
            </x-ui.card>

            <x-ui.card title="Estado" subtitle="O fluxo decide o caminho; cada passagem fica registrada." class="mt-3">
                @if ($proximosEstados === [])
                    <x-ui.state tone="no-results" title="Nenhum estado à disposição"
                        text="{{ $encerrada
                            ? 'Esta ordem já foi concluída ou cancelada: o que passou no campo é histórico e não se reescreve.'
                            : 'Ir para aberta ou cancelada pede a permissão de aprovação, que esta conta não tem.' }}" />
                @else
                    <form method="POST" action="{{ route('orders.status', $ordem) }}" data-nf-guard novalidate>
                        @csrf
                        @method('PUT')

                        <x-ui.select label="Próximo estado" name="estado" :opcoes="$proximosEstados" required
                            :hint="$ordem->status === 'draft'
                                ? 'Abrir a ordem coloca ela na fila do técnico e sai do rascunho.'
                                : 'Cancelar exige o motivo: é o que a auditoria e o cliente vão ler depois.'" />

                        <x-ui.textarea label="Observação da passagem" name="nota" rows="3" class="nf-form-largo"
                            placeholder="O que mudou, quem autorizou, o que o cliente disse..."
                            hint="Obrigatória para cancelar; acompanhada do nome de quem aplicou o estado." />

                        <div class="nf-form-acoes">
                            <x-ui.button type="submit" variant="primary" size="sm" icon="fa-solid fa-arrow-right-arrow-left"
                                data-loading="false">Aplicar estado</x-ui.button>
                        </div>
                    </form>
                @endif

                @if ($ordem->statusHistory->isNotEmpty())
                    <div class="nf-form-secao">
                        <h2>Passagens registradas</h2>
                        <ol class="nf-timeline mb-0">
                            @foreach ($ordem->statusHistory as $passagem)
                                <li>
                                    <span class="nf-timeline-time">{{ Formatters::date($passagem->created_at) }}</span>
                                    <span>
                                        <span class="nf-timeline-text">
                                            {{ $passagem->from_status
                                                ? StatusCatalog::label('order', $passagem->from_status).' → '
                                                : 'Aberta como ' }}
                                            <span class="{{ StatusCatalog::badge('order', $passagem->to_status) }}">
                                                {{ StatusCatalog::label('order', $passagem->to_status) }}
                                            </span>
                                            <br>
                                            por {{ $passagem->user?->name ?? 'sistema' }}
                                            @if ($passagem->note)
                                                · {{ $passagem->note }}
                                            @endif
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>

    <x-ui.card title="Linhas cobradas" :subtitle="$encerrada ? 'Ordem encerrada: a conta está fechada e nenhuma linha se mexe mais.' : 'Serviço do catálogo ou produto consumido. Preço e descrição congelam na linha.'" class="mt-3">
        @if ($ordem->items->isEmpty())
            <x-ui.state tone="empty" title="A ordem ainda não cobra nada"
                text="Nada entra na conta do técnico nem no financeiro até existir linha." />
        @else
            <div class="table-responsive">
                <table class="table nf-table align-middle mb-0">
                    <caption class="visually-hidden">Linhas cobradas nesta ordem de serviço</caption>
                    <thead>
                        <tr>
                            <th scope="col">Descrição</th>
                            <th scope="col">Origem</th>
                            <th scope="col" class="text-end">Quantidade</th>
                            <th scope="col" class="text-end">Unitário</th>
                            <th scope="col" class="text-end">Desconto</th>
                            <th scope="col" class="text-end">Linha</th>
                            @if ($podeEditarLinha)
                                <th scope="col" class="text-end">Ações</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordem->items as $item)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $item->description }}</span>
                                    @if ($item->isService() && $item->service)
                                        <div class="nf-text-muted-2 small">
                                            {{ $item->service->category?->name ?: 'Sem categoria' }}
                                        </div>
                                    @elseif ($item->product)
                                        <div class="nf-text-muted-2 small nf-mono">{{ $item->product->sku }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="nf-status {{ $item->isService() ? 'nf-status-open' : 'nf-status-progress' }}">
                                        {{ $item->isService() ? 'Serviço' : 'Produto' }}
                                    </span>
                                </td>
                                <td class="text-end nf-mono">{{ Formatters::decimal($item->quantity) }}</td>
                                <td class="text-end nf-mono">{{ Formatters::money($item->unit_price) }}</td>
                                <td class="text-end nf-mono">
                                    {{ (float) $item->discount > 0 ? Formatters::money($item->discount) : Formatters::TIME_NULL }}
                                </td>
                                <td class="text-end nf-mono">{{ Formatters::money($item->total()) }}</td>
                                @if ($podeEditarLinha)
                                    <td>
                                        <div class="nf-table-actions">
                                            @canany(['orders.update', 'orders.execute'])
                                            @if ($itemEmEdicao?->is($item))
                                                <x-ui.button variant="ghost" size="sm" :href="route('orders.show', $ordem)"
                                                    icon="fa-solid fa-xmark">Fechar</x-ui.button>
                                            @else
                                                <x-ui.button variant="ghost" size="sm"
                                                    :href="route('orders.show', ['ordem' => $ordem, 'editar_item' => $item->id])"
                                                    icon="fa-solid fa-pen">Editar</x-ui.button>
                                            @endif

                                                <x-ui.action-form :acao="route('orders.items.destroy', [$ordem, $item])"
                                                    rotulo="Remover" :titulo="'Remover a linha '.$item->description.'?'"
                                                    texto="A linha sai da conta desta ordem. O estoque já consumido por ela não volta sozinho." />
                                            @endcanany
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="nf-total-linha mb-0">
                <dt>Bruto</dt>
                <dd>{{ Formatters::money($totais['bruto']) }}</dd>
                <dt>Descontos</dt>
                <dd>{{ Formatters::money($totais['desconto']) }}</dd>
                <dt>Total</dt>
                <dd>{{ Formatters::money($totais['total']) }}</dd>
            </dl>
        @endif

        @canany(['orders.update', 'orders.execute'])
            @if ($encerrada)
                <p class="nf-text-muted-2 small mb-0 mt-2">
                    Abra outra ordem para cobrar o que faltou: corrigir conta fechada reescreveria o que o cliente já recebeu.
                </p>
            @else
                <div class="nf-form-secao">
                    <h2>{{ $itemEmEdicao ? 'Editar a linha '.$itemEmEdicao->description : 'Nova linha cobrada' }}</h2>

                    @if ($itemEmEdicao)
                        <p class="nf-text-muted-2 small">
                            @if ($itemEmEdicao->isService())
                                Serviço do catálogo: <strong>{{ $itemEmEdicao->service?->name ?? $itemEmEdicao->description }}</strong>.
                            @else
                                Produto consumido: <strong>{{ $itemEmEdicao->product?->name ?? $itemEmEdicao->description }}</strong>
                                <span class="nf-mono">{{ $itemEmEdicao->product?->sku }}</span>.
                            @endif
                            A origem da linha não muda aqui — remova a linha e cadastre outra.
                        </p>
                    @endif

                    <form method="POST"
                        action="{{ $itemEmEdicao
                            ? route('orders.items.update', [$ordem, $itemEmEdicao])
                            : route('orders.items.store', $ordem) }}" data-nf-guard novalidate>
                        @csrf
                        @if ($itemEmEdicao)
                            @method('PUT')
                        @endif

                        @unless ($itemEmEdicao)
                            <div class="nf-form-grade">
                                <x-ui.select label="Serviço do catálogo" name="item_servico" :opcoes="$servicos"
                                    placeholder="Nenhum serviço" hint="Escolha o serviço ou o produto — nunca os dois." />

                                <x-ui.select label="Produto consumido" name="item_produto" :opcoes="$produtos"
                                    placeholder="Nenhum produto" />
                            </div>
                        @endunless

                        <div class="nf-form-grade">
                            <x-ui.input label="Quantidade" name="item_quantidade" type="number"
                                :value="$itemEmEdicao?->quantity" step="0.0001" min="0.0001" inputmode="decimal"
                                placeholder="1" required />

                            <x-ui.input label="Valor unitário (R$)" name="item_valor" type="number"
                                :value="$itemEmEdicao?->unit_price" step="0.01" min="0" inputmode="decimal"
                                placeholder="Preço do catálogo" />

                            <x-ui.input label="Desconto da linha (R$)" name="item_desconto" type="number"
                                :value="$itemEmEdicao?->discount" step="0.01" min="0" inputmode="decimal"
                                placeholder="0,00" />

                            <x-ui.input label="Descrição na nota" name="item_descricao"
                                :value="$itemEmEdicao?->description" placeholder="Como a linha aparece para o cliente" />
                        </div>

                        <div class="nf-form-acoes">
                            <x-ui.button type="submit" :variant="$itemEmEdicao ? 'primary' : 'soft-primary'" size="sm"
                                icon="{{ $itemEmEdicao ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}"
                                data-loading="false">
                                {{ $itemEmEdicao ? 'Salvar linha' : 'Adicionar linha' }}
                            </x-ui.button>

                            @if ($itemEmEdicao)
                                <x-ui.button variant="ghost" size="sm" :href="route('orders.show', $ordem)">
                                    Cancelar
                                </x-ui.button>
                            @endif
                        </div>
                    </form>
                </div>
            @endif
        @endcanany
    </x-ui.card>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <x-ui.card title="Quadro de comissão" subtitle="Quem passou por esta ordem, com a data de entrada e de saída.">
                @if ($ordem->assignments->isEmpty())
                    <x-ui.state tone="empty" title="Ninguém comissionado ainda"
                        text="O técnico responsável cuida da ordem; a equipe de apoio entra aqui, e cada passagem fica registrada." />
                @else
                    <ul class="nf-itens mb-3">
                        @foreach ($ordem->assignments as $comissao)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        {{ $comissao->technician->name }}
                                        @if ($comissao->released_at)
                                            <span class="nf-status nf-status-canceled">liberado</span>
                                        @elseif ((int) $ordem->technician_id === (int) $comissao->technician_id)
                                            <span class="nf-status nf-status-done">responsável</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        desde <span class="nf-mono">{{ Formatters::date($comissao->assigned_at) }}</span>
                                        @if ($comissao->released_at)
                                            · saiu em <span class="nf-mono">{{ Formatters::date($comissao->released_at) }}</span>
                                        @endif
                                        <br>
                                        por {{ $comissao->assignedBy?->name ?? 'não informado' }}
                                        @if ($comissao->note)
                                            · {{ $comissao->note }}
                                        @endif
                                    </p>
                                </div>

                                @canany(['orders.update', 'orders.execute'])
                                    @unless ($encerrada)
                                        @unless ($comissao->released_at)
                                            <x-ui.action-form :acao="route('orders.assignments.destroy', [$ordem, $comissao->technician])"
                                                rotulo="Liberar" icon="fa-solid fa-user-minus"
                                                :titulo="'Liberar '.$comissao->technician->name.' desta ordem?'"
                                                texto="A passagem continua na ficha com a data de saída. Se ele era o responsável, a ordem passa ao próximo do quadro." />
                                        @endunless
                                    @endunless
                                @endcanany

                                @can('technicians.view')
                                    <x-ui.button variant="ghost" size="sm" :href="route('technicians.show', $comissao->technician)"
                                        icon="fa-solid fa-arrow-up-right-from-square">Abrir</x-ui.button>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @canany(['orders.update', 'orders.execute'])
                    @if ($encerrada)
                        <p class="nf-text-muted-2 small mb-0">
                            O quadro de uma ordem encerrada é histórico: ninguém entra nem sai.
                        </p>
                    @else
                        <div class="nf-form-secao">
                            <h2>Comissionar técnico</h2>
                            <form method="POST" action="{{ route('orders.assignments.store', $ordem) }}" data-nf-guard novalidate>
                                @csrf
                                <div class="nf-form-grade">
                                    <x-ui.select label="Técnico" name="tecnico_id" :opcoes="$tecnicos"
                                        :placeholder="$ordem->assignments->isEmpty() ? 'Escolha quem entra no quadro' : 'Escolha quem volta ao quadro'"
                                        required />

                                    <x-ui.input label="Observação da comissão" name="comissao_nota"
                                        placeholder="Suporte de refrigeração, leitura de gás..." />
                                </div>

                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="soft-primary" size="sm"
                                        icon="fa-solid fa-user-plus" data-loading="false">Colocar no quadro</x-ui.button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endcanany
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-6">
            <x-ui.card title="Check-in de campo"
                :subtitle="$podeRegistrar
                    ? 'A hora de cada carimbo é a do servidor; a posição é a que o aparelho ler agora.'
                    : 'O que foi medido na chegada e na saída desta ordem, direto do banco.'">

                @if ($podeRegistrar)
                    @php
                        $form = $visitaAberta
                            ? ['acao' => route('checkins.checkout', $visitaAberta), 'metodo' => 'PATCH',
                               'rotulo' => 'Registrar saída', 'icone' => 'fa-solid fa-person-walking-arrow-right',
                               'ajuda' => 'Sair do local não encerra a ordem: concluir é outro passo, com nota e quem aprova.']
                            : ['acao' => route('orders.checkin', $ordem), 'metodo' => null,
                               'rotulo' => 'Registrar chegada', 'icone' => 'fa-solid fa-location-dot',
                               'ajuda' => 'A chegada move a ordem para “em execução” pelo fluxo dela, com a passagem registrada.'];
                    @endphp

                    <form method="POST" action="{{ $form['acao'] }}" class="nf-checkin" data-nf-checkin
                        data-lat-ordem="{{ $ordem->latitude }}" data-lon-ordem="{{ $ordem->longitude }}"
                        data-raio="{{ $raio }}" data-nf-guard novalidate>
                        @csrf
                        @if ($form['metodo'])
                            @method($form['metodo'])
                        @endif

                        <input type="hidden" name="latitude" data-nf-lat value="{{ old('latitude') }}">
                        <input type="hidden" name="longitude" data-nf-lon value="{{ old('longitude') }}">

                        <p class="nf-checkin-leitura" data-nf-leitura data-tom="espera">
                            Posição ainda não lida. Sem ela o registro sai marcado como sem posição — e continua sendo registro válido.
                        </p>

                        @error('latitude')
                            <p class="nf-checkin-erro">{{ $message }}</p>
                        @enderror

                        @error('longitude')
                            <p class="nf-checkin-erro">{{ $message }}</p>
                        @enderror

                        @if ($visitaAberta)
                            <p class="nf-checkin-aberta">
                                Em campo desde <span class="nf-mono">{{ Formatters::dateTime($visitaAberta->checkin_at) }}</span>
                                por {{ $visitaAberta->technician?->name ?? 'técnico sem ficha' }}.
                            </p>
                        @endif

                        <x-ui.textarea label="Relato do local" name="observacao" :rows="2"
                            placeholder="Portão fechado, cliente avisado, equipamento no 3º andar..." />

                        <div class="nf-form-acoes">
                            <x-ui.button type="button" variant="ghost" size="sm"
                                icon="fa-solid fa-satellite-dish" data-nf-ler>
                                Ler posição do aparelho
                            </x-ui.button>

                            <x-ui.button type="submit" variant="primary" size="sm" :icon="$form['icone']"
                                data-loading="false">
                                {{ $form['rotulo'] }}
                            </x-ui.button>
                        </div>

                        <p class="nf-text-muted-2 small mb-0">{{ $form['ajuda'] }}</p>
                    </form>
                @endif

                @if ($ordem->checkins->isEmpty())
                    @unless ($podeRegistrar)
                        <x-ui.state tone="empty" title="Nenhum check-in nesta ordem"
                            text="Chegada e saída são registradas pelo técnico responsável, na ficha da ordem. Esta conta lê o que o campo mediu." />
                    @endunless
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($ordem->checkins as $registro)
                            @php
                                $fora = $registro->foraDoRaio();
                            @endphp
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        {{ $registro->technician?->name ?? 'Técnico sem ficha' }}
                                        <span class="{{ StatusCatalog::badge('checkin', $registro->status) }}">
                                            {{ StatusCatalog::label('checkin', $registro->status) }}
                                        </span>
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        entrada <span class="nf-mono">{{ Formatters::dateTime($registro->checkin_at) }}</span>
                                        @if ($registro->checkout_at)
                                            · saída <span class="nf-mono">{{ Formatters::dateTime($registro->checkout_at) }}</span>
                                            · <span class="nf-mono">{{ Formatters::duration($registro->duracaoMinutos()) }}</span> no local
                                        @endif
                                        <br>
                                        @if ($fora === null)
                                            <span class="nf-status nf-status-draft">sem posição lida</span>
                                        @elseif ($fora)
                                            <span class="nf-status nf-status-waiting">
                                                fora do raio: {{ Formatters::decimal($registro->checkin_distance) }} m do endereço
                                            </span>
                                        @else
                                            <span class="nf-status nf-status-done">
                                                {{ Formatters::decimal($registro->checkin_distance) }} m do endereço
                                            </span>
                                        @endif

                                        @if ($registro->checkin_latitude !== null && $registro->checkin_longitude !== null)
                                            <span class="nf-mono">
                                                · {{ Formatters::decimal($registro->checkin_latitude, 6) }},
                                                {{ Formatters::decimal($registro->checkin_longitude, 6) }}
                                            </span>
                                        @endif

                                        @if ($registro->observation)
                                            <br>{{ $registro->observation }}
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="nf-text-muted-2 small mb-0 mt-2">
                    O raio aceito por esta empresa é de {{ Formatters::decimal($raio) }} m. Carimbo, coordenada e
                    distância medida não têm campo de edição: o que o campo registrou é histórico, e a correção de
                    um erro se faz com outra passagem, nunca riscando a primeira.
                </p>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
