@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $novaBase = new \App\Models\Address([
        'type' => 'service',
        'is_primary' => $tecnico->addresses->isEmpty(),
    ]);
@endphp

<x-layouts.app
    :title="$tecnico->name"
    :subtitle="$tecnico->region
        ? 'Ficha de campo · região '.$tecnico->region
        : 'Ficha de campo: base, especialidades, equipes e o trabalho já registrado.'"
    :trilha="['Cadastros' => route('technicians.index'), 'Técnicos' => route('technicians.index'), $tecnico->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('technician', $tecnico->status) }}">
                {{ StatusCatalog::label('technician', $tecnico->status) }}
            </span>
            @if ($tecnico->admission_date)
                <span class="nf-text-muted-2 ms-2 small">
                    na escala desde {{ Formatters::date($tecnico->admission_date) }}
                </span>
            @endif
            @if ($tecnico->trashed())
                <span class="nf-status nf-status-canceled ms-2">
                    Excluído em {{ Formatters::date($tecnico->deleted_at) }}
                </span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('technicians.index')" icon="fa-solid fa-list">
                Voltar à escala
            </x-ui.button>

            @unless ($tecnico->trashed())
                @can('technicians.update')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('technicians.edit', $tecnico)"
                        icon="fa-solid fa-pen">Editar ficha</x-ui.button>
                @endcan

                @can('technicians.delete')
                    <x-ui.action-form :acao="route('technicians.destroy', $tecnico)" rotulo="Excluir"
                        titulo="Excluir {{ $tecnico->name }}?"
                        texto="Só é possível excluir quem ainda não gerou ordem, check-in ou compromisso. Inativar preserva o histórico." />
                @endcan
            @else
                @can('technicians.delete')
                    <x-ui.action-form :acao="route('technicians.restore', $tecnico)" metodo="PATCH" rotulo="Restaurar"
                        titulo="Restaurar {{ $tecnico->name }}?" texto="O técnico volta à escala e pode receber ordem de novo."
                        icon="fa-solid fa-rotate-left" :perigo="false" variante="soft-primary" />
                @endcan
            @endunless
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Ficha" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $tecnico->name }}</dd>
                    </div>
                    <div>
                        <dt>Documento</dt>
                        <dd class="nf-mono">{{ $tecnico->document ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Telefone</dt>
                        <dd class="nf-mono">{{ $tecnico->phone ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>E-mail</dt>
                        <dd>{{ $tecnico->email ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Região</dt>
                        <dd>{{ $tecnico->region ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Conta de acesso</dt>
                        <dd>
                            @if ($tecnico->user)
                                {{ $tecnico->user->name }} · <span class="nf-mono small">{{ $tecnico->user->email }}</span>
                            @else
                                <span class="nf-text-muted-2">Sem conta: este técnico não entra no sistema</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Cadastrado em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($tecnico->created_at) }}</dd>
                    </div>
                </dl>

                @if ($tecnico->notes)
                    <div class="nf-form-secao">
                        <h2>Observações internas</h2>
                        <p class="mb-0 nf-pre-line">{{ $tecnico->notes }}</p>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="O que este técnico já gerou" subtitle="Contagem contada no banco, agora.">
                <ul class="nf-fact-list mb-0">
                    @foreach ($historico as $linha)
                        <li>
                            <span>{{ $linha['total'] === 1 ? $linha['singular'] : $linha['plural'] }}</span>
                            <strong class="nf-mono">{{ $linha['total'] }}</strong>
                        </li>
                    @endforeach
                </ul>

                @if (array_sum(array_column($historico, 'total')) > 0)
                    <p class="nf-text-muted-2 small mb-0 mt-2">
                        Enquanto houver qualquer um destes registros, a ficha não pode ser excluída — inative o técnico.
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card title="Especialidades" subtitle="O que a ficha diz que ele resolve." class="mt-3">
                @if ($especialidades === [])
                    <x-ui.state tone="empty" title="Nenhuma especialidade marcada"
                        text="A ordem de serviço pode ser distribuída, mas o filtro por competência não vai encontrar este técnico." />
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($especialidades as $nome)
                            <li class="nf-item-linha">
                                <p class="mb-0">{{ $nome }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <x-ui.card title="Equipes" subtitle="Quadro com a data de entrada e de saída.">
                @if ($equipes->isEmpty())
                    <x-ui.state tone="empty" title="Nenhum vínculo de equipe"
                        text="Equipes organizam a região; quem não está em nenhuma recebe ordem direto da central." />
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($equipes as $equipe)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $equipe->name }}</p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        desde <span class="nf-mono">{{ Formatters::date($equipe->pivot->joined_at) }}</span>
                                        @if ($equipe->pivot->left_at)
                                            · saiu em {{ Formatters::date($equipe->pivot->left_at) }}
                                        @endif
                                    </p>
                                </div>

                                <x-ui.button variant="ghost" size="sm" :href="route('teams.show', $equipe)"
                                    icon="fa-solid fa-arrow-up-right-from-square">Abrir</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-6">
            <x-ui.card title="Base de trabalho" subtitle="Endereço com coordenadas: a referência do check-in.">
                @if ($tecnico->addresses->isEmpty())
                    <x-ui.state tone="empty" title="Nenhuma base cadastrada"
                        text="Sem base, a rota até o cliente é calculada do último check-in, não de um endereço fixo." />
                @else
                    <ul class="nf-itens mb-3">
                        @foreach ($tecnico->addresses as $end)
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

                                @can('technicians.update')
                                    @if ($enderecoEmEdicao?->is($end))
                                        <x-ui.button variant="ghost" size="sm" :href="route('technicians.show', $tecnico)"
                                            icon="fa-solid fa-xmark">Fechar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('technicians.show', ['tecnico' => $tecnico, 'editar_endereco' => $end->id])"
                                            icon="fa-solid fa-pen">Editar</x-ui.button>
                                    @endif
                                @endcan

                                @can('technicians.delete')
                                    <x-ui.action-form :acao="route('technicians.addresses.destroy', [$tecnico, $end])"
                                        rotulo="Remover" titulo="Remover esta base?"
                                        texto="{{ $end->city }}/{{ $end->state }} sai da ficha do técnico." />
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('technicians.update')
                    @if ($enderecoEmEdicao)
                        <div class="nf-form-secao">
                            <h2>Editar base</h2>
                            <form method="POST"
                                action="{{ route('technicians.addresses.update', [$tecnico, $enderecoEmEdicao]) }}"
                                data-nf-guard novalidate>
                                @method('PUT')
                                <x-ui.address-fields :endereco="$enderecoEmEdicao" :tipos="$tiposDeEndereco" />
                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="primary" size="sm"
                                        icon="fa-solid fa-floppy-disk" data-loading="false">Salvar base</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" :href="route('technicians.show', $tecnico)">
                                        Cancelar
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="nf-form-secao">
                            <h2>Nova base</h2>
                            <form method="POST" action="{{ route('technicians.addresses.store', $tecnico) }}"
                                data-nf-guard novalidate>
                                <x-ui.address-fields :endereco="$novaBase" :tipos="$tiposDeEndereco" />
                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="soft-primary" size="sm" icon="fa-solid fa-plus"
                                        data-loading="false">Adicionar base</x-ui.button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endcan
            </x-ui.card>
        </div>
    </div>

    @if ($localizacoes->isNotEmpty())
        <div class="row g-3 mt-1">
            <div class="col-12">
                <x-ui.card title="Últimos check-ins" subtitle="Coordenadas medidas no aparelho do técnico, não digitadas aqui.">
                    <div class="table-responsive">
                        <table class="table nf-table align-middle mb-0">
                            <caption class="visually-hidden">Cinco últimos registros de presença deste técnico</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Chegada</th>
                                    <th scope="col">Saída</th>
                                    <th scope="col">Coordenadas da chegada</th>
                                    <th scope="col">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($localizacoes as $visita)
                                    <tr>
                                        <td class="nf-mono">{{ Formatters::dateTime($visita->checkin_at) }}</td>
                                        <td class="nf-mono">{{ $visita->checkout_at ? Formatters::dateTime($visita->checkout_at) : Formatters::TIME_NULL }}</td>
                                        <td class="nf-mono">
                                            {{ Formatters::decimal($visita->checkin_latitude, 6) }},
                                            {{ Formatters::decimal($visita->checkin_longitude, 6) }}
                                        </td>
                                        <td>
                                            <span class="{{ StatusCatalog::badge('checkin', $visita->status) }}">
                                                {{ StatusCatalog::label('checkin', $visita->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>
    @endif
</x-layouts.app>
