@use('App\Support\Formatters')
@use('App\Support\StatusCatalog')

@php
    $editando = $ordem->exists;
    $liquido = $editando ? $ordem->totais()['liquido'] : null;
@endphp

<x-layouts.app
    :title="$editando ? 'Editar ordem '.$ordem->number : 'Nova ordem de serviço'"
    :subtitle="$editando
        ? 'Ajuste o que '.$ordem->client->name.' pediu. O estado da ordem não muda aqui: é botão da ficha.'
        : 'O que o cliente pediu, onde, quando e para quem. O número a ordem recebe sozinha, na sequência do ano desta empresa.'"
    :trilha="['Operação' => route('orders.index'), 'Ordens de serviço' => route('orders.index'), ($editando ? 'Editar '.$ordem->number : 'Nova') => null]"
>
    <x-ui.card>
        <form method="POST" :action="$editando ? route('orders.update', $ordem) : route('orders.store')"
            data-nf-guard novalidate>
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Cliente" name="client_id" :opcoes="$clientes" :value="$ordem->client_id"
                        required hint="Só os cadastros ativos desta empresa aparecem na lista." />

                    <x-ui.select label="Serviço do catálogo" name="service_id" :opcoes="$servicos"
                        :value="$ordem->service_id" placeholder="Serviço que não está no catálogo"
                        hint="Serve de referência para a duração estimada; a cobrança mesmo são as linhas da ficha." />

                    <x-ui.select label="Prioridade" name="priority" :opcoes="$prioridades" :value="$ordem->priority"
                        required hint="É o que ordena a fila do técnico quando mais de uma ordem espera." />

                    @if ($editando)
                        <div class="nf-form-largo">
                            <p class="nf-text-muted-2 small mb-0">
                                Estado hoje:
                                <span class="{{ StatusCatalog::badge('order', $ordem->status) }}">
                                    {{ StatusCatalog::label('order', $ordem->status) }}
                                </span>
                                — mudá-lo é pelo botão da ficha, que registra quem fez e quando.
                            </p>
                        </div>
                    @else
                        <x-ui.select label="Situação inicial" name="status" :opcoes="$situacoes" :value="$ordem->status"
                            required hint="Rascunho fica fora da fila do técnico; aberta já entra. Executar, concluir e cancelar são do botão de estado." />
                    @endif
                </div>

                <div class="nf-form-largo">
                    <x-ui.input label="Título da ordem" name="title" :value="$ordem->title"
                        placeholder="Manutenção preventiva de duas câmaras frias" required />
                </div>

                <x-ui.textarea label="O que foi pedido" name="description" :value="$ordem->description" rows="4"
                    class="nf-form-largo"
                    placeholder="Relato do cliente, sintomas, o que já foi tentado..."
                    hint="É o texto que a busca da listagem varre junto com o número e o endereço." />
            </div>

            <div class="nf-form-secao">
                <h2>Agendamento e quem executa</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Início previsto" name="scheduled_starts_at" type="datetime-local"
                        :value="$ordem->scheduled_starts_at?->format('Y-m-d\TH:i')"
                        hint="Deixe o fim em branco e a ordem fecha no horário pelo tempo estimado do serviço escolhido." />

                    <x-ui.input label="Fim previsto" name="scheduled_ends_at" type="datetime-local"
                        :value="$ordem->scheduled_ends_at?->format('Y-m-d\TH:i')" />

                    <x-ui.select label="Técnico responsável" name="technician_id" :opcoes="$tecnicos"
                        :value="$ordem->technician_id" placeholder="Sem técnico definido"
                        hint="Quem responde pela ordem no campo. A equipe de apoio entra no quadro de comissão da ficha." />

                    <x-ui.select label="Equipe" name="team_id" :opcoes="$equipas" :value="$ordem->team_id"
                        placeholder="Sem equipe" hint="Usada para distribuir a operação por região." />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Local do serviço</h2>

                <p class="nf-text-muted-2 small">
                    A ordem carrega o endereço do dia, copiado do cadastro do cliente. Se ele mudar de sala amanhã,
                    esta ordem continua dizendo onde o técnico esteve — e é daqui que o check-in mede a chegada.
                </p>

                <div class="nf-form-grade">
                    <div class="nf-form-largo">
                        <x-ui.input label="Logradouro" name="street" :value="$ordem->street"
                            placeholder="Rua, avenida ou estrada" />
                    </div>

                    <x-ui.input label="Número" name="number_address" :value="$ordem->number_address" />

                    <x-ui.input label="Complemento" name="complement" :value="$ordem->complement"
                        placeholder="Sala, andar, bloco" />

                    <x-ui.input label="Bairro" name="neighborhood" :value="$ordem->neighborhood" />

                    <x-ui.input label="Cidade" name="city" :value="$ordem->city" />

                    <x-ui.input label="UF" name="state" :value="$ordem->state" maxlength="2" placeholder="SP"
                        hint="Duas letras; o cadastro põe em maiúscula." />

                    <x-ui.input label="CEP" name="zip_code" :value="$ordem->zip_code" inputmode="numeric"
                        placeholder="00000-000" />

                    <x-ui.input label="Latitude" name="latitude" type="number" :value="$ordem->latitude"
                        step="0.0000001" min="-90" max="90" placeholder="-23.5505200" inputmode="decimal" />

                    <x-ui.input label="Longitude" name="longitude" type="number" :value="$ordem->longitude"
                        step="0.0000001" min="-180" max="180" placeholder="-46.6333080" inputmode="decimal" />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Valores e execução</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Desconto da ordem (R$)" name="discount" type="number" :value="$ordem->discount"
                        step="0.01" min="0" inputmode="decimal" placeholder="0,00"
                        hint="Desconto jogado sobre a conta inteira, somado ao que cada linha já dá." />

                    @if ($liquido !== null)
                        <div class="nf-form-largo">
                            <p class="nf-text-muted-2 small mb-0">
                                As linhas desta ordem cobram
                                <span class="nf-mono">{{ Formatters::money($liquido) }}</span> —
                                o desconto da ordem não pode passar disso.
                            </p>
                        </div>
                    @endif
                </div>

                <x-ui.textarea label="Notas de execução" name="execution_notes" :value="$ordem->execution_notes"
                    rows="4" class="nf-form-largo"
                    placeholder="Acesso, equipamento do cliente, quem libera a entrada, cuidado com o piso..."
                    hint="Fica na ordem: não é cobrança e não vai para o catálogo." />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar alterações' : 'Abrir ordem de serviço' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('orders.show', $ordem) : route('orders.index')">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="O que esta tela não pergunta" subtitle="Porque tem outro dono na operação.">
        <ul class="nf-fact-list mb-0">
            <li>
                <span>Número da ordem</span>
                <strong class="nf-mono">OS-ano-sequência, gerado no banco por empresa</strong>
            </li>
            <li>
                <span>Linhas cobradas</span>
                <strong class="nf-mono">entram na ficha, vindas do catálogo</strong>
            </li>
            <li>
                <span>Equipe de apoio</span>
                <strong class="nf-mono">quadro de comissão da ficha</strong>
            </li>
            <li>
                <span>Estado depois de aberta</span>
                <strong class="nf-mono">botão de estado, com histórico</strong>
            </li>
        </ul>

        @unless ($editando)
            <p class="nf-text-muted-2 small mb-0 mt-2">
                A ordem nasce sem linha cobrada: depois de salvá-la, a ficha abre o formulário de item
                e o de comissão.
            </p>
        @endunless
    </x-ui.card>
</x-layouts.app>
