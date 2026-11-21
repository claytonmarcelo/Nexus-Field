@php($editando = $cargo->exists)

<x-layouts.app
    :title="$editando ? 'Editar cargo' : 'Novo cargo'"
    :subtitle="$editando
        ? 'Ajuste '.$cargo->name.'. O papel sugerido nunca concede acesso por conta própria.'
        : 'Um cargo descreve a função; o papel continua sendo concedido na conta.'"
    :trilha="['Organização' => route('organization.index'), 'Cargos' => route('organization.positions.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST"
            action="{{ $editando ? route('organization.positions.update', $cargo) : route('organization.positions.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome do cargo" name="name" :value="old('name', $cargo->name)"
                        placeholder="Técnico de campo, Coordenador de operações..." required
                        hint="Único dentro do setor nesta empresa." />

                    <x-ui.input label="Nível" name="level" type="number" min="1" max="9"
                        :value="old('level', $cargo->level ?? 5)" required />

                    <x-ui.select label="Setor" name="department_id" :opcoes="$setores"
                        :value="old('department_id', $cargo->department_id)"
                        placeholder="Cargo sem setor fixo" />

                    <x-ui.select label="Papel sugerido" name="suggested_role_id" :opcoes="$papeis"
                        :value="old('suggested_role_id', $cargo->suggested_role_id)"
                        placeholder="Nenhum papel sugerido" />

                    <x-ui.textarea label="Descrição" name="description" :value="old('description', $cargo->description)"
                        placeholder="Responsabilidades do cargo" rows="3" />
                </div>

                <p class="nf-text-muted-2 small mb-0">
                    O papel sugerido é orientação para quem concede acesso. Para dar permissão de verdade, edite o papel
                    da conta — este campo nunca altera a autorização.
                </p>
            </div>

            <x-ui.switch label="Cargo ativo" name="active" :checked="old('active', $cargo->active)" />

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar cargo' : 'Cadastrar cargo' }}
                </x-ui.button>

                <x-ui.button variant="ghost"
                    :href="$editando ? route('organization.positions.show', $cargo) : route('organization.positions.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
