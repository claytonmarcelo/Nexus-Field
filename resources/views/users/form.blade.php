@use('App\Support\StatusCatalog')

@php
    $editando = $conta->exists;
    $selecionados = old('papeis', $editando ? $conta->roles->pluck('id')->all() : []);
    $selecionados = array_map('intval', (array) $selecionados);
    $clienteSelecionado = old('cliente', $conta->client_id);
@endphp

<x-layouts.app
    :title="$editando ? 'Editar conta' : 'Nova conta'"
    :subtitle="$editando
        ? 'Ajuste o acesso de '.$conta->name.'. O que mudar aqui fica registrado na auditoria.'
        : 'Quem vai entrar neste sistema. Nome, e-mail, senha e papel — o papel define tudo que a pessoa enxerga.'"
    :trilha="['Gestão' => route('users.index'), 'Usuários' => route('users.index'), ($editando ? 'Editar' : 'Nova') => null]"
>
    @if ($teto !== null && ! $editando && $usadas >= $teto)
        <x-ui.card>
            <x-ui.state tone="no-results" :title="'O plano permite '.$teto.' '.($teto === 1 ? 'conta' : 'contas')"
                text="A empresa já tem todas as contas do plano. Melhore o plano ou desative quem não usa para liberar vaga." />
        </x-ui.card>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ $editando ? route('users.update', $conta) : route('users.store') }}"
            data-nf-guard novalidate>
            @csrf
            @if ($editando)
                @method('PUT')
            @endif

            <div class="nf-form-secao">
                <h2>Identificação</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome" name="name" :value="$conta->name"
                        placeholder="Como a pessoa se apresenta" required />

                    <x-ui.input label="E-mail" name="email" type="email" :value="$conta->email"
                        placeholder="pessoa@empresa.com.br" autocomplete="off" required
                        hint="Único no sistema inteiro: é com ele que a conta entra." />

                    <x-ui.input label="Telefone" name="phone" :value="$conta->phone" inputmode="tel"
                        placeholder="(11) 4000-0000" />

                    <x-ui.select label="Situação" name="status" :opcoes="$situacoes" :value="$conta->status" required />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Senha</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="{{ $editando ? 'Nova senha' : 'Senha inicial' }}" name="password"
                        type="password" autocomplete="new-password" :required="! $editando"
                        placeholder="Mínimo 10 caracteres, letras e números"
                        :hint="$editando ? 'Deixe em branco para manter a senha atual.' : 'Quem recebe a conta define a própria depois, pelo fluxo de recuperação.'" />

                    <x-ui.input label="Confirmar senha" name="password_confirmation" type="password"
                        autocomplete="new-password" :required="! $editando" placeholder="Digite de novo" />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Papéis</h2>

                @if ($papeis === [])
                    <p class="nf-text-muted-2 mb-0">
                        Nenhum papel cabe no seu alcance — você só concede papéis cujas permissões já tem por inteiro.
                        Crie um papel personalizado dentro do seu alcance ou peça a um administrador.
                    </p>
                @else
                    <div class="nf-check-grade">
                        @foreach ($papeis as $id => $nome)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="papeis[]"
                                    id="papel{{ $id }}" value="{{ $id }}"
                                    @checked(in_array((int) $id, $selecionados, true))>
                                <label class="form-check-label" for="papel{{ $id }}">{{ $nome }}</label>
                            </div>
                        @endforeach
                    </div>

                    <p class="nf-text-muted-2 small mb-0 mt-2">
                        Só aparecem aqui os papéis que você pode conceder: ninguém entrega alcance maior que o seu.
                    </p>
                @endif

                @error('papeis')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="nf-form-secao">
                <h2>Carteira do cliente</h2>

                <div class="nf-form-grade">
                    <x-ui.select label="Cliente vinculado" name="cliente" :opcoes="$clientes"
                        :value="$clienteSelecionado" placeholder="Sem carteira (uso interno)"
                        hint="Obrigatório para a conta do papel Cliente — é a carteira que ela vai abrir ao entrar; proibido para qualquer outro papel." />
                </div>

                @error('cliente')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="nf-form-acoes">
                <x-ui.button type="submit" variant="primary"
                    icon="{{ $editando ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus' }}" data-loading="false">
                    {{ $editando ? 'Salvar conta' : 'Criar conta' }}
                </x-ui.button>

                <x-ui.button variant="ghost" :href="$editando ? route('users.show', $conta) : route('users.index')">
                    Cancelar
                </x-ui.button>

                @if ($teto !== null)
                    <p class="nf-text-muted-2 mb-0 small">
                        {{ $usadas }} de {{ $teto }} {{ $teto === 1 ? 'conta' : 'contas' }} do plano em uso.
                    </p>
                @endif
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
