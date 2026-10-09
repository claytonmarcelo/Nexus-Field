@use('App\Support\Formatters')

@php
    $editando = $podeGerir;
    $logo = $empresa->logo_path ? asset('storage/'.mb_substr($empresa->logo_path, strlen('storage/'))) : null;
@endphp

<x-layouts.app
    title="Configurações"
    subtitle="A casa em ordem: o perfil que o time enxerga, a tolerância que o check-in aceita e o ritmo do sino de todo dia."
    :trilha="['Gestão' => route('settings.index'), 'Configurações' => null]"
>
    @unless ($editando)
        <x-ui.card>
            <x-ui.state tone="denied" title="Modo leitura"
                text="Você vê a casa por dentro, mas só quem desenha as regras da casa encosta em qualquer valor. O plano e a chave de endereço não mudam por esta tela nem para o administrador." />
        </x-ui.card>
    @endunless

    <x-ui.card>
        <form method="POST" action="{{ route('settings.update') }}" data-nf-guard novalidate
            @if ($editando) enctype="multipart/form-data" @endif>
            @csrf
            @method('PUT')

            <div class="nf-form-secao">
                <h2>Perfil da empresa</h2>

                <div class="nf-form-grade">
                    <x-ui.input label="Nome" name="name" :value="$empresa->name"
                        placeholder="Como a empresa se apresenta" required :disabled="! $editando" />

                    <div class="mb-3">
                        <label class="form-label" for="slug-empresa">Chave de endereço</label>
                        <input class="form-control disable-adminlte-validations" id="slug-empresa"
                            value="{{ $empresa->slug }}" disabled>
                        <div class="form-text">
                            Única para sempre: é assim que a empresa aparece em link e log do sistema.
                        </div>
                    </div>

                    <x-ui.input label="Documento" name="document" :value="$empresa->document"
                        placeholder="CNPJ ou outro registro" :disabled="! $editando"
                        hint="Se fica entre a empresa e o contador; o sistema só guarda o que você digitar." />

                    <x-ui.input label="Telefone" name="phone" :value="$empresa->phone" inputmode="tel"
                        placeholder="(11) 4000-0000" :disabled="! $editando" />

                    <x-ui.input label="E-mail" name="email" type="email" :value="$empresa->email"
                        placeholder="contato@empresa.com.br" autocomplete="off" :disabled="! $editando"
                        hint="O endereço do escritório, único no sistema — não confunda com o e-mail de uma conta." />

                    <x-ui.input label="Site" name="website" type="url" :value="$empresa->website"
                        placeholder="https://empresa.com.br" :disabled="! $editando" />
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>A marca da empresa</h2>

                <div class="nf-marca-edital">
                    <div class="nf-marca-atual">
                        @if ($logo !== null)
                            <img src="{{ $logo }}" alt="A marca enviada por esta empresa" class="nf-marca-img" />
                            <p class="nf-text-muted-2 small mb-0">A sua marca, no lugar da marca da plataforma.</p>
                        @else
                            <x-ui.brand-mark arquivo="marca-nexus-64" :lado="56" />
                            <p class="nf-text-muted-2 small mb-0">Nada enviado ainda: a barra lateral mostra a marca da plataforma.</p>
                        @endif
                    </div>

                    @if ($editando)
                        <div class="nf-marca-envio">
                            <x-ui.input label="Enviar nova marca" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp"
                                hint="PNG, JPG ou WEBP, até 2 MB, entre 64 e 1200 pixels de cada lado. A marca antiga sai do arquivo junto com a troca." />
                        </div>
                    @endif
                </div>
            </div>

            <div class="nf-form-secao">
                <h2>Operação do campo</h2>

                <div class="nf-form-grade">
                    @php
                        $raio = old('checkin_raio', $valores[\App\Support\SettingsCatalog::RAIO_CHECKIN]);
                        $janela = old('financeiro_dias_alerta', $valores[\App\Support\SettingsCatalog::DIAS_ALERTA_VENCIMENTO]);
                    @endphp

                    <x-ui.input label="{{ $preferencias[\App\Support\SettingsCatalog::RAIO_CHECKIN]['rotulo'] }}"
                        name="checkin_raio" inputmode="numeric" :value="$raio" :disabled="! $editando"
                        placeholder="{{ $preferencias[\App\Support\SettingsCatalog::RAIO_CHECKIN]['padrao'] }}"
                        :hint="$preferencias[\App\Support\SettingsCatalog::RAIO_CHECKIN]['ajuda']" />

                    <x-ui.input label="{{ $preferencias[\App\Support\SettingsCatalog::DIAS_ALERTA_VENCIMENTO]['rotulo'] }}"
                        name="financeiro_dias_alerta" inputmode="numeric" :value="$janela" :disabled="! $editando"
                        placeholder="{{ $preferencias[\App\Support\SettingsCatalog::DIAS_ALERTA_VENCIMENTO]['padrao'] }}"
                        :hint="$preferencias[\App\Support\SettingsCatalog::DIAS_ALERTA_VENCIMENTO]['ajuda']" />
                </div>

                @foreach (['checkin_raio', 'financeiro_dias_alerta'] as $campo)
                    @error($campo)
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                @endforeach
            </div>

            <div class="nf-form-secao">
                <h2>A varredura de todo dia</h2>

                <p class="nf-text-muted-2">
                    Às sete da manhã o sistema escuta a empresa inteira: ordem vencida, conta a bater,
                    agenda de amanhã. Aqui se decide o que pode bater no sino — desligar uma família não
                    apaga fato nenhum, só para o toque recorrente dela.
                </p>

                <div class="nf-check-grade">
                    @foreach (['varredura_ordens_atrasadas', 'varredura_vencimentos', 'varredura_agenda'] as $chave)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="{{ $chave }}"
                                id="lig-{{ $chave }}" value="1"
                                @checked(old($chave, $valores[$chave]))
                                @disabled(! $editando)>
                            <label class="form-check-label" for="lig-{{ $chave }}">
                                {{ $preferencias[$chave]['rotulo'] }}
                            </label>
                        </div>
                    @endforeach
                </div>

                @foreach (['varredura_ordens_atrasadas', 'varredura_vencimentos', 'varredura_agenda'] as $chave)
                    <p class="nf-text-muted-2 small mb-1">{{ $preferencias[$chave]['ajuda'] }}</p>
                @endforeach
            </div>

            @if ($editando)
                <div class="nf-form-acoes">
                    <x-ui.button type="submit" variant="primary" icon="fa-solid fa-floppy-disk" data-loading="false">
                        Salvar configurações
                    </x-ui.button>

                    <p class="nf-text-muted-2 mb-0 small">
                        Cada campo alterado sai carimbado na auditoria, com o antes e o depois.
                    </p>
                </div>
            @endif
        </form>
    </x-ui.card>

    <x-ui.card title="O plano, que não se mexe aqui"
        subtitle="Contrato com a plataforma: cota e validade nascem fora desta tela.">
        <dl class="nf-dl">
            <div><dt>Plano</dt><dd>{{ $empresa->plan?->name ?? '—' }}</dd></div>
            <div><dt>Contas</dt><dd>{{ $contas }} de {{ $empresa->plan?->max_users ?? '∞' }} em uso</dd></div>
            <div><dt>Assinatura</dt><dd>{{ \App\Support\StatusCatalog::label('subscription', $empresa->subscription_status) }}</dd></div>
            <div><dt>Validade</dt><dd>{{ $empresa->expires_at !== null ? Formatters::date($empresa->expires_at) : 'sem termo' }}</dd></div>
        </dl>

        <p class="nf-text-muted-2 small mb-0">
            Subir de plano, entrar em trial ou renovar é conversa com a plataforma — esta tela só explica
            a cota que o contrato atual impõe.
        </p>
    </x-ui.card>
</x-layouts.app>
