@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="'Vínculo '.$vinculo->employee_code"
    :subtitle="($vinculo->user?->name ?? 'Conta removida').' · '.$historico->count().' passagens registradas nesta conta'"
    :trilha="['Organização' => route('organization.index'), 'Vínculos' => route('organization.employments.index'), $vinculo->employee_code => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('employment', $vinculo->status) }}">
                {{ StatusCatalog::label('employment', $vinculo->status) }}
            </span>
            <span class="nf-text-muted-2 ms-2 small">
                {{ StatusCatalog::label('employment_type', $vinculo->employment_type) }}
            </span>
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('organization.employments.index')"
                icon="fa-solid fa-list">Voltar aos vínculos</x-ui.button>

            @can('organization.update')
                <x-ui.button variant="soft-primary" size="sm" :href="route('organization.employments.edit', $vinculo)"
                    icon="fa-solid fa-pen">Movimentar passagem</x-ui.button>
            @endcan

            @can('organization.delete')
                @if (! in_array($vinculo->status, ['encerrado', 'desligado'], true))
                    <x-ui.action-form :acao="route('organization.employments.destroy', $vinculo)" rotulo="Encerrar"
                        icon="fa-solid fa-user-minus" :perigo="false"
                        titulo="Encerrar a passagem de {{ $vinculo->employee_code }}?"
                        texto="A linha fica marcada como desligada e permanece no histórico. Nada é apagado." />
                @endif
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <x-ui.card title="Passagem" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Código</dt>
                        <dd class="nf-mono">{{ $vinculo->employee_code }}</dd>
                    </div>
                    <div>
                        <dt>Conta</dt>
                        <dd>{{ $vinculo->user?->name ?? 'Conta removida' }}</dd>
                    </div>
                    <div>
                        <dt>Filial</dt>
                        <dd>
                            @if ($vinculo->branch)
                                <a href="{{ route('organization.branches.show', $vinculo->branch) }}">{{ $vinculo->branch->name }}</a>
                            @else
                                <span class="nf-text-muted-2">Sem filial definida</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Setor</dt>
                        <dd>
                            @if ($vinculo->department)
                                <a href="{{ route('organization.departments.show', $vinculo->department) }}">{{ $vinculo->department->name }}</a>
                            @else
                                <span class="nf-text-muted-2">Sem setor definido</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Cargo</dt>
                        <dd>
                            @if ($vinculo->position)
                                <a href="{{ route('organization.positions.show', $vinculo->position) }}">{{ $vinculo->position->name }}</a>
                            @else
                                <span class="nf-text-muted-2">Sem cargo definido</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Gestor</dt>
                        <dd>{{ $vinculo->manager?->name ?? 'Sem gestor definido' }}</dd>
                    </div>
                    <div>
                        <dt>Tipo</dt>
                        <dd>{{ StatusCatalog::label('employment_type', $vinculo->employment_type) }}</dd>
                    </div>
                    <div>
                        <dt>Início</dt>
                        <dd class="nf-mono">{{ Formatters::date($vinculo->started_at) }}</dd>
                    </div>
                    <div>
                        <dt>Término</dt>
                        <dd class="nf-mono">{{ $vinculo->ended_at ? Formatters::date($vinculo->ended_at) : '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-7">
            <x-ui.card title="Histórico da conta" subtitle="Movimentações encerram a passagem anterior; nenhuma linha é apagada.">
                <div class="table-responsive">
                    <table class="table nf-table align-middle mb-0">
                        <caption class="visually-hidden">Passagens registradas para {{ $vinculo->user?->name ?? 'esta conta' }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Filial</th>
                                <th scope="col">Setor</th>
                                <th scope="col">Cargo</th>
                                <th scope="col">Início</th>
                                <th scope="col">Término</th>
                                <th scope="col">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($historico as $passagem)
                                <tr>
                                    <td>
                                        <a class="nf-mono" href="{{ route('organization.employments.show', $passagem) }}">
                                            {{ $passagem->employee_code }}
                                        </a>
                                    </td>
                                    <td>{{ $passagem->branch?->name ?? '—' }}</td>
                                    <td>{{ $passagem->department?->name ?? '—' }}</td>
                                    <td>{{ $passagem->position?->name ?? '—' }}</td>
                                    <td class="nf-mono">{{ Formatters::date($passagem->started_at) }}</td>
                                    <td class="nf-mono">{{ $passagem->ended_at ? Formatters::date($passagem->ended_at) : '—' }}</td>
                                    <td>
                                        <span class="{{ StatusCatalog::badge('employment', $passagem->status) }}">
                                            {{ StatusCatalog::label('employment', $passagem->status) }}
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
</x-layouts.app>
