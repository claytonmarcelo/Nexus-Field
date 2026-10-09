@use('App\Support\Formatters')

<x-layouts.app
    title="Auditoria"
    subtitle="O que cada conta fez nesta empresa, quando, de onde e o que mudou. A trilha não se edita por tela: só se consulta."
    :trilha="['Gestão' => null, 'Auditoria' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $atos->total() }} {{ $atos->total() === 1 ? 'ato registrado' : 'atos registrados' }}
            @if ($atos->total() > 0)
                · página {{ $atos->currentPage() }} de {{ max(1, $atos->lastPage()) }}
            @endif
        </p>

        <div class="nf-list-acoes">
            @can('audit.export')
                <x-ui.button variant="ghost" size="sm" :href="route('audit.export', request()->query())"
                    icon="fa-solid fa-file-csv">Exportar CSV</x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.filters :rota="route('audit.index')" :limpar="route('audit.index')">
        <div class="nf-filters-largo">
            <x-ui.input
                label="Buscar"
                name="busca"
                :value="request('busca')"
                placeholder="Quem fez, que ação, a descrição ou o IP"
                data-nf-busca
            />
        </div>

        <x-ui.select label="Sobre" name="entidade" :opcoes="$entidades" :value="request('entidade')"
            placeholder="Qualquer entidade" data-nf-autosubmit />

        <x-ui.select label="Quem" name="quem" :opcoes="$quem" :value="request('quem')"
            placeholder="Qualquer conta" data-nf-autosubmit />

        <x-ui.input label="A partir de" name="inicio" type="date" :value="request('inicio')" />

        <x-ui.input label="Até" name="fim" type="date" :value="request('fim')" />
    </x-ui.filters>

    @if ($atos->isEmpty())
        <x-ui.card title="Nada para listar">
            @if ($filtrosAtivos !== [])
                <x-ui.state tone="no-results" title="Nenhum ato com estes filtros"
                    text="Ajuste a busca, a entidade, a conta ou o período e consulte de novo.">
                    <x-ui.button variant="ghost" size="sm" :href="route('audit.index')" icon="fa-solid fa-rotate-left">
                        Limpar filtros
                    </x-ui.button>
                </x-ui.state>
            @else
                <x-ui.state tone="empty" title="A trilha desta empresa está em branco"
                    text="Ela se escreve sozinha: cada cadastro, cada mudança de estado e cada dinheiro que entra ou sai deixa carimbo aqui.">
                </x-ui.state>
            @endif
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="table-responsive">
                <table class="table nf-table nf-table-wrap align-middle mb-0">
                    <caption class="visually-hidden">Atos de auditoria desta empresa, na ordem e no filtro escolhidos</caption>
                    <thead>
                        <tr>
                            <x-ui.sort-link coluna="created_at" rotulo="Quando" padrao="created_at" />
                            <x-ui.sort-link coluna="user_name" rotulo="Quem" />
                            <x-ui.sort-link coluna="action" rotulo="Ação" />
                            <th scope="col">Sobre</th>
                            <th scope="col">Descrição</th>
                            <th scope="col">De onde</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($atos as $ato)
                            <tr>
                                <td class="nf-mono">{{ $ato->created_at ? Formatters::dateTime($ato->created_at) : '—' }}</td>
                                <td>{{ $ato->user_name ?: 'sem autor' }}</td>
                                <td><span class="nf-status nf-status-draft">{{ $ato->action }}</span></td>
                                <td>
                                    {{ \App\Models\AuditLog::rotuloEntidade($ato->entity_type) }}
                                    @if ($ato->entity_id !== null)
                                        <span class="nf-text-muted-2 nf-mono">#{{ $ato->entity_id }}</span>
                                    @endif
                                </td>
                                <td>{{ $ato->description ?: '—' }}</td>
                                <td class="nf-mono">{{ $ato->ip_address ?: '—' }}</td>
                                <td>
                                    <div class="nf-table-actions">
                                        <x-ui.button variant="ghost" size="sm" :href="route('audit.show', $ato)"
                                            icon="fa-solid fa-eye">Abrir</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginador="$atos" rotulo="atos" />
        </x-ui.card>
    @endif
</x-layouts.app>
