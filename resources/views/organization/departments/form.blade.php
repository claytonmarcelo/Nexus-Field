@php($editando = $setor->exists)

<x-layouts.app
    :title="$editando ? 'Editar setor' : 'Novo setor'"
    :subtitle="$editando
        ? 'Ajuste '.$setor->name.'. Mover o setor recalcula o caminho de toda a subárvore.'
        : 'Setores podem ter pais; a hierarquia é limitada por profundidade.'"
    :trilha="['Organização' => route('organization.index'), 'Setores' => route('organization.departments.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST"
            action="{{ $editando ? route('organization.departments.update', $setor) : route('organization.departments.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome do setor" name="name" :value="old('name', $setor->name)"
                        placeholder="Atendimento, Logística, Tecnologia..." required
                        hint="O identificador usado na tela é gerado a partir deste nome." />

                    <x-ui.select label="Tipo" name="type" :opcoes="$tipos" :value="old('type', $setor->type)"
                        required />

                    <x-ui.textarea label="Descrição" name="description" :value="old('description', $setor->description)"
                        placeholder="O que este setor faz" rows="3" />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Lugar na estrutura</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Filial" name="branch_id" :opcoes="$filiais"
                        :value="old('branch_id', $setor->branch_id)"
                        placeholder="Sem filial definida" />

                    <x-ui.select label="Setor pai" name="parent_id" :opcoes="$pais"
                        :value="old('parent_id', $setor->parent_id)"
                        placeholder="Setor da raiz" />

                    <x-ui.select label="Gestor do setor" name="manager_user_id" :opcoes="$gestores"
                        :value="old('manager_user_id', $setor->manager_user_id)"
                        placeholder="Sem gestor definido" />
                </div>

                <p class="nf-text-muted-2 small mb-0">
                    Um setor não pode ser pai de si mesmo nem virar filho de um descendente. O sistema barra ciclos e
                    limita a hierarquia a {{ \App\Models\Department::PROFUNDIDADE_MAXIMA }} níveis.
                </p>
            </div>

            <x-ui.switch label="Setor ativo" name="active" :checked="old('active', $setor->active)" />

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar setor' : 'Cadastrar setor' }}
                </x-ui.button>

                <x-ui.button variant="ghost"
                    :href="$editando ? route('organization.departments.show', $setor) : route('organization.departments.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
