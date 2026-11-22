<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-head
        :title="config('app.name', 'NEXUS-FIELD').' — Gestão de operações em campo'"
        description="Gestão de campo em uma única base: ordens, chamados, agenda, check-in, estoque e financeiro, com acesso por papel e auditoria."
    />
</head>
<body>
    <x-skip-links />

    <div class="nf-public">
        <x-public-header />

        <main id="conteudo">
            <section class="nf-hero">
                <div class="container">
                    {{-- `gx-4` e não `g-5`: a margem negativa de um row g-5 (-24px)
                         é maior que o respiro do container (12px) e abre barra de
                         rolagem horizontal no celular. --}}
                    <div class="row align-items-center gx-4 gy-5">
                        <div class="col-12 col-lg-7">
                            <p class="nf-label">Gestão de operações em campo</p>
                            <h1 class="nf-display nf-hero-title">
                                Gestão de campo sem planilhas paralelas
                            </h1>
                            <p class="nf-hero-text">
                                Ordens, chamados, agenda, check‑in, estoque e financeiro em uma
                                única fonte de verdade.
                            </p>
                            <p class="nf-hero-text">
                                Quem está na rua registra uma vez; quem acompanha de dentro vê o
                                mesmo dado na hora, com acesso por papel e histórico de cada ação.
                            </p>
                            {{-- Nenhuma porta de entrada no meio da página. Ela já está
                                 no cabeçalho e no fecho final: repetir aqui só dividiria
                                 a atenção de quem decidiu entrar. --}}
                        </div>
                        <div class="col-12 col-lg-5">
                            {{-- O medalhão assenta nivelado no próprio eixo, como a
                                 operação que a plataforma ajuda a manter em ordem. --}}
                            <div class="nf-brand-stage">
                                <img
                                    class="nf-brand-stage-logo"
                                    src="{{ asset('img/logo-nexus-480.png') }}"
                                    width="480"
                                    height="422"
                                    alt="NEXUS-FIELD"
                                />
                                <span class="nf-brand-stage-nivel" aria-hidden="true"></span>
                            </div>
                        </div>
                    </div>

                    {{-- A esteira fecha a abertura em faixa larga e mostra o caminho
                         do dado, do chamado à cobrança. --}}
                    <div class="nf-flow-panel nf-hero-ciclo">
                        <p class="nf-label">Ciclo do serviço</p>
                        <ol class="nf-flow">
                            @foreach ([
                                'Chamado registrado com prazo',
                                'Ordem emitida e sequenciada',
                                'Técnico designado na agenda',
                                'Check‑in no local',
                                'Peça baixada do estoque',
                                'Assinatura e cobrança fechadas',
                            ] as $passo)
                                <li>{{ $passo }}</li>
                            @endforeach
                        </ol>

                        <p class="nf-text-muted-2 mt-3 mb-0">
                            Cada etapa alimenta a seguinte: a ordem assinada gera a cobrança com
                            o valor das linhas do próprio serviço.
                        </p>
                    </div>
                </div>
            </section>

            <section class="nf-section" id="modulos">
                <div class="container">
                    <header class="nf-section-head">
                        <p class="nf-label">Módulos integrados</p>
                        <h2 class="nf-display nf-section-title">Um sistema, ponta a ponta</h2>
                        <p class="nf-section-text">
                            Clientes, técnicos, ordens e estoque vivem na mesma base. Cada módulo
                            tem tela, regra de acesso e indicadores próprios — sem planilhas paralelas.
                        </p>
                    </header>

                    <div class="row g-4">
                        @foreach ([
                            ['fa-solid fa-clipboard-list', 'Ordens de serviço', 'Abertura, execução e aceite com estados controlados e histórico completo.'],
                            ['fa-solid fa-headset', 'Chamados', 'Fila por urgência e prazo, ligada à ordem que resolve.'],
                            ['fa-solid fa-calendar-days', 'Agenda', 'Cada técnico na janela certa, sem conflito de horários.'],
                            ['fa-solid fa-location-crosshairs', 'Check‑in em campo', 'Presença registrada no local, com posição e assinatura.'],
                            ['fa-solid fa-boxes-stacked', 'Estoque', 'Saldo, carga do técnico e consumo na mesma ordem.'],
                            ['fa-solid fa-chart-line', 'Relatórios', 'SLA, custo e produtividade calculados sobre dados reais.'],
                        ] as [$icone, $titulo, $texto])
                            <div class="col-12 col-md-6 col-xl-4">
                                <article class="card h-100">
                                    <div class="card-body">
                                        <span class="nf-icon-tile mb-3">
                                            <i class="{{ $icone }}" aria-hidden="true"></i>
                                        </span>
                                        <h3 class="card-title">{{ $titulo }}</h3>
                                        <p class="card-text nf-text-muted-2">{{ $texto }}</p>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="nf-section nf-section-alt">
                <div class="container">
                    <header class="nf-section-head">
                        <p class="nf-label">Segurança</p>
                        <h2 class="nf-display nf-section-title">Regras que acompanham a operação</h2>
                    </header>

                    <div class="row g-4 align-items-stretch">
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Acesso</p>
                                    <h2 class="nf-section-title">Permissão por papel</h2>
                                    <p class="card-text">
                                        Cada ação passa pelo servidor. Papéis definem o que cada pessoa
                                        vê e faz; fora do escopo, a resposta é 403.
                                    </p>
                                </div>
                            </article>
                        </div>
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Multiempresa</p>
                                    <h2 class="nf-section-title">Cada empresa no seu contexto</h2>
                                    <p class="card-text">
                                        O tenant é resolvido na entrada da requisição e aplicado em
                                        todas as consultas. Dados de outra empresa não aparecem.
                                    </p>
                                </div>
                            </article>
                        </div>
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Sessão</p>
                                    <h2 class="nf-section-title">Sessão protegida</h2>
                                    <p class="card-text">
                                        Identificador trocado no login, tentativas limitadas por
                                        e‑mail e endereço, e senha armazenada somente com hash.
                                    </p>
                                </div>
                            </article>
                        </div>
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Auditoria</p>
                                    <h2 class="nf-section-title">Histórico verificável</h2>
                                    <p class="card-text">
                                        Alterações relevantes registram autor, empresa e instante —
                                        sem depender da memória de ninguém.
                                    </p>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="nf-section">
                <div class="container">
                    <div class="nf-cta">
                        <div>
                            <h2 class="nf-display nf-cta-title">Comece pela operação que já existe</h2>
                            <p class="nf-cta-text mb-0">
                                Crie sua conta com nome, e‑mail e senha. Depois, no painel, envie
                                o cadastro da sua empresa para análise.
                            </p>
                        </div>
                        <x-ui.button href="{{ route('register') }}" variant="primary" size="lg" icon="fa-solid fa-user-plus">
                            Criar conta
                        </x-ui.button>
                    </div>
                </div>
            </section>
        </main>

        <x-public-footer />
    </div>

    <x-flash-messages />
</body>
</html>
