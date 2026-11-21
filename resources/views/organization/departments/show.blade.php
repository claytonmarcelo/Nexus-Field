@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="$setor->name"
    :subtitle="StatusCatalog::label('department_type', $setor->type).' · nível '.(int) $setor->depth.' · '.$subarvore->count().' setores na subárvore'"
    :trilha="['Organização' => route('organization.index'), 'Setores' => route('organization.departments.index'), $setor->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('organization', $setor->active ? 'active' : 'inactive') }}">
                {{ StatusCatalog::label('organization', $setor->active ? 'active' : 'inactive') }}
            </span>
            <span class="nf-text-muted-2 ms-2 small nf-mono">{{ $setor->path }}</span>
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('organization.departments.index')"
                icon="fa-solid fa-list">Voltar aos setores</x-ui.button>

            @can('organization.create')
                <x-ui.button variant="soft-primary" size="sm" :href="route('organization.departments.create')"
                    icon="fa-solid fa-plus">Novo setor</x-ui.button>
            @endcan

            @can('organization.update')
                <x-ui.button variant="soft-primary" size="sm" :href="route('organization.departments.edit', $setor)"
                    icon="fa-solid fa-pen">Editar setor</x-ui.button>
            @endcan

            @can('organization.delete')
                <x-ui.action-form :acao="route('organization.departments.destroy', $setor)" rotulo="Excluir"
                    titulo="Excluir {{ $setor->name }}?"
                    texto="Só é possível excluir um setor vazio, sem filhos, cargos ou vínculos." />
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <x-ui.card title="Setor" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $setor->name }}</dd>
                    </div>
                    <div>
                        <dt>Identificador</dt>
                        <dd class="nf-mono">{{ $setor->slug }}</dd>
                    </div>
                    <div>
                        <dt>Filial</dt>
                        <dd>
                            @if ($setor->branch)
                                <a href="{{ route('organization.branches.show', $setor->branch) }}">{{ $setor->branch->name }}</a>
                            @else
                                <span class="nf-text-muted-2">Sem filial definida</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Setor pai</dt>
                        <dd>
                            @if ($setor->parent)
                                <a href="{{ route('organization.departments.show', $setor->parent) }}">{{ $setor->parent->name }}</a>
                            @else
                                <span class="nf-text-muted-2">Setor da raiz</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Tipo</dt>
                        <dd>{{ StatusCatalog::label('department_type', $setor->type) }}</dd>
                    </div>
                    <div>
                        <dt>Gestor</dt>
                        <dd>{{ $setor->manager?->name ?? 'Sem gestor definido' }}</dd>
                    </div>
                    <div>
                        <dt>Profundidade</dt>
                        <dd class="nf-mono">{{ (int) $setor->depth }}</dd>
                    </div>
                    <div>
                        <dt>Descrição</dt>
                        <dd>{{ $setor->description ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-7">
            <x-ui.card title="Subárvore" subtitle="Setor atual e descendentes, na ordem do caminho gravado.">
                <div class="table-responsive">
                    <table class="table nf-table align-middle mb-0">
                        <caption class="visually-hidden">Setores da subárvore de {{ $setor->name }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Setor</th>
                                <th scope="col">Tipo</th>
                                <th scope="col" class="text-end">Nível</th>
                                <th scope="col">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subarvore as $descendente)
                                <tr>
                                    <td>
                                        <a href="{{ route('organization.departments.show', $descendente) }}">
                                            {{ str_repeat('— ', (int) $descendente->depth).$descendente->name }}
                                        </a>
                                    </td>
                                    <td>{{ StatusCatalog::label('department_type', $descendente->type) }}</td>
                                    <td class="text-end nf-mono">{{ (int) $descendente->depth }}</td>
                                    <td>
                                        <span class="{{ StatusCatalog::badge('organization', $descendente->active ? 'active' : 'inactive') }}">
                                            {{ StatusCatalog::label('organization', $descendente->active ? 'active' : 'inactive') }}
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

    <x-ui.card class="mt-3" title="Cargos" subtitle="Funções cadastradas diretamente neste setor.">
        @if ($cargos->isEmpty())
            <x-ui.state tone="empty" title="Nenhum cargo neste setor"
                text="Cargos descrevem a função; o acesso vem do papel gravado na conta." />
        @else
            <div class="table-responsive">
                <table class="table nf-table align-middle mb-0">
                    <caption class="visually-hidden">Cargos do setor {{ $setor->name }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Cargo</th>
                            <th scope="col" class="text-end">Nível</th>
                            <th scope="col">Papel sugerido</th>
                            <th scope="col">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cargos as $cargo)
                            <tr>
                                <td>
                                    <a href="{{ route('organization.positions.show', $cargo) }}">{{ $cargo->name }}</a>
                                </td>
                                <td class="text-end nf-mono">{{ (int) $cargo->level }}</td>
                                <td>{{ $cargo->suggestedRole?->name ?? 'Nenhum' }}</td>
                                <td>
                                    <span class="{{ StatusCatalog::badge('organization', $cargo->active ? 'active' : 'inactive') }}">
                                        {{ StatusCatalog::label('organization', $cargo->active ? 'active' : 'inactive') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card class="mt-3" title="Vínculos" subtitle="Passagens alocadas diretamente neste setor.">
        @if ($vinculos->isEmpty())
            <x-ui.state tone="empty" title="Nenhum vínculo neste setor"
                text="As pessoas alocadas aqui aparecem assim que a passagem for registrada." />
        @else
            <div class="table-responsive">
                <table class="table nf-table align-middle mb-0">
                    <caption class="visually-hidden">Vínculos do setor {{ $setor->name }}</caption>
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
</x-layouts.app>
