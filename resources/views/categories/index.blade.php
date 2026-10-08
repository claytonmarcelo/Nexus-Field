@use('App\Support\Formatters')

<x-layouts.app
    title="Categorias de serviço"
    subtitle="O grupo que ordena o catálogo: é por ele que a listagem de serviços filtra e o operador reconhece o trabalho."
    :trilha="['Catálogo' => null, 'Categorias de serviço' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $categorias->total() }} {{ $categorias->total() === 1 ? 'categoria cadastrada' : 'categorias cadastradas' }} · {{ $totalServicos }} {{ $totalServicos === 1 ? 'serviço no catálogo' : 'serviços no catálogo' }}
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('services.index')" icon="fa-solid fa-screwdriver-wrench">
                Ver serviços
            </x-ui.button>
        </div>
    </div>

    @can('services.view')
        <x-ui.filters :rota="route('categories.index')" :limpar="route('categories.index')">
            <div class="nf-filters-largo">
                <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                    placeholder="Nome ou slug da categoria" data-nf-busca />
            </div>
        </x-ui.filters>
    @endcan

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Cadastro de categorias" subtitle="Quantos serviços há em cada grupo é contado no banco.">
                @if ($categorias->isEmpty())
                    <x-ui.state tone="empty"
                        :title="$categorias->total() === 0 ? 'Nenhuma categoria cadastrada' : 'Nada com esta busca'"
                        text="Categoria é agrupamento, não regra de preço: refrigeração, automação, gás. Sem ela, o catálogo é uma lista só." />
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($categorias as $categoria)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $categoria->name }}</p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        <span class="nf-mono">{{ $categoria->services_count }}</span>
                                        {{ $categoria->services_count === 1 ? 'serviço' : 'serviços' }}
                                        · slug <span class="nf-mono">{{ $categoria->slug }}</span>
                                        · criada em {{ Formatters::date($categoria->created_at) }}
                                    </p>
                                </div>

                                @can('services.update')
                                    @if ($emEdicao?->is($categoria))
                                        <x-ui.button variant="ghost" size="sm" :href="route('categories.index')"
                                            icon="fa-solid fa-xmark">Fechar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('categories.index', ['editar_categoria' => $categoria->id])"
                                            icon="fa-solid fa-pen">Editar</x-ui.button>
                                    @endif
                                @endcan

                                @can('services.delete')
                                    <x-ui.action-form :acao="route('categories.destroy', $categoria)"
                                        rotulo="Excluir" titulo="Excluir {{ $categoria->name }}?"
                                        texto="Grupo com serviço dentro não sai: troque a categoria dos serviços antes." />
                                @endcan
                            </li>
                        @endforeach
                    </ul>

                    <x-ui.pagination :paginador="$categorias" rotulo="categorias" />
                @endif
            </x-ui.card>
        </div>

        @can('services.create')
            <div class="col-12 col-xl-5">
                <x-ui.card :title="$emEdicao ? 'Editar '.$emEdicao->name : 'Nova categoria'"
                    subtitle="O slug nasce do nome e é único dentro da sua empresa.">
                    <form method="POST" action="{{ $emEdicao
                        ? route('categories.update', $emEdicao)
                        : route('categories.store') }}" data-nf-guard novalidate>
                        @csrf
                        @if ($emEdicao)
                            @method('PUT')
                        @endif

                        <x-ui.input label="Nome" name="name" :value="$emEdicao?->name"
                            placeholder="Refrigeração e ar-condicionado" required />

                        <div class="nf-form-acoes">
                            <x-ui.button type="submit" variant="primary" size="sm"
                                icon="{{ $emEdicao ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                                {{ $emEdicao ? 'Salvar categoria' : 'Cadastrar categoria' }}
                            </x-ui.button>

                            @if ($emEdicao)
                                <x-ui.button variant="ghost" size="sm" :href="route('categories.index')">
                                    Cancelar
                                </x-ui.button>
                            @endif
                        </div>
                    </form>

                    @if ($emEdicao)
                        <p class="nf-text-muted-2 small mb-0 mt-2">
                            Criada em {{ Formatters::dateTime($emEdicao->created_at) }}.
                        </p>
                    @endif
                </x-ui.card>
            </div>
        @endcan
    </div>
</x-layouts.app>
