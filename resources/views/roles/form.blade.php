@php
    $editando = $papel->exists;
    $selecionadas = old(
        'permissoes',
        $editando ? $papel->permissions->pluck('slug')->all() : [],
    );
    $selecionadas = (array) $selecionadas;
@endphp

<x-layouts.app
    :title="$editando ? 'Editar papel' : 'Novo papel'"
    :subtitle="$editando
        ? 'Ajuste o nome e o alcance do papel '.$papel->name.'. Contas com ele mudam de visão junto — a auditoria registra.'
        : 'Um jeito de enxergar o sistema. O nome vira slug no nascimento e não se troca: é o carimbo do papel.'"
    :trilha="['Gestão' => route('roles.index'), 'Papéis' => route('roles.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST" action="{{ $editando ? route('roles.update', $papel) : route('roles.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome do papel" name="name" :value="$papel->name"
                        placeholder="Ex.: Leitor de relatórios" required
                        :hint="$editando
                            ? 'O slug nasce do nome e fica para sempre — o deste aqui é '.$papel->slug.'.'
                            : 'O slug nasce do nome no nascimento e não se troca mais: defina com calma.'" />

                    <x-ui.textarea label="Descrição" name="descricao" :value="$papel->description" rows="2"
                        class="nf-form-largo" placeholder="Para quem é este papel e o que ele resolve no dia a dia" />
                </div>
            </div>

            @foreach ($matriz as $linha)
                <div class="nf-form-secao">
                    <h2>{{ $linha['rotulo'] }}</h2>

                    <div class="nf-check-grade">
                        @foreach ($linha['acoes'] as $acao)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissoes[]"
                                    id="per{{ $acao['slug'] }}" value="{{ $acao['slug'] }}"
                                    @checked(in_array($acao['slug'], $selecionadas, true))>
                                <label class="form-check-label" for="per{{ $acao['slug'] }}">
                                    {{ $acao['rotulo'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @error('permissoes')
                <div class="nf-form-secao">
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                </div>
            @enderror

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar papel' : 'Criar papel' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('roles.show', $papel) : route('roles.index')">
                    Cancelar
                </x-ui.button>

                <p class="nf-text-muted-2 mb-0 small">
                    Só aparecem permissões que você mesmo já tem: ninguém desenha um papel com mais alcance que o seu.
                </p>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
