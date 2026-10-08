@use('App\Support\StatusCatalog')

@php
    $editando = $tecnico->exists;
    $selecionadas = $editando ? $tecnico->specialties->pluck('id')->all() : [];
@endphp

<x-layouts.app
    :title="$editando ? 'Editar técnico' : 'Novo técnico'"
    :subtitle="$editando
        ? 'Ajuste a ficha de '.$tecnico->name.'. Especialidades e conta de acesso mudam aqui.'
        : 'Quem vai a campo. Nome e situação bastam para distribuir a primeira ordem de serviço.'"
    :trilha="['Cadastros' => route('technicians.index'), 'Técnicos' => route('technicians.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST" :action="$editando ? route('technicians.update', $tecnico) : route('technicians.store')"
            data-nf-guard novalidate>
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome do técnico" name="name" :value="$tecnico->name"
                        placeholder="Como aparece na escala" required />

                    <x-ui.input label="Documento" name="document" :value="$tecnico->document" inputmode="numeric"
                        placeholder="CPF ou registro interno" hint="Único dentro desta empresa. Pode ficar em branco." />

                    <x-ui.select label="Situação na escala" name="status" :opcoes="$situacoes" :value="$tecnico->status" required />

                    <x-ui.input label="Região de atendimento" name="region" :value="$tecnico->region"
                        placeholder="Zona Norte, Litoral, interior..." hint="É o valor que aparece no filtro da listagem." />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Contato e admissão</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Telefone" name="phone" :value="$tecnico->phone" inputmode="tel"
                        placeholder="(11) 90000-0000" />

                    <x-ui.input label="E-mail" name="email" type="email" :value="$tecnico->email"
                        placeholder="tecnico@empresa.com.br" autocomplete="off" />

                    <x-ui.input label="Data de admissão" name="admission_date" type="date"
                        :value="$tecnico->admission_date?->format('Y-m-d')" />

                    <x-ui.select label="Conta de acesso" name="user_id" :opcoes="$contas" :value="$tecnico->user_id"
                        placeholder="Sem conta de acesso"
                        hint="A conta entra no sistema com o papel técnico e passa a ver as próprias ordens." />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Especialidades</h2>

                @if ($especialidades === [])
                    <p class="nf-text-muted-2 mb-0">
                        Nenhuma especialidade cadastrada.
                        @can('technicians.create')
                            <a href="{{ route('specialties.index') }}">Cadastre a primeira</a> para amarrar competência a técnico.
                        @endcan
                    </p>
                @else
                    <p class="nf-text-muted-2 small">Marque o que este técnico resolve. A listagem filtra por aqui.</p>

                    <div class="nf-check-grade">
                        @foreach ($especialidades as $id => $nome)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="especialidades[]"
                                    id="esp{{ $id }}" value="{{ $id }}"
                                    @checked(in_array($id, old('especialidades', $selecionadas), false))>
                                <label class="form-check-label" for="esp{{ $id }}">{{ $nome }}</label>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="nf-form-secao">
                <h2>Observações internas</h2>

                <x-ui.textarea label="Anotações" name="notes" :value="$tecnico->notes" rows="4" class="nf-form-largo"
                    placeholder="CNH, veículo, restrição de horário, idioma técnico..."
                    hint="Visível para quem tem acesso à ficha; não aparece em ordem de serviço." />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar ficha' : 'Cadastrar técnico' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('technicians.show', $tecnico) : route('technicians.index')">
                    Cancelar
                </x-ui.button>

                @if (! $editando)
                    <p class="nf-text-muted-2 mb-0 small">
                        A base de trabalho e o quadro de equipes entram depois, já dentro da ficha.
                    </p>
                @endif
            </div>
        </form>
    </x-ui.card>

    @if ($editando)
        <x-ui.card title="Situação na escala" subtitle="O que cada escolha muda na distribuição.">
            <ul class="nf-fact-list mb-0">
                @foreach ($situacoes as $slug => $rotulo)
                    <li>
                        <span>{{ $rotulo }}</span>
                        <strong class="nf-mono">
                            {{ match ($slug) {
                                'available' => 'aparece como opção de carga',
                                'busy' => 'já está em campo agora',
                                'off' => 'fora da escala do dia',
                                default => 'não recebe serviço novo',
                            } }}
                        </strong>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</x-layouts.app>
