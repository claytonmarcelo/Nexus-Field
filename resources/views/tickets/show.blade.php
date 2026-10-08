@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')
@use('App\Support\TextoSeguro')
@use('App\Models\Ticket')

@php
    $encerrado = $chamado->estaEncerrado();
    $prazo = $chamado->prazoResolucao();
    $vencido = $chamado->prazoVencido();
    $aberto = $chamado->opened_at;
@endphp

<x-layouts.app
    :title="$chamado->protocol.' · '.$chamado->subject"
    :subtitle="$chamado->client->name.' — o que foi relatado, quem atende e o que foi respondido.'"
    :trilha="['Operação' => route('tickets.index'), 'Chamados' => route('tickets.index'), $chamado->protocol => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="nf-mono">{{ $chamado->protocol }}</span>
            <span class="{{ StatusCatalog::badge('ticket', $chamado->status) }}">
                {{ StatusCatalog::label('ticket', $chamado->status) }}
            </span>
            <span class="{{ StatusCatalog::badge('priority', $chamado->priority) }}">
                {{ StatusCatalog::label('priority', $chamado->priority) }}
            </span>
            @if ($vencido)
                <span class="nf-status nf-status-canceled">prazo vencido</span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('tickets.index')" icon="fa-solid fa-list">
                Voltar aos chamados
            </x-ui.button>

            @can('tickets.update')
                @unless ($encerrado)
                    <x-ui.button variant="soft-primary" size="sm" :href="route('tickets.edit', $chamado)"
                        icon="fa-solid fa-pen">Editar chamado</x-ui.button>
                @endunless
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="O chamado" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Cliente</dt>
                        <dd>
                            @can('clients.view')
                                <a href="{{ route('clients.show', $chamado->client) }}">{{ $chamado->client->name }}</a>
                            @else
                                {{ $chamado->client->name }}
                            @endcan
                        </dd>
                    </div>
                    <div>
                        <dt>Categoria</dt>
                        <dd>{{ $categoria ?? \Illuminate\Support\Str::headline((string) $chamado->category) }}</dd>
                    </div>
                    <div>
                        <dt>Ordem relacionada</dt>
                        <dd>
                            @if ($chamado->serviceOrder)
                                @can('orders.view')
                                    <a class="nf-mono" href="{{ route('orders.show', $chamado->serviceOrder) }}">
                                        {{ $chamado->serviceOrder->number }}
                                    </a>
                                    <span class="nf-text-muted-2 small">{{ $chamado->serviceOrder->title }}</span>
                                @else
                                    <span class="nf-mono">{{ $chamado->serviceOrder->number }}</span>
                                @endcan
                            @else
                                <span class="nf-text-muted-2">chamado que não nasceu de uma ordem</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Técnico</dt>
                        <dd>
                            @if ($chamado->technician)
                                @can('technicians.view')
                                    <a href="{{ route('technicians.show', $chamado->technician) }}">{{ $chamado->technician->name }}</a>
                                @else
                                    {{ $chamado->technician->name }}
                                @endcan
                            @else
                                <span class="nf-status nf-status-waiting">sem técnico definido</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Responsável interno</dt>
                        <dd>{{ $chamado->responsibleUser?->name ?? Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Aberto em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($aberto) }}</dd>
                    </div>
                    <div>
                        <dt>Prazo de resolução</dt>
                        <dd>
                            <span class="nf-mono">{{ $prazo ? Formatters::dateTime($prazo) : Formatters::TIME_NULL }}</span>
                            @if ($prazo && ! $encerrado)
                                <div class="nf-text-muted-2 small">
                                    {{ $vencido
                                        ? 'venceu há '.Formatters::duration((int) $prazo->diffInMinutes(now())).' — o chamado ainda espera alguém.'
                                        : 'faltam '.Formatters::duration((int) $prazo->diffInMinutes(now())).' para resolver.' }}
                                </div>
                            @endif
                        </dd>
                    </div>
                    @if ($chamado->resolved_at)
                        <div>
                            <dt>Resolvido em</dt>
                            <dd class="nf-mono">{{ Formatters::dateTime($chamado->resolved_at) }}</dd>
                        </div>
                    @endif
                    @if ($chamado->closed_at)
                        <div>
                            <dt>Fechado em</dt>
                            <dd class="nf-mono">{{ Formatters::dateTime($chamado->closed_at) }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="nf-form-secao">
                    <h2>O que o cliente contou</h2>

                    @php
                        $relato = TextoSeguro::sanitizar($chamado->description);
                    @endphp

                    @if ($relato === null)
                        <p class="mb-0 nf-text-muted-2">
                            O relato chegou por telefone e ninguém escreveu o que foi dito. O assunto é tudo o que o
                            banco tem sobre o problema.
                        </p>
                    @else
                        <div class="nf-rico mb-0">{!! $relato !!}</div>
                    @endif
                </div>

                @if (filled($chamado->resolution_note))
                    <div class="nf-form-secao">
                        <h2>Resolução registrada</h2>
                        <p class="mb-0 nf-pre-line">{{ $chamado->resolution_note }}</p>
                        <p class="nf-text-muted-2 small mb-0 mt-1">
                            É a frase que ficou quando o chamado virou
                            {{ mb_strtolower(StatusCatalog::label('ticket', 'resolved')) }}
                            — o que o cliente lê se a ficha for aberta de novo.
                        </p>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Estado" subtitle="O fluxo decide o caminho; cada passagem fica com autor e data.">
                @if ($proximosEstados === [])
                    <x-ui.state tone="no-results" title="Nenhum estado à disposição"
                        :text="$encerrado
                            ? 'Este chamado está fechado: a conversa terminou e o que surgiu depois entra em outro protocolo.'
                            : (! $podeMover
                                ? 'Conduzir o estado do chamado é do escritório e de quem atende. A sua parte aqui é a conversa abaixo.'
                                : 'Resolver e fechar pedem a permissão de encerramento, que esta conta não tem. Peça a quem atende a mover o passo do dia a dia.')" />
                @else
                    <form method="POST" action="{{ route('tickets.status', $chamado) }}" data-nf-guard novalidate>
                        @csrf
                        @method('PUT')

                        <x-ui.select label="Próximo estado" name="estado" :opcoes="$proximosEstados" required
                            hint="Só os caminhos que este estado permite aparecerem aqui — nada de pular etapa." />

                        <x-ui.textarea label="Observação da passagem" name="nota" rows="3"
                            placeholder="O que foi feito, o que o cliente respondeu, por que está parando..."
                            hint="Obrigatória para resolver e para fechar sem resolução: é a frase que fica na ficha." />

                        <div class="nf-form-acoes">
                            <x-ui.button type="submit" variant="primary" size="sm"
                                icon="fa-solid fa-arrow-right-arrow-left" data-loading="false">
                                Aplicar estado
                            </x-ui.button>
                        </div>
                    </form>
                @endif

                @if ($chamado->statusHistory->isNotEmpty())
                    <div class="nf-form-secao">
                        <h2>Passagens registradas</h2>
                        <ol class="nf-timeline mb-0">
                            @foreach ($chamado->statusHistory as $passagem)
                                <li>
                                    <span class="nf-timeline-time">{{ Formatters::date($passagem->created_at) }}</span>
                                    <span>
                                        <span class="nf-timeline-text">
                                            {{ $passagem->from_status
                                                ? StatusCatalog::label('ticket', $passagem->from_status).' → '
                                                : 'Aberto como ' }}
                                            <span class="{{ StatusCatalog::badge('ticket', $passagem->to_status) }}">
                                                {{ StatusCatalog::label('ticket', $passagem->to_status) }}
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

            <x-ui.card title="Quanto tempo isto está em aberto" subtitle="Medido no banco, a partir da abertura." class="mt-3">
                <ul class="nf-fact-list mb-0">
                    <li>
                        <span>Aberto há</span>
                        <strong class="nf-mono">
                            {{ $aberto ? Formatters::duration((int) $aberto->diffInMinutes(now())) : Formatters::TIME_NULL }}
                        </strong>
                    </li>
                    <li>
                        <span>Prioridade pede</span>
                        <strong class="nf-mono">{{ Ticket::PRAZO_HORAS[$chamado->priority] ?? Ticket::PRAZO_PADRAO }} horas</strong>
                    </li>
                    <li>
                        <span>Notas na conversa</span>
                        <strong class="nf-mono">{{ $notas->count() }}</strong>
                    </li>
                </ul>

                @if ($vencido)
                    <p class="nf-text-muted-2 small mb-0 mt-2">
                        O prazo da própria prioridade passou e o chamado continua
                        {{ mb_strtolower(StatusCatalog::label('ticket', $chamado->status)) }}: é a linha que a listagem
                        mostra em filtro de atrasados.
                    </p>
                @endif
            </x-ui.card>
        </div>
    </div>

    <x-ui.card title="Conversa do chamado"
        :subtitle="$encerrado
            ? 'Chamado fechado: o que foi dito fica aqui, e o que surgiu depois é outro protocolo.'
            : ($podeInterna
                ? 'Primeiro o cliente: a nota de quem abriu o chamado vem antes da interna, e a interna fica só nesta tela.'
                : 'A conversa do seu chamado: o que o escritório respondeu e o que você respondeu de volta.')
        "
        class="mt-3">
        @if ($notas->isEmpty())
            <x-ui.state tone="empty" title="Nenhuma nota até agora"
                text="O relato é tudo o que existe até agora. A primeira resposta é o que transforma chamado em atendimento." />
        @else
            <ul class="nf-itens mb-0">
                @foreach ($notas as $nota)
                    <li class="nf-item-linha">
                        <p class="mb-0 fw-semibold">
                            {{ $nota->autor() }}
                            <span class="nf-text-muted-2 small nf-mono">{{ Formatters::dateTime($nota->created_at) }}</span>
                            @if ($nota->is_internal)
                                <span class="nf-status nf-status-waiting">interna</span>
                            @endif
                        </p>

                        @php
                            $texto = TextoSeguro::sanitizar($nota->body);
                        @endphp

                        @if ($texto === null)
                            <p class="mb-0 nf-text-muted-2 small">Nota sem texto.</p>
                        @else
                            <div class="nf-rico">{!! $texto !!}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($encerrado)
            <p class="nf-text-muted-2 small mb-0 mt-2">
                Abra outra chamada para o que apareceu depois: fechar o protocolo é o ponto em que a conversa para.
            </p>
        @else
            <div class="nf-form-secao">
                <h2>Responder</h2>

                <form method="POST" action="{{ route('tickets.comments.store', $chamado) }}" data-nf-guard novalidate>
                    @csrf
                    <x-ui.editor label="Nota" name="nota" altura="180" required
                        placeholder="O que foi feito, o que falta, o que o cliente pediu..."
                        hint="Texto formatado, limpo no servidor antes de ser gravado." />

                    @if ($podeInterna)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="interna" id="nota-interna" value="1">
                            <label class="form-check-label" for="nota-interna">Nota interna</label>
                            <p class="nf-text-muted-2 small mb-0 mt-1">
                                Não aparece para o cliente: é o recado do escritório para quem atende. Quem não tem
                                permissão de editar chamado nunca recebe esta linha na tela.
                            </p>
                        </div>
                    @endif

                    <div class="nf-form-acoes">
                        <x-ui.button type="submit" variant="primary" size="sm" icon="fa-solid fa-paper-plane"
                            data-loading="false">Gravar nota</x-ui.button>
                    </div>
                </form>
            </div>
        @endif
    </x-ui.card>
</x-layouts.app>
