@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="$servico->name"
    :subtitle="$servico->category?->name
        ? 'Serviço do grupo '.$servico->category->name.' · '.Formatters::money($servico->price)
        : 'Ficha do serviço: preço, tempo estimado e onde ele já foi usado.'"
    :trilha="['Catálogo' => route('services.index'), 'Serviços' => route('services.index'), $servico->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('catalogo', $servico->status) }}">
                {{ StatusCatalog::label('catalogo', $servico->status) }}
            </span>
            @if ($servico->trashed())
                <span class="nf-status nf-status-canceled ms-2">
                    Excluído em {{ Formatters::date($servico->deleted_at) }}
                </span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('services.index')" icon="fa-solid fa-list">
                Voltar ao catálogo
            </x-ui.button>

            @unless ($servico->trashed())
                @can('services.update')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('services.edit', $servico)"
                        icon="fa-solid fa-pen">Editar serviço</x-ui.button>
                @endcan

                @can('services.delete')
                    <x-ui.action-form :acao="route('services.destroy', $servico)" rotulo="Excluir"
                        titulo="Excluir {{ $servico->name }}?"
                        texto="Só sai do catálogo quem nunca apareceu em ordem nem foi cobrado. Inativar preserva o histórico." />
                @endcan
            @else
                @can('services.delete')
                    <x-ui.action-form :acao="route('services.restore', $servico)" metodo="PATCH" rotulo="Restaurar"
                        titulo="Restaurar {{ $servico->name }}?"
                        texto="O serviço volta ao catálogo e pode ser escolhido em novas ordens."
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
                        <dd>{{ $servico->name }}</dd>
                    </div>
                    <div>
                        <dt>Código</dt>
                        <dd class="nf-mono">{{ $servico->code ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Categoria</dt>
                        <dd>
                            @if ($servico->category)
                                <a href="{{ route('categories.index', ['busca' => $servico->category->name]) }}">
                                    {{ $servico->category->name }}
                                </a>
                            @else
                                <span class="nf-text-muted-2">Sem categoria</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Preço</dt>
                        <dd class="nf-mono">{{ Formatters::money($servico->price) }}</dd>
                    </div>
                    <div>
                        <dt>Duração estimada</dt>
                        <dd class="nf-mono">{{ Formatters::duration($servico->estimated_minutes) }}</dd>
                    </div>
                    <div>
                        <dt>Cadastrado em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($servico->created_at) }}</dd>
                    </div>
                </dl>

                @if ($servico->description)
                    <div class="nf-form-secao">
                        <h2>Escopo</h2>
                        <p class="mb-0 nf-pre-line">{{ $servico->description }}</p>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Onde este serviço já apareceu" subtitle="Contagem feita no banco, agora.">
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
                        Enquanto houver qualquer um destes registros, o serviço não pode ser excluído — inative-o.
                    </p>
                @endif
            </x-ui.card>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <x-ui.card title="Últimas ordens com este serviço"
                subtitle="As oito mais recentes abertas com ele, do mais novo ao mais antigo.">
                @if ($ultimasOrdens->isEmpty())
                    <x-ui.state tone="empty" title="Nenhuma ordem usa este serviço"
                        text="Assim que uma ordem de serviço for aberta com ele, as mais recentes aparecem aqui." />
                @else
                    <div class="table-responsive">
                        <table class="table nf-table align-middle mb-0">
                            <caption class="visually-hidden">Ordens de serviço abertas com este serviço</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Número</th>
                                    <th scope="col">Título</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Situação</th>
                                    <th scope="col">Aberta em</th>
                                    <th scope="col" class="text-end">Abrir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ultimasOrdens as $ordem)
                                    <tr>
                                        <td class="nf-mono">{{ $ordem->number }}</td>
                                        <td>{{ $ordem->title }}</td>
                                        <td>
                                            @if ($ordem->client)
                                                @can('clients.view')
                                                    <a href="{{ route('clients.show', $ordem->client) }}">{{ $ordem->client->name }}</a>
                                                @else
                                                    {{ $ordem->client->name }}
                                                @endcan
                                            @else
                                                <span class="nf-text-muted-2">Cliente removido</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="{{ StatusCatalog::badge('order', $ordem->status) }}">
                                                {{ StatusCatalog::label('order', $ordem->status) }}
                                            </span>
                                        </td>
                                        <td class="nf-mono">{{ Formatters::dateTime($ordem->created_at) }}</td>
                                        <td class="text-end">
                                            @can('orders.view')
                                                {{-- A rota só existe a partir da fase 12: a tela não pode
                                                     prometer um link que o aplicativo ainda não desenha. --}}
                                                @if (Route::has('orders.show'))
                                                    <x-ui.button variant="ghost" size="sm"
                                                        :href="route('orders.show', $ordem)" icon="fa-solid fa-eye">
                                                        Ordem
                                                    </x-ui.button>
                                                @else
                                                    <span class="nf-text-muted-2 small">tela de ordens na fase 12</span>
                                                @endif
                                            @else
                                                <span class="nf-text-muted-2 small">sem acesso às ordens</span>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
