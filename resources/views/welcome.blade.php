<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-head
        :title="config('app.name', 'NEXUS-FIELD').' — Gestão de operações em campo'"
        description="Plataforma de gestão de campo: ordens de serviço, chamados, agenda, check-in geolocalizado, estoque e financeiro, com autorização real por papel."
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
                                A operação externa inteira em um só lugar — do chamado aberto até a assinatura do cliente.
                            </h1>
                            <p class="nf-hero-text">
                                Ordens de serviço, chamados, agenda, check-in com geolocalização, estoque e financeiro
                                conversando entre si. Cada tela mostra apenas o que o papel do usuário pode ver, e o
                                servidor recusa o resto.
                            </p>
                            <div class="d-flex flex-column flex-sm-row gap-2">
                                <x-ui.button href="{{ route('login') }}" variant="primary" size="lg" icon="fa-solid fa-key">
                                    Acessar a plataforma
                                </x-ui.button>
                                <x-ui.button href="#modulos" variant="ghost" size="lg">
                                    Ver os módulos
                                </x-ui.button>
                            </div>
                        </div>
                        <div class="col-12 col-lg-5">
                            {{-- O medalhão pendurado no próprio eixo: balança com
                                 amplitude cada vez menor e assenta nivelado, que é
                                 o que a plataforma faz com a operação do cliente. --}}
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

                            <div class="nf-flow-panel">
                                <p class="nf-label">O ciclo de um serviço</p>
                                <ol class="nf-flow">
                                    @foreach ([
                                        'Chamado registrado',
                                        'Ordem de serviço aberta',
                                        'Técnico designado e agendado',
                                        'Check-in no local, com posição',
                                        'Consumo de estoque lançado',
                                        'Aceite do cliente e histórico',
                                    ] as $passo)
                                        <li>{{ $passo }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="nf-section" id="modulos">
                <div class="container">
                    <header class="nf-section-head">
                        <p class="nf-label">Módulos integrados</p>
                        <h2 class="nf-display nf-section-title">Um módulo alimenta o outro</h2>
                        <p class="nf-section-text">
                            A mesma base de clientes, técnicos, itens e ordens serve ao operacional e ao financeiro.
                            Nada é digitado duas vezes, nada é informado à mão.
                        </p>
                    </header>

                    <div class="row g-4">
                        @foreach ([
                            ['fa-solid fa-clipboard-list', 'Ordens de serviço', 'Abertura, designação, execução e aceite, com estados controlados e histórico completo.'],
                            ['fa-solid fa-headset', 'Chamados', 'Fila priorizada por urgência e prazo, com vínculo direto à ordem que a resolve.'],
                            ['fa-solid fa-calendar-days', 'Agenda', 'Calendário por técnico, equipe e cliente, arrastando da demanda para a janela real.'],
                            ['fa-solid fa-location-crosshairs', 'Check-in em campo', 'Entrada e saída do local com posição registrada, comprovando o que foi executado.'],
                            ['fa-solid fa-boxes-stacked', 'Estoque', 'Produtos, serviços, lotes e movimentações ligados ao consumo de cada ordem.'],
                            ['fa-solid fa-chart-line', 'KPIs e relatórios', 'SLA, custo, produtividade e faturamento calculados sobre o que está no banco.'],
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
                    <div class="row g-4 align-items-stretch">
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Autorização</p>
                                    <h2 class="nf-section-title">O botão não é a regra</h2>
                                    <p class="card-text">
                                        Cada ação passa pelo servidor antes de acontecer. Papéis — Administrador,
                                        Supervisor/Gestor, Funcionário, Técnico e Cliente — carregam permissões por
                                        módulo, e uma requisição fora do escopo devolve 403 mesmo que chegue pronta.
                                    </p>
                                </div>
                            </article>
                        </div>
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Multiempresa</p>
                                    <h2 class="nf-section-title">Cada empresa enxerga só a si</h2>
                                    <p class="card-text">
                                        O contexto da empresa é resolvido na entrada da requisição e aplicado pelo
                                        <span class="nf-mono">company_id</span> em todas as consultas. Criar um
                                        registro para outra empresa a partir de um formulário enviado não é possível.
                                    </p>
                                </div>
                            </article>
                        </div>
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Sessão</p>
                                    <h2 class="nf-section-title">Entrar e sair sem rastro</h2>
                                    <p class="card-text">
                                        Troca de identificador da sessão após o login, tentativa limitada por
                                        e-mail e endereço, sessão invalidada e token renovado no logout, e senha
                                        somente com hash.
                                    </p>
                                </div>
                            </article>
                        </div>
                        <div class="col-12 col-lg-6">
                            <article class="card h-100">
                                <div class="card-body">
                                    <p class="nf-label">Auditoria</p>
                                    <h2 class="nf-section-title">Quem fez o quê, e quando</h2>
                                    <p class="card-text">
                                        Movimentações relevantes deixam registro com autor, empresa e instante, para
                                        que uma discussão com cliente não dependa da memória de ninguém.
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
                                Entre com a conta da sua empresa e monte clientes, técnicos, agenda e ordens a partir
                                do primeiro acesso.
                            </p>
                        </div>
                        <x-ui.button href="{{ route('login') }}" variant="accent" size="lg" icon="fa-solid fa-arrow-right">
                            Acessar
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
