@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $endereco = $cliente->enderecoPrincipal();
    $novoContato = new \App\Models\ClientContact(['is_primary' => $cliente->contacts->isEmpty()]);
    $novoEndereco = new \App\Models\Address([
        'type' => 'service',
        'is_primary' => $cliente->addresses->isEmpty(),
    ]);
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

        <div class="col-12 col-xl-5">
            <x-ui.card title="O que este cliente já gerou" subtitle="Contagem contada no banco, agora.">
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
                        Enquanto houver qualquer um destes registros, o cliente não pode ser excluído — inative-o.
                    </p>
                @endif
            </x-ui.card>
        </div>
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
</x-layouts.app>
