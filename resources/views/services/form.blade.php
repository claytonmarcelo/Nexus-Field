@use('App\Support\Formatters')

@php($editando = $servico->exists)

<x-layouts.app
    :title="$editando ? 'Editar serviço' : 'Novo serviço'"
    :subtitle="$editando
        ? 'Ajuste o que '.$servico->name.' custa e quanto tempo leva.'
        : 'Uma linha do catálogo: o nome é o que aparece na ordem de serviço e o preço é o que ela cobra.'"
    :trilha="['Catálogo' => route('services.index'), 'Serviços' => route('services.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST" :action="$editando ? route('services.update', $servico) : route('services.store')"
            data-nf-guard novalidate>
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome do serviço" name="name" :value="$servico->name"
                        placeholder="Instalação de ar-condicionado split" required />

                    <x-ui.input label="Código interno" name="code" :value="$servico->code" inputmode="text"
                        placeholder="SVC-014" hint="Opcional. É como a empresa chama o serviço na nota, na planilha, no rádio." />

                    <x-ui.select label="Categoria" name="service_category_id" :opcoes="$categorias"
                        :value="$servico->service_category_id" placeholder="Sem categoria"
                        hint="Agrupamento do catálogo; é por categoria que a listagem filtra." />

                    <x-ui.select label="Situação no catálogo" name="status" :opcoes="$situacoes" :value="$servico->status" required />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Preço e tempo</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Preço (R$)" name="price" type="number" :value="$servico->price"
                        step="0.01" min="0" inputmode="decimal" placeholder="0,00" required
                        hint="Valor unitário cobrado por este serviço." />

                    <x-ui.input label="Duração estimada (minutos)" name="estimated_minutes" type="number"
                        :value="$servico->estimated_minutes" step="1" min="1" max="14400" inputmode="numeric"
                        placeholder="90" hint="Alimenta a agenda: é com este tempo que a janela de atendimento é desenhada." />
                </div>

                @if ($editando)
                    <p class="nf-text-muted-2 small mb-0">
                        Preço hoje na tela: <span class="nf-mono">{{ Formatters::money($servico->price) }}</span> ·
                        duração <span class="nf-mono">{{ Formatters::duration($servico->estimated_minutes) }}</span>.
                        Mudar isto não altera ordens já fechadas.
                    </p>
                @endif
            </div>

            <div class="nf-form-secao">
                <h2>Descrição</h2>

                <x-ui.textarea label="O que está incluso neste serviço" name="description" :value="$servico->description"
                    rows="4" class="nf-form-largo"
                    placeholder="Escopo, material que a empresa leva, o que fica por conta do cliente..."
                    hint="Aparece para quem abre a ordem e pode ser copiada para o relatório." />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar serviço' : 'Cadastrar serviço' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('services.show', $servico) : route('services.index')">
                    Cancelar
                </x-ui.button>

                @unless ($editando)
                    <p class="nf-text-muted-2 mb-0 small">
                        O serviço entra no catálogo e já pode ser escolhido em uma ordem de serviço.
                    </p>
                @endunless
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Situação no catálogo" subtitle="O que cada escolha muda na operação.">
        <ul class="nf-fact-list mb-0">
            @foreach ($situacoes as $slug => $rotulo)
                <li>
                    <span>{{ $rotulo }}</span>
                    <strong class="nf-mono">
                        {{ match ($slug) {
                            'active' => 'aparece na escolha da ordem',
                            default => 'some da escolha, histórico intacto',
                        } }}
                    </strong>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</x-layouts.app>
