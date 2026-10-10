@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $endereco = $cliente->enderecoPrincipal();
    $novoContato = new \App\Models\ClientContact(['is_primary' => $cliente->contacts->isEmpty()]);
    $novoEndereco = new \App\Models\Address([
        'type' => 'service',
        'is_primary' => $cliente->addresses->isEmpty(),
    ]);

    // Um substantivo com dois números, no singular ou no plural, como a casa faz
    // desde a fase 9: a tela concorda com o banco em vez de dizer "1 ordens".
    $contagem = fn (int $total, string $singular, string $plural) => $total === 1 ? "1 {$singular}" : "{$total} {$plural}";

    $resumoOrdens = match (true) {
        $totalOrdens === 0 => 'Nenhuma ordem no nome dele ainda.',
        $totalOrdens === 1 => 'Uma ordem no banco — ela está aqui.',
        default => $contagem($totalOrdens, 'ordem registrada', 'ordens registradas').' — as mais recentes primeiro.',
    };

    $resumoChamados = match (true) {
        $totalChamados === 0 => 'Nenhum chamado aberto por ele.',
        $totalChamados === 1 => 'Um chamado no banco — ele está aqui.',
        default => $contagem($totalChamados, 'chamado registrado', 'chamados registrados').' — os mais recentes primeiro.',
    };

    $resumoAgenda = $agenda['total'] === 0
        ? 'Nenhuma janela reservada para este cliente.'
        : $contagem($agenda['total'], 'janela reservada', 'janelas reservadas').' — as que ainda vêm estão aqui.';
@endphp

