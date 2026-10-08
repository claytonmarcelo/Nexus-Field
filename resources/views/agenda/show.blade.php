@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $travado = $compromisso->estaTravado();
    $minutos = $compromisso->minutos();
@endphp

<x-layouts.app
    :title="$compromisso->title"
    :subtitle="$compromisso->all_day
        ? 'Janela de dia inteiro na escala — ' . Formatters::date($compromisso->starts_at)
        : 'Janela marcada na escala — ' . Formatters::dateTime($compromisso->starts_at)"
    :trilha="['Operação' => route('agenda.index'), 'Agenda' => route('agenda.index'), 'Compromisso' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('appointment_type', $compromisso->type) }}">
                {{ StatusCatalog::label('appointment_type', $compromisso->type) }}
            </span>
            <span class="{{ StatusCatalog::badge('appointment', $compromisso->status) }}">
                {{ StatusCatalog::label('appointment', $compromisso->status) }}
            </span>
            <span class="nf-text-muted-2 small nf-mono">
                {{ $compromisso->all_day
                    ? Formatters::date($compromisso->starts_at)
                    : Formatters::time($compromisso->starts_at) . ' → ' . Formatters::time($compromisso->ends_at) }}
            </span>
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('agenda.index')" icon="fa-solid fa-calendar-days">
                Voltar à agenda
            </x-ui.button>

            @can('agenda.update')
                @if ($podeEditar)
                    <x-ui.button variant="soft-primary" size="sm" :href="route('agenda.edit', $compromisso)"
                        icon="fa-solid fa-pen">Editar</x-ui.button>
                @endif
            @endcan

            @can('agenda.delete')
                <x-ui.action-form :acao="route('agenda.destroy', $compromisso)" rotulo="Apagar"
                    icon="fa-solid fa-trash-can" variante="ghost"
                    :titulo="'Apagar o compromisso '.$compromisso->title.' da agenda?'"
                    :texto="'A janela deixa de existir. A ordem ou o chamado presos a ele continuam onde estão.'" />
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="O compromisso" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Janela</dt>
                        <dd>
                            <span class="nf-mono">
                                {{ $compromisso->all_day
                                    ? Formatters::date($compromisso->starts_at).($compromisso->ends_at->isSameDay($compromisso->starts_at) ? '' : ' → '.Formatters::date($compromisso->ends_at))
                                    : Formatters::dateTime($compromisso->starts_at).' → '.Formatters::dateTime($compromisso->ends_at) }}
                            </span>
                            <div class="nf-text-muted-2 small">
                                {{ $compromisso->all_day ? 'dia inteiro' : Formatters::duration($minutos) }}
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt>Técnico</dt>
                        <dd>
                            @if ($compromisso->technician)
                                @can('technicians.view')
                                    <a href="{{ route('technicians.show', $compromisso->technician) }}">
                                        {{ $compromisso->technician->name }}
                                    </a>
                                @else
                                    {{ $compromisso->technician->name }}
                                @endcan
                            @else
                                <span class="nf-status nf-status-waiting">sem técnico — só o escritório lê esta janela</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Cliente</dt>
                        <dd>
                            @if ($compromisso->client)
                                @can('clients.view')
                                    <a href="{{ route('clients.show', $compromisso->client) }}">{{ $compromisso->client->name }}</a>
                                @else
                                    {{ $compromisso->client->name }}
                                @endcan
                            @else
                                <span class="nf-text-muted-2">compromisso interno da operação</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Local</dt>
                        <dd>{{ $compromisso->location ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Ordem prendida</dt>
                        <dd>
                            @if ($compromisso->serviceOrder)
                                @can('orders.view')
                                    <a class="nf-mono" href="{{ route('orders.show', $compromisso->serviceOrder) }}">
                                        {{ $compromisso->serviceOrder->number }}
                                    </a>
                                    <span class="nf-text-muted-2 small">{{ $compromisso->serviceOrder->title }}</span>
                                @else
                                    <span class="nf-mono">{{ $compromisso->serviceOrder->number }}</span>
                                @endcan
                            @else
                                <span class="nf-text-muted-2">nenhuma ordem</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Chamado prendido</dt>
                        <dd>
                            @if ($compromisso->ticket)
                                @can('tickets.view')
                                    <a class="nf-mono" href="{{ route('tickets.show', $compromisso->ticket) }}">
                                        {{ $compromisso->ticket->protocol }}
                                    </a>
                                    <span class="nf-text-muted-2 small">{{ $compromisso->ticket->subject }}</span>
                                @else
                                    <span class="nf-mono">{{ $compromisso->ticket->protocol }}</span>
                                @endcan
                            @else
                                <span class="nf-text-muted-2">nenhum chamado</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Anotação</dt>
                        <dd>{{ $compromisso->description ?: Formatters::TIME_NULL }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Estado" subtitle="O fluxo decide o caminho: agendado leva a concluído ou cancelado.">
                @if ($proximosEstados === [])
                    <x-ui.state tone="no-results" title="Nenhum estado à disposição"
                        :text="$travado
                            ? 'Compromisso concluído é fato passado: a janela e o estado não se reescrevem mais.'
                            : 'Conduzir o estado da agenda é de quem marca a escala. A sua parte aqui é ler a janela.'" />
                @else
                    <form method="POST" action="{{ route('agenda.status', $compromisso) }}" data-nf-guard novalidate>
                        @csrf
                        @method('PUT')

                        <x-ui.select label="Próximo estado" name="estado" :opcoes="$proximosEstados" required
                            hint="Cancelado volta a agendado — remarcar é exatamente o que a agenda serve para fazer." />

                        <div class="nf-form-acoes">
                            <x-ui.button type="submit" variant="primary" size="sm"
                                icon="fa-solid fa-arrow-right-arrow-left" data-loading="false">
                                Aplicar estado
                            </x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="Medidas da janela" subtitle="Calculadas do que está gravado, não digitadas." class="mt-3">
                <ul class="nf-fact-list mb-0">
                    <li>
                        <span>Duração</span>
                        <strong class="nf-mono">{{ $compromisso->all_day ? 'dia inteiro' : Formatters::duration($minutos) }}</strong>
                    </li>
                    <li>
                        <span>A partir de agora</span>
                        <strong class="nf-mono">
                            {{ $compromisso->starts_at->isFuture()
                                ? 'falta '.Formatters::duration((int) now()->diffInMinutes($compromisso->starts_at, false))
                                : 'passou há '.Formatters::duration((int) $compromisso->starts_at->diffInMinutes(now())) }}
                        </strong>
                    </li>
                    <li>
                        <span>Registrado em</span>
                        <strong class="nf-mono">{{ Formatters::dateTime($compromisso->created_at) }}</strong>
                    </li>
                    <li>
                        <span>Última alteração</span>
                        <strong class="nf-mono">{{ Formatters::dateTime($compromisso->updated_at) }}</strong>
                    </li>
                </ul>

                @if ($compromisso->status === 'scheduled' && $compromisso->starts_at->isPast())
                    <p class="nf-text-muted-2 small mb-0 mt-2">
                        A janela passou e o compromisso continua agendado: é a linha que a agenda mostra
                        sem ninguém ter marcado o que aconteceu.
                    </p>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
