@use('App\Support\StatusCatalog')

@php
    $editando = $compromisso->exists;

    // O campo é datetime-local, que fala ISO sem fuso ("2026-10-08T14:30"). É o
    // relógio de parede da operação que o banco guarda, então nada aqui converte.
    $marca = fn ($momento) => $momento?->format('Y-m-d\TH:i');
@endphp

<x-layouts.app
    :title="$editando ? 'Editar compromisso' : 'Novo compromisso'"
    :subtitle="$editando
        ? 'A janela, o técnico e o lugar. O estado do compromisso não muda aqui: é botão da ficha.'
        : 'Reservar uma janela na escala: o que vai acontecer, quando, com quem e onde.'"
    :trilha="['Operação' => route('agenda.index'), 'Agenda' => route('agenda.index'), ($editando ? 'Editar compromisso' : 'Novo compromisso') => null]"
>
    <x-ui.card>
        <form method="POST"
            action="{{ $editando ? route('agenda.update', $compromisso) : route('agenda.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>O que é</h2>

                <div class="nf-form-largo">
                    <x-ui.input label="Título" name="title" :value="$compromisso->title"
                        placeholder="Manutenção na câmara fria — Padaria Sant'Anna" required />
                </div>

                <div class="nf-form-grade">
                    <x-ui.select label="Tipo" name="type" :opcoes="$tipos" :value="$compromisso->type" required
                        hint="Ordem de serviço e chamado prendem o compromisso à ficha deles; visita e compromisso interno não." />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Quando</h2>

                <x-ui.switch name="all_day" label="Dia inteiro" :checked="$compromisso->all_day"
                    hint="Marcado, o que vale são os dias: o arraste no calendário desloca a janela inteira sem hora." />

                <div class="nf-form-grade">
                    <x-ui.input label="Início" name="starts_at" type="datetime-local"
                        :value="$marca($compromisso->starts_at)" required />

                    <x-ui.input label="Fim" name="ends_at" type="datetime-local"
                        :value="$marca($compromisso->ends_at)" required
                        :hint="$compromisso->all_day
                            ? 'Num compromisso de dia inteiro o fim pode cair no mesmo dia.'
                            : 'Precisa ser depois do início: é a duração que o calendário desenha.'" />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Onde e para quem</h2>

                @if ($restrito)
                    <div class="nf-form-largo">
                        <p class="nf-text-muted-2 small mb-0">
                            Esta conta tem vínculo de técnico ou de cliente, então a escala inteira da empresa não aparece
                            na lista: quem marca a janela de um técnico é o escritório.
                        </p>
                    </div>
                @else
                    <div class="nf-form-grade">
                        <x-ui.select label="Técnico" name="technician_id" :opcoes="$tecnicos"
                            :value="$compromisso->technician_id" placeholder="Ainda sem técnico"
                            hint="O compromisso aparece na agenda dele a partir deste campo — é por isso que um técnico sem compromisso nenhum já tem uma escala." />

                        <x-ui.select label="Cliente" name="client_id" :opcoes="$clientes"
                            :value="$compromisso->client_id" placeholder="Compromisso interno"
                            hint="Prender a uma ordem ou a um chamado já preenche o cliente sozinho." />
                    </div>
                @endif

                <div class="nf-form-grade">
                    <x-ui.input label="Local" name="location" :value="$compromisso->location"
                        placeholder="Cozinha do cliente, base operacional, endereço da ordem..." />

                    <x-ui.select label="Ordem de serviço" name="service_order_id" :opcoes="$ordens"
                        :value="$compromisso->service_order_id" placeholder="Nenhuma ordem"
                        hint="A janela da ordem já aparece no calendário sozinha; prendendo aqui o compromisso conta o mesmo número." />
                </div>

                <div class="nf-form-grade">
                    <x-ui.select label="Chamado" name="ticket_id" :opcoes="$chamados"
                        :value="$compromisso->ticket_id" placeholder="Nenhum chamado" />
                </div>

                <x-ui.textarea label="Anotação da janela" name="description" :value="$compromisso->description"
                    rows="4" placeholder="Levar a peça, avisar a portaria, horário de acesso..."
                    hint="Texto simples. É o que quem chega lê na ficha do compromisso." />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-calendar-plus' }}"
                    data-loading="false">
                    {{ $editando ? 'Salvar alterações' : 'Marcar na agenda' }}
                </x-ui.button>

                <x-ui.button variant="ghost"
                    :href="$editando ? route('agenda.show', $compromisso) : route('agenda.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="O que esta tela não decide" subtitle="Porque o estado e a empresa têm outro dono.">
        <ul class="nf-fact-list mb-0">
            <li>
                <span>Empresa</span>
                <strong class="nf-mono">vem do contexto do login, não de campo</strong>
            </li>
            <li>
                <span>Estado</span>
                <strong class="nf-mono">nasce agendado; concluído e cancelado são botão da ficha</strong>
            </li>
            <li>
                <span>Cliente de ordem e chamado</span>
                <strong class="nf-mono">herdado da ficha presa, escrito no servidor</strong>
            </li>
            <li>
                <span>Duração</span>
                <strong class="nf-mono">medida do início e do fim gravados, não digitada</strong>
            </li>
        </ul>
    </x-ui.card>
</x-layouts.app>
