@use('App\Support\Formatters')

<x-layouts.app
    title="Especialidades"
    subtitle="Competência que um técnico pode ter: é por aqui que a ordem encontra o alguém certo."
    :trilha="['Cadastros' => null, 'Especialidades' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            {{ $especialidades->total() }}
            {{ $especialidades->total() === 1 ? 'especialidade cadastrada' : 'especialidades cadastradas' }}
            · {{ $totalTecnicos }} {{ $totalTecnicos === 1 ? 'técnico na escala' : 'técnicos na escala' }}
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('technicians.index')" icon="fa-solid fa-user-gear">
                Ver técnicos
            </x-ui.button>
        </div>
    </div>

    @can('technicians.view')
        <x-ui.filters :rota="route('specialties.index')" :limpar="route('specialties.index')">
            <div class="nf-filters-largo">
                <x-ui.input label="Buscar" name="busca" :value="request('busca')"
                    placeholder="Nome ou descrição da especialidade" data-nf-busca />
            </div>
        </x-ui.filters>
    @endcan

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Cadastro de especialidades" subtitle="Quantos técnicos têm cada uma é contado no banco.">
                @if ($especialidades->isEmpty())
                    <x-ui.state tone="empty"
                        :title="$especialidades->total() === 0 ? 'Nenhuma especialidade cadastrada' : 'Nada com esta busca'"
                        text="Especialidade é rótulo de competência: refrigeração, automação, gás. Sem ela, a distribuição só olha a região." />
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($especialidades as $especialidade)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $especialidade->name }}</p>
                                    <p class="mb-0 nf-text-muted-2 small">
                                        {{ $especialidade->description ?: 'Sem descrição' }}
                                        <br>
                                        <span class="nf-mono">{{ $especialidade->technicians_count }}</span>
                                        {{ $especialidade->technicians_count === 1 ? 'técnico' : 'técnicos' }}
                                        · slug <span class="nf-mono">{{ $especialidade->slug }}</span>
                                    </p>
                                </div>

                                @can('technicians.update')
                                    @if ($emEdicao?->is($especialidade))
                                        <x-ui.button variant="ghost" size="sm" :href="route('specialties.index')"
                                            icon="fa-solid fa-xmark">Fechar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm"
                                            :href="route('specialties.index', ['editar_especialidade' => $especialidade->id])"
                                            icon="fa-solid fa-pen">Editar</x-ui.button>
                                    @endif
                                @endcan

                                @can('technicians.delete')
                                    <x-ui.action-form :acao="route('specialties.destroy', $especialidade)"
                                        rotulo="Excluir" titulo="Excluir {{ $especialidade->name }}?"
                                        texto="Uma especialidade em uso não sai: retire-a das fichas antes." />
                                @endcan
                            </li>
                        @endforeach
                    </ul>

                    <x-ui.pagination :paginador="$especialidades" rotulo="especialidades" />
                @endif
            </x-ui.card>
        </div>

        @can('technicians.create')
            <div class="col-12 col-xl-5">
                <x-ui.card :title="$emEdicao ? 'Editar '.$emEdicao->name : 'Nova especialidade'"
                    subtitle="O nome é único por empresa e o slug nasce dele.">
                    <form method="POST" :action="$emEdicao
                        ? route('specialties.update', $emEdicao)
                        : route('specialties.store')" data-nf-guard novalidate>
                        @if ($emEdicao)
                            @method('PUT')
                        @endif

                        <x-ui.input label="Nome" name="name" :value="$emEdicao?->name"
                            placeholder="Refrigeração e ar-condicionado" required />

                        <x-ui.textarea label="Descrição" name="description" :value="$emEdicao?->description" rows="3"
                            placeholder="O que entra nesta competência, na prática." />

                        <div class="nf-form-acoes">
                            <x-ui.button type="submit" variant="primary" size="sm"
                                icon="{{ $emEdicao ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                                {{ $emEdicao ? 'Salvar especialidade' : 'Cadastrar especialidade' }}
                            </x-ui.button>

                            @if ($emEdicao)
                                <x-ui.button variant="ghost" size="sm" :href="route('specialties.index')">
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
