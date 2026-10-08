@use('App\Support\StatusCatalog')

@php
    $editando = $equipe->exists;
    $noQuadro = $editando
        ? $equipe->technicians->whereNull('pivot.left_at')->pluck('id')->all()
        : [];
@endphp

<x-layouts.app
    :title="$editando ? 'Editar equipe' : 'Nova equipe'"
    :subtitle="$editando
        ? 'Ajuste '.$equipe->name.'. Tirar alguém do quadro registra a saída, não apaga a passagem.'
        : 'Um nome, uma região e os técnicos que trabalham juntos.'"
    :trilha="['Cadastros' => route('teams.index'), 'Equipes' => route('teams.index'), ($editando ? 'Editar' : 'Nova') => null]"
>
    <x-ui.card>
        <form method="POST" action="{{ $editando ? route('teams.update', $equipe) : route('teams.store') }}"
            data-nf-guard novalidate>
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome da equipe" name="name" :value="$equipe->name"
                        placeholder="Equipe Zona Norte, Operações Litoral..." required
                        hint="Único dentro desta empresa." />

                    <x-ui.input label="Região" name="region" :value="$equipe->region"
                        placeholder="Onde ela roda" />

                    <x-ui.select label="Líder" name="leader_id" :opcoes="$tecnicos" :value="$equipe->leader_id"
                        placeholder="Sem líder definido" />

                    <x-ui.select label="Situação" name="status" :opcoes="$situacoes" :value="$equipe->status" required />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Quadro de técnicos</h2>

                @if ($tecnicos === [])
                    <p class="nf-text-muted-2 mb-0">
                        Nenhum técnico cadastrado.
                        @can('technicians.create')
                            <a href="{{ route('technicians.create') }}">Cadastre o primeiro</a> para montar a equipe.
                        @endcan
                    </p>
                @else
                    <p class="nf-text-muted-2 small">
                        Desmarcar alguém não apaga a passagem pela equipe: fica registrado o dia da saída.
                    </p>

                    <div class="nf-check-grade">
                        @foreach ($tecnicos as $id => $nome)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="membros[]"
                                    id="membro{{ $id }}" value="{{ $id }}"
                                    @checked(in_array($id, old('membros', $noQuadro), false))>
                                <label class="form-check-label" for="membro{{ $id }}">{{ $nome }}</label>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar equipe' : 'Cadastrar equipe' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('teams.show', $equipe) : route('teams.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
