@php($editando = $vinculo->exists)

<x-layouts.app
    :title="$editando ? 'Movimentar vínculo' : 'Novo vínculo'"
    :subtitle="$editando
        ? 'Mudar filial, setor, cargo ou gestor encerra a passagem atual e abre outra. O histórico fica intacto.'
        : 'Uma conta ativa por passagem: escolha onde ela trabalha e desde quando.'"
    :trilha="['Organização' => route('organization.index'), 'Vínculos' => route('organization.employments.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST"
            action="{{ $editando ? route('organization.employments.update', $vinculo) : route('organization.employments.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Passagem</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Conta" name="user_id" :opcoes="$usuarios"
                        :value="old('user_id', $vinculo->user_id)" placeholder="Escolha uma conta" required
                        hint="{{ $editando ? 'A conta atual continua disponível para preservar a passagem.' : 'Só aparecem contas sem vínculo ativo.' }}" />

                    <x-ui.input label="Código do funcionário" name="employee_code"
                        :value="old('employee_code', $vinculo->employee_code)"
                        placeholder="FUNC-001" required hint="Único nesta empresa." />

                    <x-ui.select label="Tipo de vínculo" name="employment_type" :opcoes="$tipos"
                        :value="old('employment_type', $vinculo->employment_type ?? 'clt')" required />

                    <x-ui.select label="Situação" name="status" :opcoes="$situacoes"
                        :value="old('status', $vinculo->status ?? 'ativo')" required />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Alocação</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Filial" name="branch_id" :opcoes="$filiais"
                        :value="old('branch_id', $vinculo->branch_id)" placeholder="Sem filial definida" />

                    <x-ui.select label="Setor" name="department_id" :opcoes="$setores"
                        :value="old('department_id', $vinculo->department_id)" placeholder="Sem setor definido" />

                    <x-ui.select label="Cargo" name="position_id" :opcoes="$cargos"
                        :value="old('position_id', $vinculo->position_id)" placeholder="Sem cargo definido" />

                    <x-ui.select label="Gestor" name="manager_id" :opcoes="$gestores"
                        :value="old('manager_id', $vinculo->manager_id)" placeholder="Sem gestor definido" />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Datas</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Início" name="started_at" type="date"
                        :value="old('started_at', $vinculo->started_at?->toDateString())" required />

                    <x-ui.input label="Término" name="ended_at" type="date"
                        :value="old('ended_at', $vinculo->ended_at?->toDateString())"
                        hint="Deixe em branco para uma passagem em curso." />
                </div>

                @if ($editando)
                    <p class="nf-text-muted-2 small mb-0">
                        Mudar conta, código, tipo, filial, setor, cargo, gestor ou data de início encerra esta passagem
                        como “encerrada” e cria uma nova. Para desligar, use a ação própria na ficha.
                    </p>
                @endif
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar movimentação' : 'Cadastrar vínculo' }}
                </x-ui.button>

                <x-ui.button variant="ghost"
                    :href="$editando ? route('organization.employments.show', $vinculo) : route('organization.employments.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
