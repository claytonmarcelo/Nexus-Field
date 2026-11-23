@php
    $dados = json_encode($tecnicos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<x-layouts.app
    title="Mapa GPS"
    subtitle="Quem está em campo e por onde passou: a posição e a rota lidas do banco desta empresa."
    :trilha="['Operação' => null, 'Mapa GPS' => null]"
>
    <div class="nf-gps" data-nf-gps
        data-nf-gps-dados="{{ $dados }}"
        data-nf-gps-url="{{ $rotaCompartilhamento }}"
        data-nf-gps-token="{{ csrf_token() }}"
    >
        <div class="row g-3">
            <div class="col-xl-8">
                <x-ui.card title="Rotas em campo">
                    @if ($tecnicos === [])
                        <x-ui.state tone="empty" title="Nenhuma equipe no mapa"
                            text="Assim que um técnico de campo registrar a chegada numa ordem, a rota dele aparece aqui." />
                    @else
                        <div id="nf-gps-mapa" class="nf-gps-mapa" role="img"
                            aria-label="Mapa das rotas dos técnicos de campo"></div>
                        <p class="nf-gps-legenda mb-0">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            Posição informada
                            <i class="fa-solid fa-route" aria-hidden="true"></i>
                            Rota percorrida
                            <span>{{ $totalRotas }} {{ $totalRotas === 1 ? 'ponto registrado' : 'pontos registrados' }}</span>
                        </p>
                    @endif
                </x-ui.card>
            </div>

            <div class="col-xl-4">
                <x-ui.card title="Equipe de campo">
                    @if ($comPartilhar)
                        <div class="nf-gps-compartilhar">
                            <x-ui.button type="button" variant="ghost" size="sm" icon="fa-solid fa-location-dot"
                                data-nf-gps-compartilhar>
                                Compartilhar minha localização
                            </x-ui.button>
                            <p class="nf-gps-aviso mb-0">
                                O navegador pede permissão antes de qualquer leitura. Você pode parar o
                                compartilhamento a qualquer momento.
                            </p>
                        </div>
                    @endif

                    <ul class="nf-gps-lista">
                        @foreach ($tecnicos as $tecnico)
                            <li>
                                <button type="button" class="nf-gps-pessoa" data-nf-gps-foco="{{ $tecnico['id'] }}">
                                    <span class="nf-gps-pessoa-nome">{{ $tecnico['nome'] }}</span>
                                    <span class="nf-gps-pessoa-meta">
                                        {{ $tecnico['funcao'] }}
                                        · {{ count($tecnico['rota']) }} {{ count($tecnico['rota']) === 1 ? 'ponto' : 'pontos' }}
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            </div>
        </div>

        <p class="nf-gps-nota" aria-live="polite" data-nf-gps-status hidden></p>
    </div>
</x-layouts.app>
