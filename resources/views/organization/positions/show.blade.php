@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="$cargo->name"
    :subtitle="'Nível '.(int) $cargo->level.' · '.$vinculos->count().' '.($vinculos->count() === 1 ? 'vínculo' : 'vínculos')"
    :trilha="['Organização' => route('organization.index'), 'Cargos' => route('organization.positions.index'), $cargo->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('organization', $cargo->active ? 'active' : 'inactive') }}">
                {{ StatusCatalog::label('organization', $cargo->active ? 'active' : 'inactive') }}
            </span>
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('organization.positions.index')"
                icon="fa-solid fa-list">Voltar aos cargos</x-ui.button>

            @can('organization.update')
                <x-ui.button variant="soft-primary" size="sm" :href="route('organization.positions.edit', $cargo)"
                    icon="fa-solid fa-pen">Editar cargo</x-ui.button>
            @endcan

            @can('organization.delete')
                <x-ui.action-form :acao="route('organization.positions.destroy', $cargo)" rotulo="Excluir"
                    titulo="Excluir {{ $cargo->name }}?"
                    texto="Só é possível excluir um cargo que não aparece em nenhum vínculo." />
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <x-ui.card title="Cargo" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $cargo->name }}</dd>
                    </div>
                    <div>
                        <dt>Nível</dt>
                        <dd class="nf-mono">{{ (int) $cargo->level }}</dd>
                    </div>
                    <div>
                        <dt>Setor</dt>
                        <dd>
                            @if ($cargo->department)
                                <a href="{{ route('organization.departments.show', $cargo->department) }}">
                                    {{ $cargo->department->name }}
                                </a>
                            @else
                                <span class="nf-text-muted-2">Sem setor fixo</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Papel sugerido</dt>
                        <dd>{{ $cargo->suggestedRole?->name ?? 'Nenhum' }}</dd>
                    </div>
                    <div>
                        <dt>Descrição</dt>
                        <dd>{{ $cargo->description ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Cadastrado em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($cargo->created_at) }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-7">
            <x-ui.card title="Pessoas neste cargo" subtitle="Vínculos que apontam para esta função.">
                @if ($vinculos->isEmpty())
                    <x-ui.state tone="empty" title="Nenhum vínculo neste cargo"
                        text="Os vínculos aparecem quando uma passagem receber este cargo." />
                @else
                    <div class="table-responsive">
                        <table class="table nf-table align-middle mb-0">
                            <caption class="visually-hidden">Vínculos do cargo {{ $cargo->name }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Código</th>
                                    <th scope="col">Conta</th>
                                    <th scope="col">Situação</th>
                                    <th scope="col">Início</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vinculos as $vinculo)
                                    <tr>
                                        <td>
                                            <a class="nf-mono" href="{{ route('organization.employments.show', $vinculo) }}">
                                                {{ $vinculo->employee_code }}
                                            </a>
                                        </td>
                                        <td>{{ $vinculo->user?->name ?? 'Conta removida' }}</td>
                                        <td>
                                            <span class="{{ StatusCatalog::badge('employment', $vinculo->status) }}">
                                                {{ StatusCatalog::label('employment', $vinculo->status) }}
                                            </span>
                                        </td>
                                        <td class="nf-mono">{{ Formatters::date($vinculo->started_at) }}</td>
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
