@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

<x-layouts.app
    :title="$filial->name"
    :subtitle="StatusCatalog::label('branch_type', $filial->type).' · '.$filial->departments->count().' setores · '.$vinculos->count().' vínculos'"
    :trilha="['Organização' => route('organization.index'), 'Filiais' => route('organization.branches.index'), $filial->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="{{ StatusCatalog::badge('organization', $filial->active ? 'active' : 'inactive') }}">
                {{ StatusCatalog::label('organization', $filial->active ? 'active' : 'inactive') }}
            </span>
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('organization.branches.index')"
                icon="fa-solid fa-list">Voltar às filiais</x-ui.button>

            @can('organization.update')
                <x-ui.button variant="soft-primary" size="sm" :href="route('organization.branches.edit', $filial)"
                    icon="fa-solid fa-pen">Editar filial</x-ui.button>
            @endcan

            @can('organization.delete')
                <x-ui.action-form :acao="route('organization.branches.destroy', $filial)" rotulo="Excluir"
                    titulo="Excluir {{ $filial->name }}?"
                    texto="Só é possível excluir uma unidade sem setores e sem vínculos." />
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <x-ui.card title="Unidade" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $filial->name }}</dd>
                    </div>
                    <div>
                        <dt>Identificador</dt>
                        <dd class="nf-mono">{{ $filial->slug }}</dd>
                    </div>
                    <div>
                        <dt>Tipo</dt>
                        <dd>{{ StatusCatalog::label('branch_type', $filial->type) }}</dd>
                    </div>
                    <div>
                        <dt>Gestor</dt>
                        <dd>{{ $filial->manager?->name ?? 'Sem gestor definido' }}</dd>
                    </div>
                    <div>
                        <dt>Cadastrada em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($filial->created_at) }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-7">
            <x-ui.card title="Setores" subtitle="Hierarquia cadastrada nesta unidade.">
                @if ($filial->departments->isEmpty())
                    <x-ui.state tone="empty" title="Unidade sem setores"
                        text="Os setores aparecem aqui assim que forem cadastrados." />
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($filial->departments as $setor)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        <a href="{{ route('organization.departments.show', $setor) }}">
                                            {{ str_repeat('— ', (int) $setor->depth).$setor->name }}
                                        </a>
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        {{ StatusCatalog::label('department_type', $setor->type) }}
                                    </p>
                                </div>

                                <x-ui.button variant="ghost" size="sm"
                                    :href="route('organization.departments.show', $setor)"
                                    icon="fa-solid fa-eye">Abrir</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>

    <x-ui.card class="mt-3" title="Vínculos nesta unidade" subtitle="Passagens registradas na filial.">
        @if ($vinculos->isEmpty())
            <x-ui.state tone="empty" title="Nenhum vínculo nesta unidade"
                text="Os vínculos aparecem quando uma passagem é alocada aqui." />
        @else
            <div class="table-responsive">
                <table class="table nf-table align-middle mb-0">
                    <caption class="visually-hidden">Vínculos da unidade {{ $filial->name }}</caption>
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