<x-layouts.app
    :title="$cliente->name"
    :subtitle="$cliente->trade_name ?: 'Ficha do cliente: contatos, endereços e o que ele já gerou.'"
    :trilha="['Cadastros' => route('clients.index'), 'Clientes' => route('clients.index'), $cliente->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('client', $cliente->status) }}">
                {{ StatusCatalog::label('client', $cliente->status) }}
            </span>
            @if ($cliente->trashed())
                <span class="nf-status nf-status-canceled ms-2">
                    Excluído em {{ Formatters::date($cliente->deleted_at) }}
                </span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('clients.index')" icon="fa-solid fa-list">
                Voltar à carteira
            </x-ui.button>

            @unless ($cliente->trashed())
                @can('clients.update')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('clients.edit', $cliente)"
                        icon="fa-solid fa-pen">Editar cadastro</x-ui.button>
                @endcan

                @can('clients.delete')
                    <x-ui.action-form :acao="route('clients.destroy', $cliente)" rotulo="Excluir"
                        titulo="Excluir {{ $cliente->name }}?"
                        texto="Só é possível excluir quem ainda não gerou ordem, chamado ou lançamento. Inativar preserva o histórico." />
                @endcan
            @else
                @can('clients.delete')
                    <x-ui.action-form :acao="route('clients.restore', $cliente)" metodo="PATCH" rotulo="Restaurar"
                        titulo="Restaurar {{ $cliente->name }}?" texto="O cliente volta à carteira e pode receber serviço."
                        icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                @endcan
            @endunless
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Cadastro" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $cliente->name }}</dd>
                    </div>
                    <div>
                        <dt>Fantasia</dt>
                        <dd>{{ $cliente->trade_name ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Documento</dt>
                        <dd class="nf-mono">{{ $cliente->document ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Telefone</dt>
                        <dd class="nf-mono">{{ $cliente->phone ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>E-mail</dt>
                        <dd>{{ $cliente->email ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Cadastrado em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($cliente->created_at) }}</dd>
                    </div>
                </dl>

                @if ($cliente->notes)
                    <div class="nf-form-secao">
                        <h2>Observações internas</h2>
                        <p class="mb-0 nf-pre-line">{{ $cliente->notes }}</p>
                    </div>
                @endif
            </x-ui.card>
        </div>

        @can('orders.view')
            <div class="col-12 col-xl-5">
                <x-ui.card title="Ordens de serviço" :subtitle="$resumoOrdens">
                    <x-slot:tools>
                        <a class="nf-text-muted-2" href="{{ route('orders.index', ['cliente' => $cliente->id]) }}">
                            Ver todas
                        </a>
                    </x-slot:tools>

                    @if ($ordens->isEmpty())
                        <x-ui.state tone="empty" title="Nenhuma ordem no nome deste cliente"
                            text="É daqui que a operação inteira nasce: sem ordem, não há chamado, visita nem conta a cobrar." />
                    @else
                        <ul class="nf-itens mb-0">
                            @foreach ($ordens as $ordem)
                                <li class="nf-item-linha nf-item-linha-lado">
                                    <div>
                                        <p class="mb-0">
                                            <a class="fw-semibold nf-mono" href="{{ route('orders.show', $ordem) }}">
                                                {{ $ordem->number }}
                                            </a>
                                        </p>
                                        <p class="mb-0 nf-text-muted-2 small">{{ $ordem->title }}</p>
                                        <p class="mb-0 nf-text-muted-2 small">
                                            @if ($ordem->scheduled_starts_at)
                                                <span class="nf-mono">{{ Formatters::dateTime($ordem->scheduled_starts_at) }}</span>
                                            @else
                                                sem janela marcada
                                            @endif
                                            @if ($ordem->technician)
                                                · {{ $ordem->technician->name }}
                                            @endif
                                            @if ($ordem->service)
                                                · {{ $ordem->service->name }}
                                            @endif
                                        </p>
                                    </div>

                                    <span class="{{ StatusCatalog::badge('order', $ordem->status) }}">
                                        {{ StatusCatalog::label('order', $ordem->status) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endcan
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <x-ui.card title="Contatos" subtitle="Quem atende do lado do cliente. Um deles é o principal.">
                @if ($cliente->contacts->isEmpty())
                    <x-ui.state tone="empty" title="Nenhum contato"
                        text="Cadastre quem recebe a ordem de serviço para o campo não ficar sem referência." />
                @else
                    <ul class="nf-itens mb-3">
                        @foreach ($cliente->contacts as $contato)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        {{ $contato->name }}
                                        @if ($contato->is_primary)
                                            <span class="nf-status nf-status-done">principal</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        {{ $contato->role ?: 'Sem cargo' }}
                                        @if ($contato->phone)
                                            · <span class="nf-mono">{{ $contato->phone }}</span>
                                        @endif
                                        @if ($contato->email)
                                            · {{ $contato->email }}
                                        @endif
                                    </p>
                                </div>

                                @can('clients.update')
                                    @if ($contatoEmEdicao?->is($contato))
                                        <x-ui.button variant="ghost" size="sm" :href="route('clients.show', $cliente)"
                                            icon="fa-solid fa-xmark">Fechar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('clients.show', ['cliente' => $cliente, 'editar_contato' => $contato->id])"
                                            icon="fa-solid fa-pen">Editar</x-ui.button>
                                    @endif
                                @endcan

                                @can('clients.delete')
                                    <x-ui.action-form :acao="route('clients.contacts.destroy', [$cliente, $contato])"
                                        rotulo="Remover" titulo="Remover o contato {{ $contato->name }}?"
                                        texto="O contato sai da ficha. Ordens já emitidas não mudam." />
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('clients.update')
                    @if ($contatoEmEdicao)
                        <div class="nf-form-secao">
                            <h2>Editar {{ $contatoEmEdicao->name }}</h2>
                            <form method="POST" action="{{ route('clients.contacts.update', [$cliente, $contatoEmEdicao]) }}"
                                data-nf-guard novalidate>
                                @csrf
                                @method('PUT')
                                <x-ui.contact-fields :contato="$contatoEmEdicao" />
                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="primary" size="sm"
                                        icon="fa-solid fa-floppy-disk" data-loading="false">Salvar contato</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" :href="route('clients.show', $cliente)">
                                        Cancelar
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="nf-form-secao">
                            <h2>Novo contato</h2>
                            <form method="POST" action="{{ route('clients.contacts.store', $cliente) }}" data-nf-guard novalidate>
                                @csrf
                                <x-ui.contact-fields :contato="$novoContato" />
                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="soft-primary" size="sm" icon="fa-solid fa-plus"
                                        data-loading="false">Adicionar contato</x-ui.button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endcan
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-6">
            <x-ui.card title="Endereços" subtitle="É daqui que o check-in de campo mede a distância.">
                @if ($cliente->addresses->isEmpty())
                    <x-ui.state tone="empty" title="Nenhum endereço"
                        text="Sem endereço com coordenadas, o check-in não tem para onde medir a chegada." />
                @else
                    <ul class="nf-itens mb-3">
                        @foreach ($cliente->addresses as $end)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        {{ StatusCatalog::label('address', $end->type) }}
                                        @if ($end->is_primary)
                                            <span class="nf-status nf-status-done">principal</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        {{ $end->street }}{{ $end->number ? ', '.$end->number : '' }}
                                        @if ($end->complement)
                                            · {{ $end->complement }}
                                        @endif
                                        <br>
                                        {{ $end->neighborhood ?: 'Sem bairro' }} ·
                                        {{ $end->city }}/{{ $end->state }} ·
                                        <span class="nf-mono">{{ $end->zip_code ?: Formatters::TIME_NULL }}</span>
                                        <br>
                                        @if ($end->latitude && $end->longitude)
                                            <span class="nf-mono">
                                                {{ Formatters::decimal($end->latitude, 6) }},
                                                {{ Formatters::decimal($end->longitude, 6) }}
                                            </span>
                                        @else
                                            <span class="nf-status nf-status-waiting">sem coordenadas</span>
                                        @endif
                                    </p>
                                </div>

                                @can('clients.update')
                                    @if ($enderecoEmEdicao?->is($end))
                                        <x-ui.button variant="ghost" size="sm" :href="route('clients.show', $cliente)"
                                            icon="fa-solid fa-xmark">Fechar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('clients.show', ['cliente' => $cliente, 'editar_endereco' => $end->id])"
                                            icon="fa-solid fa-pen">Editar</x-ui.button>
                                    @endif
                                @endcan

                                @can('clients.delete')
                                    <x-ui.action-form :acao="route('clients.addresses.destroy', [$cliente, $end])"
                                        rotulo="Remover" titulo="Remover este endereço?"
                                        texto="{{ $end->city }}/{{ $end->state }} sai da ficha do cliente." />
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('clients.update')
                    @if ($enderecoEmEdicao)
                        <div class="nf-form-secao">
                            <h2>Editar endereço</h2>
                            <form method="POST" action="{{ route('clients.addresses.update', [$cliente, $enderecoEmEdicao]) }}"
                                data-nf-guard novalidate>
                                @csrf
                                @method('PUT')
                                <x-ui.address-fields :endereco="$enderecoEmEdicao" :tipos="$tiposDeEndereco" />
                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="primary" size="sm"
                                        icon="fa-solid fa-floppy-disk" data-loading="false">Salvar endereço</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" :href="route('clients.show', $cliente)">
                                        Cancelar
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="nf-form-secao">
                            <h2>Novo endereço</h2>
                            <form method="POST" action="{{ route('clients.addresses.store', $cliente) }}" data-nf-guard novalidate>
                                @csrf
                                <x-ui.address-fields :endereco="$novoEndereco" :tipos="$tiposDeEndereco" />
                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="soft-primary" size="sm" icon="fa-solid fa-plus"
                                        data-loading="false">Adicionar endereço</x-ui.button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endcan
            </x-ui.card>
        </div>
    </div>

    <div class="row g-3 mt-1">
        @can('tickets.view')
            <div class="col-12 col-xl-7">
                <x-ui.card title="Chamados" :subtitle="$resumoChamados">
                    <x-slot:tools>
                        <a class="nf-text-muted-2" href="{{ route('tickets.index', ['cliente' => $cliente->id]) }}">
                            Ver todas
                        </a>
                    </x-slot:tools>

                    @if ($chamados->isEmpty())
                        <x-ui.state tone="empty" title="Nenhum chamado no nome deste cliente"
                            text="Chamado é a voz dele quando algo que já foi atendido quebra de novo. Sem chamado, o histórico dele é só ordem." />
                    @else
                        <ul class="nf-itens mb-0">
                            @foreach ($chamados as $chamado)
                                <li class="nf-item-linha nf-item-linha-lado">
                                    <div>
                                        <p class="mb-0">
                                            <a class="fw-semibold nf-mono" href="{{ route('tickets.show', $chamado) }}">
                                                {{ $chamado->protocol }}
                                            </a>
                                        </p>
                                        <p class="mb-0 nf-text-muted-2 small">{{ $chamado->subject }}</p>
                                        <p class="mb-0 nf-text-muted-2 small">
                                            <span class="nf-mono">{{ Formatters::dateTime($chamado->opened_at) }}</span>
                                            · {{ StatusCatalog::label('priority', $chamado->priority) }}
                                            @if ($chamado->technician)
                                                · {{ $chamado->technician->name }}
                                            @endif
                                        </p>
                                    </div>

                                    <span class="{{ StatusCatalog::badge('ticket', $chamado->status) }}">
                                        {{ StatusCatalog::label('ticket', $chamado->status) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endcan

        @can('agenda.view')
            <div class="col-12 col-xl-5">
                <x-ui.card title="Agenda" :subtitle="$resumoAgenda">
                    <x-slot:tools>
                        <a class="nf-text-muted-2" href="{{ route('agenda.index') }}">Abrir o calendário</a>
                    </x-slot:tools>

                    @if ($agenda['total'] === 0)
                        <x-ui.state tone="empty" title="Nenhuma janela reservada"
                            text="A agenda é o que combina a visita antes de ela acontecer; sem compromisso marcado, o campo chega quando a ordem mandou." />
                    @else
                        @if ($agenda['proximas']->isNotEmpty())
                            <ul class="nf-itens mb-0">
                                @foreach ($agenda['proximas'] as $janela)
                                    <li class="nf-item-linha nf-item-linha-lado">
                                        <div>
                                            <p class="mb-0">
                                                <a class="fw-semibold" href="{{ route('agenda.show', $janela) }}">
                                                    {{ $janela->title }}
                                                </a>
                                            </p>
                                            <p class="mb-0 nf-text-muted-2 small nf-mono">
                                                {{ $janela->all_day
                                                    ? Formatters::date($janela->starts_at).' · dia inteiro'
                                                    : Formatters::dateTime($janela->starts_at).' → '.Formatters::time($janela->ends_at) }}
                                            </p>
                                            <p class="mb-0 nf-text-muted-2 small">
                                                {{ StatusCatalog::label('appointment_type', $janela->type) }}
                                                @if ($janela->technician)
                                                    · {{ $janela->technician->name }}
                                                @endif
                                                @if ($janela->serviceOrder)
                                                    · <span class="nf-mono">{{ $janela->serviceOrder->number }}</span>
                                                @endif
                                            </p>
                                        </div>

                                        <span class="{{ StatusCatalog::badge('appointment', $janela->status) }}">
                                            {{ StatusCatalog::label('appointment', $janela->status) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="nf-text-muted-2 small mb-0">
                                Nenhuma janela por vir: o que está registrado já passou ou foi cancelado.
                            </p>
                        @endif

                        @if ($agenda['passada'])
                            <p class="nf-text-muted-2 small mb-0 mt-2">
                                Última janela no nome dele:
                                <span class="nf-mono">{{ Formatters::dateTime($agenda['passada']->starts_at) }}</span>
                                · {{ StatusCatalog::label('appointment', $agenda['passada']->status) }}.
                            </p>
                        @endif
                    @endif
                </x-ui.card>
            </div>
        @endcan
    </div>

    <div class="row g-3 mt-1">
        @can('orders.view')
            <div class="col-12 col-xl-7">
                <x-ui.card title="Serviços que ele usa"
                    subtitle="Somados das linhas das ordens dele, no MySQL — não é lista digitada à mão.">
                    @if ($servicos->isEmpty())
                        <x-ui.state tone="empty" title="Nenhum serviço cobrado até agora"
                            text="O que ele usa é o que as ordens dele cobram: enquanto não houver linha de serviço, o cartão fica vazio de propósito." />
                    @else
                        <div class="table-responsive">
                            <table class="table nf-table align-middle mb-0">
                                <caption class="visually-hidden">Serviços que já saíram das ordens de serviço deste cliente</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Serviço</th>
                                        <th scope="col" class="text-end">Ordens</th>
                                        <th scope="col" class="text-end">Quantidade</th>
                                        <th scope="col" class="text-end">Cobrado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($servicos as $linha)
                                        <tr>
                                            <td class="fw-semibold">{{ $linha->servico }}</td>
                                            <td class="text-end nf-mono">{{ $linha->ordens }}</td>
                                            <td class="text-end nf-mono">{{ Formatters::decimal($linha->quantidade) }}</td>
                                            <td class="text-end nf-mono">{{ Formatters::money((float) $linha->valor) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="nf-text-muted-2 small mb-0 mt-2">
                            O valor é a linha líquida — quantidade vezes preço, menos o desconto da própria linha —
                            somada em todas as ordens que este cliente tem no banco.
                        </p>
                    @endif
                </x-ui.card>
            </div>
        @endcan

        @if ($trilha !== null)
            <div class="col-12 col-xl-5">
                <x-ui.card title="Trilha desta ficha" subtitle="Os últimos atos gravados sobre esta linha do cadastro.">
                    <x-slot:tools>
                        <a class="nf-text-muted-2" href="{{ route('audit.index', ['entidade' => 'Client']) }}">
                            Auditoria de clientes
                        </a>
                    </x-slot:tools>

                    @if ($trilha->isEmpty())
                        <x-ui.state tone="empty" title="Nenhum ato registrado nesta ficha"
                            text="Cadastro criado por carga inicial não tem autor de tela: a trilha começa a contar a partir da primeira edição feita aqui." />
                    @else
                        <ul class="nf-itens mb-0">
                            @foreach ($trilha as $ato)
                                <li class="nf-item-linha nf-item-linha-lado">
                                    <div>
                                        <p class="mb-0">
                                            <span class="nf-status nf-status-draft">{{ $ato->action }}</span>
                                        </p>
                                        <p class="mb-0 nf-text-muted-2 small">
                                            <span class="nf-mono">{{ Formatters::dateTime($ato->created_at) }}</span>
                                            · {{ $ato->user_name ?: 'sem autor' }}
                                        </p>
                                        @if ($ato->description)
                                            <p class="mb-0 nf-text-muted-2 small">{{ $ato->description }}</p>
                                        @endif
                                    </div>

                                    <x-ui.button variant="ghost" size="sm" :href="route('audit.show', $ato)"
                                        icon="fa-solid fa-eye">Abrir</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endif
    </div>

    @if ($financas !== null)
        <div class="row g-3 mt-1">
            <div class="col-12">
                <x-ui.card title="Financeiro deste cliente"
                    subtitle="Receita lançada no nome dele, somada pela mesma régua do rodapé do financeiro.">
                    <x-slot:tools>
                        <a class="nf-text-muted-2" href="{{ route('financial.index', ['cliente' => $cliente->id]) }}">
                            Abrir o financeiro filtrado nele
                        </a>
                    </x-slot:tools>

                    @if ($financas['registros'] === 0)
                        <x-ui.state tone="empty" title="Nenhuma conta lançada no nome dele"
                            text="O caixa aparece aqui quando a primeira receita é lançada contra este cliente — nada é estimado antes disso." />
                    @else
                        <div class="row g-3">
                            <div class="col-6 col-lg-3">
                                <p class="nf-kpi-label mb-1">Lançado</p>
                                <p class="nf-kpi-value mb-0 nf-mono">{{ Formatters::money($financas['bruto']) }}</p>
                                <p class="nf-kpi-hint mb-0">{{ $contagem($financas['registros'], 'conta de receita', 'contas de receita') }}</p>
                            </div>
                            <div class="col-6 col-lg-3">
                                <p class="nf-kpi-label mb-1">Recebido</p>
                                <p class="nf-kpi-value mb-0 nf-mono">{{ Formatters::money($financas['pago']) }}</p>
                                <p class="nf-kpi-hint mb-0">soma dos pagamentos registrados</p>
                            </div>
                            <div class="col-6 col-lg-3">
                                <p class="nf-kpi-label mb-1">A receber</p>
                                <p class="nf-kpi-value mb-0 nf-mono">{{ Formatters::money($financas['em_aberto']) }}</p>
                                <p class="nf-kpi-hint mb-0">conta sem pagamento fechado</p>
                            </div>
                            <div class="col-6 col-lg-3">
                                <p class="nf-kpi-label mb-1">Vencido</p>
                                <p class="nf-kpi-value mb-0 {{ $financas['vencido'] > 0 ? 'nf-status-canceled' : '' }}">
                                    {{ Formatters::money($financas['vencido_valor']) }}
                                </p>
                                <p class="nf-kpi-hint mb-0">
                                    {{ $contagem($financas['vencido'], 'conta atrasada', 'contas atrasadas') }}
                                </p>
                            </div>
                        </div>
                    @endif
                </x-ui.card>
            </div>
        </div>
    @endif
</x-layouts.app>
