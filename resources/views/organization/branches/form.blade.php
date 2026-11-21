@php($editando = $filial->exists)

<x-layouts.app
    :title="$editando ? 'Editar filial' : 'Nova filial'"
    :subtitle="$editando
        ? 'Ajuste os dados de '.$filial->name.'. A matriz ativa é única nesta empresa.'
        : 'Uma matriz por empresa; as demais unidades são filiais.'"
    :trilha="['Organização' => route('organization.index'), 'Filiais' => route('organization.branches.index'), ($editando ? 'Editar' : 'Nova') => null]"
>
    <x-ui.card>
        <form method="POST"
            action="{{ $editando ? route('organization.branches.update', $filial) : route('organization.branches.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome da unidade" name="name" :value="old('name', $filial->name)"
                        placeholder="Matriz, Filial Norte, Centro de distribuição..." required
                        hint="O identificador usado na tela é gerado a partir deste nome." />

                    <x-ui.select label="Tipo" name="type" :opcoes="$tipos" :value="old('type', $filial->type)"
                        required />

                    <x-ui.select label="Gestor da unidade" name="manager_user_id" :opcoes="$gestores"
                        :value="old('manager_user_id', $filial->manager_user_id)"
                        placeholder="Sem gestor definido" />
                </div>

                <x-ui.switch label="Unidade ativa" name="active"
                    :checked="old('active', $filial->active)" />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar filial' : 'Cadastrar filial' }}
                </x-ui.button>

                <x-ui.button variant="ghost"
                    :href="$editando ? route('organization.branches.show', $filial) : route('organization.branches.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
