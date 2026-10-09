@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="$equipe->name"
    :subtitle="$equipe->region
        ? 'Equipe da região '.$equipe->region.' · '.$quadro->count().' no quadro'
        : 'Quadro da equipe e o que ela tem em aberto.'"
    :trilha="['Cadastros' => route('teams.index'), 'Equipes' => route('teams.index'), $equipe->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('team', $equipe->status) }}">
                {{ StatusCatalog::label('team', $equipe->status) }}
            </span>
            <span class="nf-text-muted-2 ms-2 small">
                {{ $quadro->count() }} no quadro · {{ $saidas->count() }} com saída registrada
            </span>
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('teams.index')" icon="fa-solid fa-list">
                Voltar às equipes
            </x-ui.button>

            @can('teams.update')
                <x-ui.button variant="soft-primary" size="sm" :href="route('teams.edit', $equipe)"
                    icon="fa-solid fa-pen">Editar equipe</x-ui.button>
            @endcan

            @can('teams.delete')
                <x-ui.action-form :acao="route('teams.destroy', $equipe)" rotulo="Excluir"
                    titulo="Excluir {{ $equipe->name }}?"
                    texto="Só é possível excluir equipe com o quadro vazio. Inativar preserva o histórico." />
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <x-ui.card title="Equipe" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $equipe->name }}</dd>
                    </div>
                    <div>
                        <dt>Líder</dt>
                        <dd>
                            @if ($lider)
                                <a href="{{ route('technicians.show', $lider) }}">{{ $lider->name }}</a>
                            @else
                                <span class="nf-text-muted-2">Sem líder</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Região</dt>
                        <dd>{{ $equipe->region ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Montada em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($equipe->created_at) }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-7">
            <x-ui.card title="Quadro atual" subtitle="Entrada registrada; quem sai continua na história da equipe.">
                @if ($quadro->isEmpty())
                    <x-ui.state tone="empty" title="Equipe sem técnicos"
                        text="Adicione quem trabalha nesta região para a distribuição considerar a equipe." />
                @else
                    <ul class="nf-itens mb-3">
                        @foreach ($quadro as $membro)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        <a href="{{ route('technicians.show', $membro) }}">{{ $membro->name }}</a>
                                        @if ($lider && $membro->is($lider))
                                            <span class="nf-status nf-status-done">líder</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        desde <span class="nf-mono">{{ Formatters::date($membro->pivot->joined_at) }}</span>
                                        · {{ StatusCatalog::label('technician', $membro->status) }}
                                        @if ($membro->region)
                                            · {{ $membro->region }}
                                        @endif
                                    </p>
                                </div>

                                @can('teams.update')
                                    <x-ui.button variant="ghost" size="sm" :href="route('technicians.edit', $membro)"
                                        icon="fa-solid fa-pen">Editar ficha</x-ui.button>

                                    <x-ui.action-form :acao="route('teams.members.destroy', [$equipe, $membro])"
                                        rotulo="Sair da equipe" icon="fa-solid fa-user-minus"
                                        titulo="Registrar a saída de {{ $membro->name }}?"
                                        texto="A linha do quadro fica com a data de hoje; o histórico de ordens não muda." />
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('teams.update')
                    @if ($entradas !== [])
                        <div class="nf-form-secao">
                            <h2>Adicionar ao quadro</h2>
                            <form method="POST" action="{{ route('teams.members.store', $equipe) }}" data-nf-guard novalidate>
                                @csrf
                                <div class="nf-form-grade">
                                    <x-ui.select label="Técnico" name="tecnico_id" :opcoes="$entradas"
                                        placeholder="Escolha quem entra" required />
                                </div>

                                <div class="nf-form-acoes">
                                    <x-ui.button type="submit" variant="soft-primary" size="sm" icon="fa-solid fa-plus"
                                        data-loading="false">Adicionar</x-ui.button>
                                </div>
                            </form>
                        </div>
                    @else
                        <p class="nf-text-muted-2 small mb-0">
                            Todo técnico ativo da empresa já está neste quadro.
                        </p>
                    @endif
                @endcan
            </x-ui.card>
        </div>
    </div>

    @if ($saidas->isNotEmpty())
        <div class="row g-3 mt-1">
            <div class="col-12">
                <x-ui.card title="Quem já passou por aqui" subtitle="Saída registrada, nunca apagamento.">
                    <ul class="nf-itens mb-0">
                        @foreach ($saidas as $membro)
                            <li class="nf-item-linha">
                                <div>
                                    <p class="mb-0">{{ $membro->name }}</p>
                                    <p class="mb-0 nf-text-muted-2 small nf-mono">
                                        {{ Formatters::date($membro->pivot->joined_at) }} →
                                        {{ Formatters::date($membro->pivot->left_at) }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            </div>
        </div>
    @endif

    <div class="row g-3 mt-1">
        <div class="col-12">
            <x-ui.card title="O que a equipe tem em aberto" subtitle="Ordens paradas nas mãos de quem está no quadro.">
                @if ($ordens === [])
                    <x-ui.state tone="empty" title="Nada em aberto"
                        text="Nenhuma ordem aberta, em execução ou em espera passou por este quadro hoje." />
                @else
                    <div class="table-responsive">
                        <table class="table nf-table align-middle mb-0">
                            <caption class="visually-hidden">Ordens em aberto que pertencem a técnicos desta equipe</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Número</th>
                                    <th scope="col">Título</th>
                                    <th scope="col">Técnico</th>
                                    <th scope="col" class="text-end">Abrir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ordens as $ordem)
                                    <tr>
                                        <td class="nf-mono">{{ $ordem->number }}</td>
                                        <td>{{ $ordem->title }}</td>
                                        <td>{{ $ordem->tecnico }}</td>
                                        <td class="text-end">
                                            @can('orders.view')
                                                <x-ui.button variant="ghost" size="sm"
                                                    :href="route('orders.show', $ordem->id)" icon="fa-solid fa-eye">
                                                    Ordem
                                                </x-ui.button>
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
