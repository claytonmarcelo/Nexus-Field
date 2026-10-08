@use('App\Support\StatusCatalog')
@use('App\Models\Ticket')

@php
    $editando = $chamado->exists;

    // A regra do prazo inteira na tela, e não só a prioridade escolhida: quem abre
    // a chamada precisa saber o que cada nível vale antes de marcar urgente.
    $prazos = collect(Ticket::PRAZO_HORAS)
        ->map(fn (int $horas, string $prioridade) => StatusCatalog::label('priority', $prioridade).' '.$horas.'h')
        ->implode(' · ');
@endphp

<x-layouts.app
    :title="$editando ? 'Editar chamado '.$chamado->protocol : 'Nova chamada'"
    :subtitle="$editando
        ? 'Ajuste o relato e para quem ele vai. O estado do chamado não muda aqui: é botão da ficha.'
        : 'O que o cliente relatou, em que categoria cai e com que urgência. O protocolo o chamado recebe sozinho, na sequência do ano desta empresa.'"
    :trilha="['Operação' => route('tickets.index'), 'Chamados' => route('tickets.index'), ($editando ? 'Editar '.$chamado->protocol : 'Nova chamada') => null]"
>
    <x-ui.card>
        <form method="POST" action="{{ $editando ? route('tickets.update', $chamado) : route('tickets.store') }}"
            data-nf-guard novalidate>
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Quem chamou</h2>

                @if ($contaCliente)
                    <div class="nf-form-largo">
                        <p class="nf-text-muted-2 small mb-0">
                            Este chamado sai da carteira <strong>{{ optional($chamado->client)->name ?? 'da sua conta' }}</strong>:
                            o cliente é escrito pelo servidor, a partir da sessão, e não por um campo deste formulário.
                        </p>
                    </div>
                @else
                    <div class="nf-form-grade">
                        <x-ui.select label="Cliente" name="client_id" :opcoes="$clientes" :value="$chamado->client_id"
                            required hint="Só os cadastros desta empresa aparecem na lista." />

                        <x-ui.select label="Ordem de serviço relacionada" name="service_order_id" :opcoes="$ordens"
                            :value="$chamado->service_order_id" placeholder="Chamado que não é de uma ordem"
                            hint="Serve de contexto: o que o cliente relata depois de um serviço costuma nascer preso a ele." />
                    </div>
                @endif
            </div>

            <div class="nf-form-secao">
                <h2>O relato</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Categoria" name="category" :opcoes="$categorias" :value="$chamado->category"
                        placeholder="Escolha a categoria" required
                        hint="São as categorias de serviço desta empresa — o mesmo vocabulário do catálogo." />

                    <x-ui.select label="Prioridade" name="priority" :opcoes="$prioridades" :value="$chamado->priority"
                        required :hint="'Define o prazo de resolução, a contar da abertura: '.$prazos.' O prazo é calculado, não digitado.'" />
                </div>

                <div class="nf-form-largo">
                    <x-ui.input label="Assunto" name="subject" :value="$chamado->subject"
                        placeholder="Câmara fria apitando alarme de porta" required />
                </div>

                <x-ui.editor label="O que o cliente contou" name="description" :value="$chamado->description"
                    altura="260"
                    placeholder="Sintoma, quando começou, o que já foi tentado, qual o equipamento..."
                    hint="Texto formatado: negrito, listas e link. O que passar daqui é limpo no servidor antes de ser gravado." />
            </div>

            @unless ($contaCliente)
                <div class="nf-form-secao">
                    <h2>Quem atende</h2>

                    <div class="nf-form-grade">
                        <x-ui.select label="Técnico" name="technician_id" :opcoes="$tecnicos"
                            :value="$chamado->technician_id" placeholder="Ainda sem técnico"
                            hint="Quem vai ao local. O chamado também aparece na ficha dele quando ele é apontado aqui." />

                        <x-ui.select label="Responsável interno" name="responsible_user_id" :opcoes="$responsaveis"
                            :value="$chamado->responsible_user_id" placeholder="Nenhum usuário nomeado"
                            hint="Quem dá a resposta ao cliente no painel — pode ser um usuário sem ficha de técnico." />
                    </div>

                    <p class="nf-text-muted-2 small mb-0">
                        Nenhum dos dois é obrigatório: chamado sem dono é chamado que ninguém vê, e a lista de espera
                        existe para justamente mostrar isso.
                    </p>
                </div>
            @endunless

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar alterações' : 'Abrir chamado' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('tickets.show', $chamado) : route('tickets.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="O que esta tela não pergunta" subtitle="Porque tem outro dono na operação.">
        <ul class="nf-fact-list mb-0">
            <li>
                <span>Protocolo</span>
                <strong class="nf-mono">CH-ano-sequência, gerado no banco por empresa</strong>
            </li>
            <li>
                <span>Estado e abertura</span>
                <strong class="nf-mono">o chamado nasce aberto; mover o estado é botão da ficha</strong>
            </li>
            <li>
                <span>Nota da conversa</span>
                <strong class="nf-mono">entra na ficha, marcada como interna quando for do escritório</strong>
            </li>
            <li>
                <span>Prazo</span>
                <strong class="nf-mono">calculado da prioridade, não digitado</strong>
            </li>
        </ul>

        @unless ($editando)
            <p class="nf-text-muted-2 small mb-0 mt-2">
                A chamada nasce sem conversa: depois de abri-la, a ficha mostra o prazo correndo e o campo de resposta.
            </p>
        @endunless
    </x-ui.card>
</x-layouts.app>
