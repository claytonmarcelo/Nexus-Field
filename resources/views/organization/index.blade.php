@use('App\Support\StatusCatalog')

<x-layouts.app
    title="Organização"
    subtitle="Filial, setor, cargo e vínculo lidos do banco no mesmo alcance das ordens e da folha de contas."
    :trilha="['Organização' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            Estrutura desta empresa · {{ $totais['filiais'] }} filiais · {{ $totais['setores'] }} setores · {{ $totais['pessoas'] }} pessoas
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('organization.branches.index')" icon="fa-solid fa-building">
                Filiais
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('organization.departments.index')" icon="fa-solid fa-sitemap">
                Setores
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('organization.positions.index')" icon="fa-solid fa-id-badge">
                Cargos
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('organization.employments.index')" icon="fa-solid fa-users">
                Vínculos
            </x-ui.button>
        </div>
    </div>

    @if ($recortado)
        <x-ui.card>
            <x-ui.state tone="empty" title="Organograma recortado pelo seu alcance"
                text="Você enxerga apenas as pessoas do seu setor, filial, equipe ou subordinados. A estrutura cadastrada continua a mesma para a empresa." />
        </x-ui.card>
    @endif

    @if ($filiais->isEmpty())
        <x-ui.card title="Nada para mostrar">
            <x-ui.state tone="empty" title="Nenhuma filial ativa"
                text="Cadastre a matriz para começar a estrutura organizacional." />
        </x-ui.card>
    @else
        @foreach ($filiais as $filial)
            <x-ui.card
                :title="$filial->name"
                :subtitle="StatusCatalog::label('branch_type', $filial->type).' · '.$filial->departments->count().' setores'"
            >
                @if ($filial->departments->isEmpty())
                    <x-ui.state tone="empty" title="Filial sem setores"
                        text="Os setores desta filial aparecem aqui assim que forem cadastrados." />
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
                                        {{ $setor->pessoas_count }} {{ $setor->pessoas_count === 1 ? 'pessoa alocada' : 'pessoas alocadas' }} diretamente neste setor
                                    </p>
                                </div>

                                <x-ui.button variant="ghost" size="sm" :href="route('organization.departments.show', $setor)"
                                    icon="fa-solid fa-eye">Abrir</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    @endif
</x-layouts.app>
