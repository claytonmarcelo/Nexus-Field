@use('App\Support\StatusCatalog')

@php
    $editando = $cliente->exists;
@endphp

<x-layouts.app
    :title="$editando ? 'Editar cliente' : 'Novo cliente'"
    :subtitle="$editando
        ? 'Ajuste o cadastro de '.$cliente->name.'. O que mudar aqui fica registrado na auditoria.'
        : 'Quem a empresa vai atender. Nome, documento e situação bastam para abrir a primeira ordem de serviço.'"
    :trilha="['Cadastros' => route('clients.index'), 'Clientes' => route('clients.index'), ($editando ? 'Editar' : 'Novo') => null]"
>
    <x-ui.card>
        <form method="POST" action="{{ $editando ? route('clients.update', $cliente) : route('clients.store') }}"
            data-nf-guard novalidate>
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome do cliente" name="name" :value="$cliente->name"
                        placeholder="Razão social ou nome como aparece no contrato" required />

                    <x-ui.input label="Nome fantasia" name="trade_name" :value="$cliente->trade_name"
                        placeholder="Como o chamam no dia a dia" />

                    <x-ui.input label="Documento" name="document" :value="$cliente->document" inputmode="numeric"
                        placeholder="CNPJ ou CPF" hint="Único dentro desta empresa. Pode ficar em branco." />

                    <x-ui.select label="Situação" name="status" :opcoes="$situacoes" :value="$cliente->status" required />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Contato</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="E-mail" name="email" type="email" :value="$cliente->email"
                        placeholder="contato@empresa.com.br" autocomplete="off" />

                    <x-ui.input label="Telefone" name="phone" :value="$cliente->phone" inputmode="tel"
                        placeholder="(11) 4000-0000" />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Observações internas</h2>

                <x-ui.textarea label="Anotações" name="notes" :value="$cliente->notes" rows="4" class="nf-form-largo"
                    placeholder="Restrições de acesso, horário de obra, quem autoriza o serviço..."
                    hint="Visível para quem tem acesso ao cadastro; não aparece em ordem de serviço." />
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar alterações' : 'Cadastrar cliente' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('clients.show', $cliente) : route('clients.index')">
                    Cancelar
                </x-ui.button>

                @if (! $editando)
                    <p class="nf-text-muted-2 mb-0 small">
                        Endereço e contatos entram depois, já dentro da ficha do cliente.
                    </p>
                @endif
            </div>
        </form>
    </x-ui.card>

    @if ($editando)
        <x-ui.card title="Situação" subtitle="O que cada escolha muda na operação.">
            <ul class="nf-fact-list mb-0">
                <li>
                    <span>{{ StatusCatalog::label('client', 'active') }}</span>
                    <strong class="nf-mono">entra na busca de ordem e chamado</strong>
                </li>
                <li>
                    <span>{{ StatusCatalog::label('client', 'inactive') }}</span>
                    <strong class="nf-mono">histórico preservado, nada novo</strong>
                </li>
            </ul>
        </x-ui.card>
    @endif
</x-layouts.app>
