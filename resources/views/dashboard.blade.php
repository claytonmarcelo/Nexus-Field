@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Dashboard"
    subtitle="Indicadores e filas consultados no banco desta empresa, conforme as permissões do seu papel."
>
    @if ($painel['kpis'] !== [])
        <div class="row g-3">
            @foreach ($painel['kpis'] as $kpi)
                <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                    <div class="card nf-kpi h-100">
                        <div class="card-body">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="nf-icon-tile tone-{{ $kpi['tone'] }}" aria-hidden="true">
                                    <i class="{{ $kpi['icon'] }}"></i>
                                </span>

                                <div class="flex-grow-1 nf-kpi-body">
                                    <p class="nf-kpi-label mb-1">{{ $kpi['label'] }}</p>
                                    <p class="nf-kpi-value mb-0 {{ str_starts_with((string) $kpi['value'], 'R$ ') ? 'nf-kpi-value-money' : '' }}">
                                        {{ $kpi['value'] }}
                                    </p>

                                    @if (isset($kpi['delta']))
                                        <p class="nf-kpi-delta is-{{ $kpi['delta']['direction'] }} mb-0 mt-2">
                                            <i class="fa-solid {{ $kpi['delta']['direction'] === 'down' ? 'fa-arrow-down' : ($kpi['delta']['direction'] === 'up' ? 'fa-arrow-up' : 'fa-minus') }}" aria-hidden="true"></i>
                                            <span>{{ $kpi['delta']['text'] }}</span>
                                        </p>
                                    @elseif (!empty($kpi['hint']))
                                        <p class="nf-kpi-hint mb-0 mt-2">{{ $kpi['hint'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3 mt-1">
        @if ($painel['ordens'] !== [])
            <div class="col-12 col-xl-8">
                <x-ui.card title="Ordens na fila" subtitle="Próximos 7 dias, pela data agendada no banco.">
                    @if ($painel['ordens']['proximas']->isEmpty())
                        <x-ui.state tone="empty" title="Nenhuma ordem agendada">
                            Nada com data marcada para os próximos sete dias nesta empresa.
                        </x-ui.state>
                    @else
                        <div class="table-responsive">
                            <table class="table nf-table nf-table-wrap align-middle mb-0">
                                <caption class="visually-hidden">Ordens de serviço agendadas para os próximos sete dias</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Número</th>
                                        <th scope="col">Cliente</th>
                                        <th scope="col">Agendada</th>
                                        <th scope="col">Técnico</th>
                                        <th scope="col">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($painel['ordens']['proximas'] as $ordem)
                                        <tr>
                                            <td class="nf-mono">
                                                <a class="fw-semibold" href="{{ route('orders.show', $ordem) }}">{{ $ordem->number }}</a>
                                            </td>
                                            <td>{{ $ordem->client->name }}</td>
                                            <td class="nf-mono">
                                                {{ Formatters::dateTime($ordem->scheduled_starts_at) }}
                                            </td>
                                            <td>{{ $ordem->technician?->name ?? 'Sem técnico atribuído' }}</td>
                                            <td>
                                                <span class="{{ StatusCatalog::badge('order', $ordem->status) }}">
                                                    {{ StatusCatalog::label('order', $ordem->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($painel['ordens']['fila_atrasada']->isNotEmpty())
                        <x-slot:footer>
                            <p class="mb-1 nf-kpi-label">Prazo vencido</p>
                            <ul class="nf-fact-list mb-0">
                                @foreach ($painel['ordens']['fila_atrasada'] as $ordem)
                                    <li>
                                        <span>
                                            <span class="nf-mono">{{ $ordem->number }}</span>
                                            · {{ $ordem->client->name }}
                                        </span>
                                        <strong class="nf-mono nf-status-canceled">
                                            {{ Formatters::date($ordem->scheduled_ends_at) }}
                                        </strong>
                                    </li>
                                @endforeach
                            </ul>
                        </x-slot:footer>
                    @endif
                </x-ui.card>
            </div>

            <div class="col-12 col-xl-4">
                <x-ui.card title="Ordens por estado" subtitle="Contagem real por status, do total registrado.">
                    @if ($painel['ordens']['distribuicao'] === [])
                        <x-ui.state tone="empty" title="Sem ordens registradas">
                            A distribuição aparece quando houver a primeira ordem desta empresa.
                        </x-ui.state>
                    @else
                        <ul class="nf-bar-list mb-0">
                            @foreach ($painel['ordens']['distribuicao'] as $linha)
                                <li>
                                    <div class="nf-bar-head">
                                        <span>{{ $linha['label'] }}</span>
                                        <span class="nf-mono">{{ $linha['total'] }}</span>
                                    </div>
                                    <div class="nf-bar-track" role="img"
                                        aria-label="{{ $linha['label'] }}: {{ $linha['total'] }} ordens ({{ $linha['porcentagem'] }}% do total)">
                                        <div class="nf-bar-fill tone-{{ $linha['tone'] }}"
                                            style="--nf-bar-width: {{ $linha['porcentagem'] }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endif
    </div>

    <div class="row g-3 mt-1">
        @if ($painel['agenda'] !== [])
            <div class="col-12 col-xl">
                <x-ui.card title="Agenda de hoje" :subtitle="$usuario->technician
                    ? 'As janelas marcadas para você hoje.'
                    : 'Compromissos gravados na agenda desta empresa.'">
                    @if ($painel['agenda']['hoje']->isEmpty())
                        <x-ui.state tone="empty" title="Dia sem compromissos">
                            <p class="mb-2">Nenhuma janela começando hoje. O calendário mostra a semana inteira.</p>
                            <x-ui.button variant="ghost" size="sm" :href="route('agenda.index')"
                                icon="fa-solid fa-calendar-days">Abrir a agenda</x-ui.button>
                        </x-ui.state>
                    @else
                        <ul class="nf-timeline mb-0">
                            @foreach ($painel['agenda']['hoje'] as $compromisso)
                                <li>
                                    <span class="nf-timeline-time nf-mono">{{ Formatters::time($compromisso->starts_at) }}</span>
                                    <div class="nf-timeline-body">
                                        <p class="mb-0">
                                            <a href="{{ route('agenda.show', $compromisso) }}">{{ $compromisso->title }}</a>
                                        </p>
                                        <p class="mb-0 nf-timeline-text">
                                            {{ $compromisso->technician?->name ?? 'Sem técnico' }}
                                            @if ($compromisso->client)
                                                · {{ $compromisso->client->name }}
                                            @endif
                                            @if ($compromisso->location)
                                                · {{ $compromisso->location }}
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <x-slot:tools>
                        <a class="nf-text-muted-2" href="{{ route('agenda.index') }}">
                            {{ $painel['agenda']['proximos'] }} em 7 dias
                        </a>
                    </x-slot:tools>
                </x-ui.card>
            </div>
        @endif

        @if ($painel['chamados'] !== [])
            <div class="col-12 col-xl-8">
                <x-ui.card title="Chamados na fila" subtitle="Abertos, pela prioridade e pelo tempo em espera.">
                    @if ($painel['chamados']['fila']->isEmpty())
                        <x-ui.state tone="empty" title="Nenhum chamado aberto">
                            Fila zerada nesta empresa agora.
                        </x-ui.state>
                    @else
                        <div class="table-responsive">
                            <table class="table nf-table nf-table-wrap align-middle mb-0">
                                <caption class="visually-hidden">Chamados abertos ordenados por prioridade</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Protocolo</th>
                                        <th scope="col">Assunto</th>
                                        <th scope="col">Cliente</th>
                                        <th scope="col">Prioridade</th>
                                        <th scope="col">Aberto em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($painel['chamados']['fila'] as $chamado)
                                        <tr>
                                            <td class="nf-mono">
                                                <a class="fw-semibold" href="{{ route('tickets.show', $chamado) }}">{{ $chamado->protocol }}</a>
                                            </td>
                                            <td>{{ $chamado->subject }}</td>
                                            <td>{{ $chamado->client->name }}</td>
                                            <td>
                                                <span class="{{ StatusCatalog::badge('priority', $chamado->priority) }}">
                                                    {{ StatusCatalog::label('priority', $chamado->priority) }}
                                                </span>
                                            </td>
                                            <td class="nf-mono text-nowrap">{{ Formatters::date($chamado->opened_at) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <x-slot:footer>
                        <ul class="nf-fact-list mb-0">
                            <li>
                                <span>Resolvidos nos últimos 7 dias</span>
                                <strong class="nf-mono">{{ $painel['chamados']['resolvidos'] }}</strong>
                            </li>
                            <li>
                                <span>Tempo médio de resolução (30 dias)</span>
                                <strong class="nf-mono">
                                    @if ($painel['chamados']['tempo_medio'] === null)
                                        <span class="nf-text-muted-2">Sem resolução registrada</span>
                                    @else
                                        {{ Formatters::decimal($painel['chamados']['tempo_medio'], 1) }} h
                                    @endif
                                </strong>
                            </li>
                        </ul>
                    </x-slot:footer>
                </x-ui.card>
            </div>
        @endif
    </div>

    <div class="row g-3 mt-1">
        @if ($painel['financeiro'] !== [])
            <div class="col-12 col-xl">
                <x-ui.card title="Carteira financeira" subtitle="Somas reais de lançamentos e pagamentos do mês.">
                    <ul class="nf-fact-list">
                        <li>
                            <span>A receber</span>
                            <strong class="nf-mono">{{ Formatters::money($painel['financeiro']['a_receber']) }}</strong>
                        </li>
                        <li>
                            <span>Desses, vencidos</span>
                            <strong class="nf-mono {{ $painel['financeiro']['vencido'] > 0 ? 'nf-status-canceled' : '' }}">
                                {{ Formatters::money($painel['financeiro']['vencido']) }}
                            </strong>
                        </li>
                        <li>
                            <span>Recebido no mês (pagamentos)</span>
                            <strong class="nf-mono">{{ Formatters::money($painel['financeiro']['receita_mes']) }}</strong>
                        </li>
                        <li>
                            <span>Despesa paga no mês</span>
                            <strong class="nf-mono">{{ Formatters::money($painel['financeiro']['despesa_mes']) }}</strong>
                        </li>
                        <li>
                            <span>Saldo do mês</span>
                            <strong class="nf-mono">
                                {{ Formatters::money($painel['financeiro']['receita_mes'] - $painel['financeiro']['despesa_mes']) }}
                            </strong>
                        </li>
                    </ul>

                    @if ($painel['financeiro']['venceram']->isNotEmpty())
                        <p class="nf-kpi-label mb-1">Vencem em até 15 dias</p>
                        <ul class="nf-fact-list mb-0">
                            @foreach ($painel['financeiro']['venceram'] as $lancamento)
                                <li>
                                    <span>
                                        {{ $lancamento->description }}
                                        <span class="nf-text-muted-2">· {{ $lancamento->client?->name ?? 'Sem cliente' }}</span>
                                    </span>
                                    <strong class="nf-mono">
                                        {{ Formatters::money($lancamento->amount) }}
                                        <span class="nf-text-muted-2">{{ Formatters::date($lancamento->due_date) }}</span>
                                    </strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endif

        @if ($painel['estoque'] !== [])
            <div class="col-12 col-xl">
                <x-ui.card title="Estoque para repor" subtitle="Saldo central derivado das movimentações, contra o ponto de reposição.">
                    @if ($painel['estoque']['reposicao']->isEmpty())
                        <x-ui.state tone="empty" title="Nenhum item abaixo do ponto">
                            Todo o estoque central está acima do mínimo configurado.
                        </x-ui.state>
                    @else
                        <div class="table-responsive">
                            <table class="table nf-table nf-table-wrap align-middle mb-0">
                                <caption class="visually-hidden">Produtos com saldo central abaixo do ponto de reposição</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">SKU</th>
                                        <th scope="col">Produto</th>
                                        <th scope="col" class="text-end">Saldo / ponto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($painel['estoque']['reposicao'] as $produto)
                                        <tr>
                                            <td class="nf-mono">{{ $produto->sku }}</td>
                                            <td>{{ $produto->name }}</td>
                                            <td class="nf-mono text-end">
                                                <span class="nf-status-canceled">{{ Formatters::decimal($produto->central_balance, 2) }}</span>
                                                <span class="nf-text-muted-2">/ {{ Formatters::decimal($produto->reorder_point, 2) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            </div>
        @endif
    </div>

    <div class="row g-3 mt-1">
        @if ($painel['equipe'] !== [])
            <div class="col-12 col-xl">
                <x-ui.card title="Equipe" subtitle="Técnicos ativos e ordens em aberto de cada um.">
                    @if ($painel['equipe']['lista']->isEmpty())
                        <x-ui.state tone="empty" title="Nenhum técnico cadastrado">
                            Cadastre a equipe para ver a carga de trabalho por aqui.
                        </x-ui.state>
                    @else
                        <ul class="nf-fact-list mb-0">
                            @foreach ($painel['equipe']['lista'] as $tecnico)
                                <li>
                                    <span>
                                        {{ $tecnico->name }}
                                        <span class="{{ StatusCatalog::badge('technician', $tecnico->status) }}">
                                            {{ StatusCatalog::label('technician', $tecnico->status) }}
                                        </span>
                                    </span>
                                    <strong class="nf-mono">{{ $tecnico->service_orders_count }} em aberto</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endif

        @if ($painel['notificacoes'] !== [])
            <div class="col-12 col-xl">
                <x-ui.card title="Notificações" subtitle="Avisos gravados para esta conta.">
                    @if ($painel['notificacoes']['lista']->isEmpty())
                        <x-ui.state tone="empty" title="Nada novo">
                            Nenhuma notificação para {{ $usuario->name }} nesta empresa.
                        </x-ui.state>
                    @else
                        <ul class="nf-timeline mb-0">
                            @foreach ($painel['notificacoes']['lista'] as $notificacao)
                                <li>
                                    <span class="nf-timeline-time nf-mono" aria-hidden="true">
                                        <i class="{{ $notificacao->read_at ? 'fa-regular fa-circle-check' : 'fa-solid fa-circle' }}"></i>
                                    </span>
                                    <div class="nf-timeline-body">
                                        <p class="mb-0">{{ $notificacao->title }}</p>
                                        <p class="mb-0 nf-timeline-text">
                                            {{ Formatters::dateTime($notificacao->created_at) }}
                                            @if (! $notificacao->read_at)
                                                · <span class="nf-status nf-status-waiting">sem leitura</span>
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endif
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Quem está operando" subtitle="Sessão, empresa e plano que o contexto resolveu no login.">
                <div class="d-flex flex-wrap gap-3 align-items-start">
                    <span class="nf-avatar nf-avatar-lg" aria-hidden="true">{{ $usuario->initials() }}</span>

                    <div class="flex-grow-1 nf-session">
                        <dl class="nf-dl mb-0">
                            <div>
                                <dt>Nome</dt>
                                <dd>{{ $usuario->name }}</dd>
                            </div>
                            <div>
                                <dt>E-mail</dt>
                                <dd class="nf-mono">{{ $usuario->email }}</dd>
                            </div>
                            <div>
                                <dt>Papel</dt>
                                <dd>
                                    @forelse ($usuario->roles as $papel)
                                        <span class="badge text-bg-primary">{{ $papel->name }}</span>
                                    @empty
                                        <span class="badge text-bg-secondary">Sem papel atribuído</span>
                                    @endforelse
                                </dd>
                            </div>
                            <div>
                                <dt>Empresa</dt>
                                <dd>{{ $usuario->company?->name ?? 'Sem empresa vinculada' }}</dd>
                            </div>
                            @if ($usuario->company?->plan)
                                <div>
                                    <dt>Plano</dt>
                                    <dd>{{ $usuario->company->plan->name }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt>Último acesso</dt>
                                <dd class="nf-mono">
                                    @if ($usuario->last_login_at)
                                        {{ Formatters::dateTime($usuario->last_login_at) }}
                                        <span class="nf-text-muted-2">{{ $usuario->last_login_at->diffForHumans() }}</span>
                                    @else
                                        <span class="nf-text-muted-2">Primeiro acesso desta conta.</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <x-slot:tools>
                    <span class="nf-status nf-status-done">Conta ativa</span>
                </x-slot:tools>
            </x-ui.card>
        </div>

        @if ($painel['base'] !== [])
            <div class="col-12 col-xl">
                <x-ui.card title="Base consultada" subtitle="Contagens reais no MySQL desta empresa.">
                    <ul class="nf-fact-list mb-0">
                        @foreach ($painel['base'] as $linha)
                            <li>
                                <span>{{ $linha['label'] }}</span>
                                <strong class="nf-mono">{{ $linha['value'] }}</strong>
                            </li>
                        @endforeach
                    </ul>

                    <x-slot:footer>
                        <p class="mb-0 nf-text-muted-2">
                            Só aparece o módulo que o seu papel pode ver: um indicador sem
                            permissão não é ocultado no HTML, a consulta dele não chega a rodar.
                        </p>
                    </x-slot:footer>
                </x-ui.card>
            </div>
        @endif
    </div>
</x-layouts.app>
