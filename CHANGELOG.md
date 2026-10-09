# Changelog — NEXUS-FIELD

Todos os registros relevantes de mudanças deste projeto. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e este projeto usa
[SemVer](https://semver.org/lang/pt-BR/).

Padrão de versões: esta reconstrução parte do zero, então o baseline é `0.1.0`.

## [Unreleased]

### Adicionado

- Autenticação com sessão endurecida: `Auth::attempt`, troca do ID da sessão após entrar
  (contra session fixation), logout com `invalidate` + `regenerateToken`, bloqueio de usuário
  inativo e de empresa com assinatura inativa, e limite de 5 tentativas por e-mail e IP.
- Recuperação de senha pelo password broker do framework: token de uso único, expiração própria,
  senha nova com no mínimo 10 caracteres, letras e números, e confirmação obrigatória.
- Autorização real no servidor: catálogo de permissões por módulo (`PermissionCatalog`), os cinco
  papéis de sistema (Administrador, Supervisor/Gestor, Funcionário, Técnico, Cliente) sincronizados
  pelo seeder, `User::hasPermission`/`hasAnyPermission` com cache por request e o middleware
  `permission`, que recusa com 403 antes de qualquer botão existir na tela.
- Multi-tenancy efetivo, não apenas uma coluna a mais: `TenantContext` por request, middleware
  `ResolveCompany`, escopo global `CompanyScope` e o trait `BelongsToCompany`, com `anyCompany()`
  reservado para agregações internas e para o console.
- Models da camada nuclear: `Company`, `Plan`, `Role`, `Permission`, `User`, `Client`,
  `ClientContact`, `Address` e `CompanySetting`.
- 30 testes (86 asserções) cobrando login válido e inválido, usuário inativo, assinatura vencida,
  throttle, mudança de ID de sessão, logout, gate de permissão por papel, reset de senha com token
  válido/forgiado/fraco, isolamento entre tenants, as três telas abertas de acesso e a proibição
  dos diálogos nativos do navegador.
- `tests/CreatesFixtures.php`, que monta empresa, plano, permissões, papel e usuário para os
  testes de feature rodarem contra o MySQL real (`nexusfield_test`), sem mock de banco.
- Design system "Premium Gourmet + Technology" sobre AdminLTE 4: tokens de tema em
  `resources/css/nexusfield/tokens.css` (dia bonito / noite bonita, sem branco puro e sem preto
  puro), base em `base.css` e componentes em `components.css`, com cartões, botões (inclusive
  `.btn-accent`, `.btn-ghost`, `.btn-soft-primary` e estado `data-loading`), formulários, tabelas,
  badges de estado, KPIs, esqueletos e estados de interface (loading/empty/sem resultado/erro/sem
  permissão).
- Identidade tipográfica e de cor aplicada por variáveis, sem `!important`: Bootstrap 5.3 e
  AdminLTE 4 lidos dos pacotes npm e revestidos por `--bs-*`/`--lte-*`; Inter para a interface e
  Fraunces para o display, servidos localmente pelo Vite (`@fonts`), com `fontaine` gerando o
  fallback métrico das fontes.
- Componentes Blade do design system: `x-ui.button`, `x-ui.card`, `x-ui.input`, `x-ui.state`,
  `x-ui.theme-toggle` e o script pré-pintura `x-script.theme`, que aplica o tema salvo antes do
  primeiro frame.
- Chave global de tema claro/escuro com preferência persistida em `localStorage` e cookie
  (`nf_theme`, SameSite=Lax), cor da barra do celular sincronizada e respeito a
  `prefers-color-scheme` quando nada foi escolhido.
- Camada de interação compartilhada: `toast` (Toastr com `escapeHtml: true`, para que mensagem
  vinda do banco não vire HTML), `confirm`/`prompt`/`alert` via SweetAlert2 no lugar dos diálogos
  nativos — que ficam proibidos no projeto — e guarda de envio em `form[data-nf-guard]`, que
  desabilita o botão e mostra o spinner enquanto o request não volta.
- Página de entrada pública real (`resources/views/welcome.blade.php`) com a casca que as telas
  abertas compartilham (`public.css`): cabeçalho fixo com marca e chave de tema, apresentação dos
  módulos integrados, das garantias de autorização, multiempresa, sessão e auditoria, e a chamada
  de acesso. Nenhum número, cadastro ou gráfico inventado na tela — o que depende de banco chega
  nas fases seguintes, junto com as telas autenticadas.
- `x-ui.brand-mark`, a marca da plataforma desenhada em SVG com as próprias variáveis de cor, sem
  imagem externa e sem depender do tema para trocar de aparência.
- Casca compartilhada das telas abertas — `x-site-head`, `x-public-header`, `x-public-footer`,
  reunidos em `x-auth-shell` —, de modo que a página de entrada e as telas de acesso usem o mesmo
  cabeçalho, o mesmo rodapé e a mesma cabeça de documento, sem cópia de marcação.
- Telas de acesso reconstruídas sobre essa casca: entrada (`auth/login`), solicitação de link
  (`auth/forgot-password`) e definição de senha nova (`auth/reset-password`), com os campos do
  design system, `autocomplete` correto (`username`, `current-password`, `new-password`), rótulo de
  obrigatoriedade, ajuda de regra de senha e link de volta — nada de formulário que não salva.
- `x-flash-messages` com `resources/js/nexusfield/flash.js`: o recado que o controlador devolve em
  `session('status')` chega como toast uma única vez por carregamento de página, e o nó some do
  DOM depois de lido.
- `x-skip-links`, o par de links de salto do teclado em português, reutilizado por todas as telas
  abertas.
- `lang/pt_BR/validation.php` e `lang/pt_BR/passwords.php`: sem pasta de idioma no projeto, o
  framework devolvia "The email field is required." e "We have emailed your password reset link."
  no meio de uma interface em português. Só as regras que o código usa hoje entraram.
- `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD` documentados no `.env.example`: a credencial do
  administrador semeado vem do ambiente, nunca do código, e o seeder recusa senha gerada
  automaticamente em produção.
- Layout autenticado na estrutura oficial do AdminLTE 4: `x-layouts.app` com `app-wrapper` em
  grid, `x-app.sidebar` (marca + menu), `x-app.navbar` (hambúrguer `data-lte-toggle="sidebar"`,
  chave de tema e menu do usuário com sair por POST+CSRF), `x-app.content-header` (título,
  subtítulo e trilha) e `x-app.footer`. O estado da sidebar mora no `<body>`, como o template
  manda, e `<html data-lte-color-mode="off">` desliga o ColorMode do AdminLTE para a chave de
  tema do projeto continuar sendo a única.
- `App\Support\Navigation`, o mapa do menu lateral: uma entrada só aparece se a rota dela existir
  **e** se o papel do usuário tiver a permissão correspondente. Link para tela que não existe é
  funcionalidade falsa, e link que o papel não pode abrir seria um 403 na cara de quem entrou.
- `DashboardController` e a tela de dashboard: sessão, empresa, plano, papéis e último acesso na
  tela, mais quatro contagens que saem de consultas reais ao MySQL desta empresa (usuários,
  clientes, papéis e permissões do catálogo). Nenhum indicador antes de existir módulo que o
  produza.
- `<x-signature />`, a assinatura de rodapé (NEXUS-FIELD · Clayton Marcelo · 2026) compartilhada
  entre a página pública e a autenticada; o crédito estava escrito em dois arquivos.
- `User::initials()`, as iniciais do avatar — identificação que não depende de upload de foto.
- 38 testes (119 asserções), com `tests/Feature/AuthenticatedLayoutTest.php` cobrindo convidado
  mandado para o login, dados de sessão e empresa na tela, contagem batendo com o banco, menu sem
  link morto, item sem permissão fora do menu e recusado no gate, logout por POST com CSRF, o
  contrato do layout (classes do body, treeview, atalhos de teclado em português) e o rodapé.
- `App\Support\DashboardMetrics`, o painel inteiro servido por consulta ao MySQL desta empresa:
  cada bloco (ordens, agenda, chamados, equipe, clientes, estoque, financeiro, notificações, base
  consultada) primeiro pergunta ao RBAC se quem entrou tem a permissão de `*.view` daquele módulo,
  e só então roda suas consultas. Sem permissão o bloco não aparece na tela **e a query não é
  montada** — não existe "esconde o card e consulta igual". Os 12 KPIs, as tabelas e as barras de
  distribuição saem do mesmo caminho, e o `with()` na consulta leva junto o que a tabela mostra —
  card nenhum vira N+1.
- `App\Support\StatusCatalog`, o vocabulário de estados do negócio (ordem, chamado, prioridade,
  técnico, financeiro) com rótulo em português, tom semântico e badge: a mesma palavra não chega
  traduzida em três lugares diferentes da tela.
- `App\Support\Formatters` — `money()`, `decimal()`, `date()`, `dateTime()`, `time()` e
  `TIME_NULL` — para KPI, tabela e agenda exibirem R$ e datas no mesmo formato, e um campo de
  tempo vazio aparecer como `—` em vez de `00:00` ou `1970`.
- Models operacionais que o painel consulta: `ServiceOrder`, `ServiceOrderItem`,
  `ServiceOrderAssignment`, `ServiceOrderStatusHistory`, `ServiceOrderCheckin`, `Ticket`,
  `TicketComment`, `Appointment`, `Technician`, `Team`, `Specialty`, `Product`, `Service`,
  `ServiceCategory`, `StockMovement`, `FinancialRecord`, `Payment` e `Notification`, todos
  passando pelo `CompanyScope`. O saldo de estoque continua derivado das movimentações
  (`CENTRAL_SIGN`/`TECHNICIAN_SIGN`), sem coluna que possa discordar do histórico.
- Painel em si: os doze KPIs em cartões, a fila de ordens dos próximos sete dias, a agenda de
  hoje, os chamados por prioridade, a carteira financeira (a receber, recebido no mês, despesa do
  mês, vencidos), o estoque abaixo do ponto de reposição, quem está em campo, as notificações sem
  leitura e a base consultada. Cada bloco tem estado vazio próprio, escrito com o que a consulta
  realmente procurou — nada de linha fantasma ou zero decorativo.
- `database/seeders/DemoSeeder.php`, a demonstração que valida a fundação sem contaminar a
  operação: roda só em ambiente não-produtivo (recusa produção), cria tudo dentro de uma empresa à
  parte (`nexusfield-demo`) e limpa essa empresa antes de regravar, então rodar de novo não
  duplica. As datas são relativas ao dia em que roda, para o painel ter hoje, ontem e semana em
  andamento. Não é chamado pelo `DatabaseSeeder`; `php artisan db:seed --class=DemoSeeder`.
- `SEED_DEMO_PASSWORD` no `.env.example`: a senha dos usuários de demonstração vem do ambiente; sem
  valor, o seeder gera uma e mostra no console, e nada de credencial entra no repositório.
- `tests/Feature/DashboardTest.php` (4 testes, 130 asserções) contra o MySQL real: os doze KPIs
  batendo um a um com a contagem do banco, bloco sem permissão comprovadamente **sem query** (via
  `DB::listen`, com a salvaguarda de que alguma consulta rodou), empresa sem dados mostrando estado
  vazio em vez de número inventado, e os dados da outra empresa fora do painel.
- `tests/Feature/DemoSeederTest.php` (5 testes, 46 asserções): recusa de produção, dependência do
  catálogo de permissões, nada da demonstração na empresa real (tabelas-filhas contadas pela
  empresa do pai, como o modelo resolve), reexecução sem duplicar e o painel abrindo com o número
  que está no banco. A suíte fecha em 47 testes / 295 asserções.
- Instalação documentada fecha o ciclo: `composer run setup` passou a rodar `db:seed` depois do
  `migrate`, e o `README.md` explica por que esse passo não é opcional (sem permissões semeadas não
  há papel que autorize nada), como o `DemoSeeder` entra no ambiente local e o que cada variável
  nova faz. Antes, o README terminava no `migrate` e a aplicação subia sem catálogo de permissões.
- Conta raiz do sistema: a coluna `users.is_root` (migration própria) marca a identidade
  administrativa permanente do projeto, e a guarda mora no model, não na tela. A conta raiz não
  pode ser excluída (nem lógica nem física), não pode ser desativada, não pode trocar de e-mail nem
  de empresa — `User::booted()` recusa com exceção em português. `is_root` ficou fora de
  `$fillable` de propósito: request nenhum cria uma raiz, ela só nasce do seeder, que escreve a
  coluna por `forceFill()` e num save separado, para o `updating` já valer contra o próprio seeder.
- Domínio absoluto da conta raiz não depende de papel: `permissionSlugs()` devolve o catálogo
  inteiro quando `is_root`, então um `roles()->sync([])` mal-enquadrado não tira o poder de quem
  administra a plataforma. A senha continua rotacionável — sem isso uma credencial vazada ficaria
  presa para sempre, o que é insegurança vestida de imutabilidade.
- `SEED_ADMIN_EMAIL` passa a apontar a conta raiz (`DatabaseSeeder::ROOT_EMAIL`) e o método do
  seeder se chama `seedRootAccount()`; em produção ele segue recusando senha gerada automaticamente,
  e a credencial mora só no `.env`.
- `tests/Feature/RootAccountTest.php` (9 testes, 31 asserções): raiz semeada sem se duplicar,
  `is_root` ignorado em mass assignment, exclusão/desativação/troca de e-mail recusadas, rotação de
  senha seguida de login real pelo `POST /login`, catálogo completo mesmo sem papel, painel com
  todos os blocos, e as guardas valendo **somente** para a raiz — usuário comum continua editável e
  excluível. A suíte fecha em 56 testes / 326 asserções.
- Ilustração técnica no `README.md`: seis capturas reais do aplicativo rodando, em
  `docs/screenshots/` — apresentação pública (claro), entrada (escuro), recuperação de acesso
  (claro), painel operacional nos dois temas a 1440px e o painel num celular de 390px. Tiradas por
  Chrome em modo headless contra o servidor de desenvolvimento, entrando pelo formulário de
  verdade, sem mockup nem tela desenhada à mão. A legenda diz o que é dado de demonstração
  (`DemoSeeder`) e o que é empresa real, para a galeria não virar promessa de módulo que ainda não
  existe.
- `README.md` agora responde por que não existe tela de cadastro: cada usuário nasce preso a uma
  empresa e a um papel, então auto-cadastro teria que criar empresa, escolher plano e nomear o
  administrador na mesma operação. O texto diz quais contas existem hoje (raiz pelo seeder padrão,
  quatro pelo `DemoSeeder`) e não promete a tela de usuários, que é fase própria.
- Chave de tema em pílula: trilha com knob deslizante que carrega o sol ou a lua, posição e ícone
  decididos por CSS em `[data-bs-theme]` — o mesmo atributo que o `x-script.theme` já escreveu antes
  da primeira pintura, então o botão nasce certo sem esperar o JavaScript. `theme.js` passou a
  sincronizar `aria-pressed` (leitor de tela sabe em qual tema está) e o movimento respeita
  `prefers-reduced-motion`.
- Mostrar/ocultar senha nos campos de senha. `x-ui.input` com `type="password"` ganha um invólucro
  `.nf-password` e um `type="button"` sobreposto; `resources/js/nexusfield/passwords.js` só troca o
  `type` do próprio campo, devolve o foco e restaura a seleção do usuário. Nada de espelhar valor,
  nada de gravar em `localStorage`, e nada de esconder no `blur` — quem confere a senha costuma
  voltar para corrigir um caractere. O `is-invalid` vai junto no invólucro para a mensagem de erro
  continuar irmã do campo sem reescrever a regra do Bootstrap.
- Rodapé público em três camadas: identidade com a marca e a promessa operacional, selos das
  capacidades que o projeto já tem hoje (isolamento por empresa, permissões por papel, trilha de
  auditoria e o fuso real de `config('app.timezone')`) e a navegação — que mostra **Painel** para
  quem já entrou em vez de um link de entrada que só devolveria a pessoa ao painel. O pé
  autenticado ganhou o chip com o nome da empresa da sessão, lido do banco.
- `--nf-header-height` como token único de altura do topo: navbar autenticado, faixa da marca na
  sidebar e cabeçalho público fecham nos mesmos 52px (eram ~64 no público e a faixa da marca não
  alinhava com a barra). O cabeçalho de conteúdo também baixou.
- `tests/Feature/PasswordFieldTest.php` (5 testes): o `<input>` nasce `password`, o botão é
  `type="button"` (um clique nunca envia o formulário) e aponta para o campo certo por
  `aria-controls`, senha digitada não ecoa em `old()`, redefinição tem um botão por campo de senha
  e a recuperação de acesso não ganha botão onde não há senha. Lido pelo DOM, não por busca de
  string solta.
- `tests/Unit/UserInitialsTest.php` (6 testes) e uma asserção nova no `AuthenticatedLayoutTest`
  prendendo o contrato `[data-nf-theme-toggle]` entre Blade e JavaScript, mais o chip da empresa no
  rodapé. A suíte fecha em 68 testes / 356 asserções.
- `tests/Unit/ScreenshotsTest.php` (4 testes / 34 asserções) prende a vitrine do README ao
  repositório: as seis capturas existem, cada `<img>` tem o próprio arquivo como link de tamanho
  cheio, todas medem 1440×900 lidos do cabeçalho IHDR do PNG, e a descrição de cada uma diz o tema
  que a imagem realmente mostra. Uma tela que sai do ar sem trocar a captura agora quebra a suíte.
  A suíte fecha em 77 testes / 575 asserções.
- Marca oficial do produto no repositório: `public/img/marca-nexus-64.png` (o medalhão recortado do
  círculo, servido no cabeçalho público, na faixa da sidebar, no rodapé e no favicon PNG) e
  `docs/branding/` com o medalhão em 512px e a arte completa aparada. O recorte não é redesenho: o
  círculo foi achado pela maior corrida contínua de pixels opacos na faixa do medalhão, e os quatro
  tons da rampa (`--nf-brand-deep` `#08410d`, `--nf-brand-core` `#1f7328`, `--nf-brand-vivid`
  `#62bc60`, `--nf-brand-mint` `#c9f9b6`) são os percentis 2% / 35% / 70% / 95% da luminância dos
  pixels verdes medidos no arquivo original.
- `public/favicon.ico` de verdade, com 16, 32 e 48 pixels do mesmo medalhão embutidos em PNG, e o
  `<link rel="icon">` correspondente em `x-site-head` — antes o arquivo existia zerado e o navegador
  não desenhava nada na aba.
- `test_a_marca_e_verde_como_o_medalhao_e_nao_a_teal_anterior` em `tests/Unit/PaletteTest.php`:
  mais 29 hexes na lista de veto (a marca teal e o bronze antigos, com todos os derivados) e a
  exigência de que o canal verde da marca vença vermelho e azul nos dois temas. A suíte fecha em
  78 testes / 621 asserções.
- Fase 9 — clientes no ar, do formulário ao CSV. `ClientController` lista com busca (nome, razão
  social, documento, e-mail), filtro por situação — ativo, inativo e o painel dos excluídos
  temporariamente, que usa `onlyTrashed()` — filtro por cidade lida do próprio banco de endereços
  da empresa, ordenação presa numa lista de colunas permitidas e paginação própria (`por_pagina`
  de 10 a 100, `page`, `withQueryString` e a contagem do banco escrita na tela, não o tamanho da
  página). `clients/form.blade.php` cadastra e edita sobre o mesmo Blade, com `data-nf-guard` no
  lugar de diálogo nativo; `clients/show.blade.php` é a ficha: cadastro, observações, o que o
  cliente já gerou (ordens, chamados, compromissos e lançamentos contados no MySQL) e as duas
  relações aninhadas editáveis na própria tela — contatos e endereços, com um único namespace de
  campos por formulário (`contato_*`, `endereco_*`) para que um `old()` não invada o outro, e
  edição inline por `?editar_contato=<id>`, que funciona sem JavaScript.
- Primitivas de UI reutilizadas em todo o módulo: `x-ui.filters` (busca com autossubmit, situação,
  cidade e ordenação), paginação com o seletor de itens por página, `x-ui.contact-fields` e
  `x-ui.address-fields` — estes dois prontos para os endereços polimórficos de técnicos (fase 10)
  e do check-in (fase 15) — e o trait `TrataRegistrosAninhados`, que obriga o filho a pertencer ao
  cadastro aberto antes de aceitar `update`/`destroy`. Contato principal é único por cliente, o
  servidor rebaixa os outros; endereço principal idem.
- Exportação CSV de verdade: `clients/exportar` honra a busca e a situação que estão na tela,
  atravessa a empresa em lotes de 200 com `lazyById`, escreve BOM (para o Excel abrir acento
  direito), separa por `;` e nunca leva registro da empresa ao lado.
- Excluir quem já trabalhou é recusado no servidor, com o motivo no toast: a tela explica que o
  caminho honesto é inativar. `tests/Feature/ClientsTest.php` (15 testes / 72 asserções) cobra
  isolamento de tenant na lista, na ficha, na exportação e pela URL direta, as três situações, a
  paginação, a unicidade de documento por empresa, a posse morfológica dos registros aninhados, a
  matriz de permissões (técnico e cliente papéis reais levam 403 em criar, editar, excluir e
  exportar) e a exclusão recusada. A suíte fecha em 93 testes / 697 asserções.
- Fase 10 — a escala inteira no ar. `TechnicianController` lista com busca (nome, documento, e-mail,
  telefone e região), filtro por situação do técnico — disponível, ocupado, folgado, inativo e o
  painel dos excluídos, de novo com `onlyTrashed()` —, filtro por região e por especialidade lidos do
  próprio banco, ordenação presa em lista de colunas permitidas e a mesma paginação própria da fase 9.
  A ficha (`technicians/show.blade.php`) mostra o cadastro, o que o técnico já gerou (ordens, check-ins
  e compromissos contados no MySQL), as especialidades, as equipes com a data de entrada e de saída do
  quadro, a base de trabalho com endereços editados em linha e os últimos check-ins medidos em campo.
- `TeamController` monta o quadro com líder, região e situação. A saída de um membro não apaga a linha
  de `team_members`: grava `left_at` e deixa `joined_at` de pé, então "quem estava aqui em março"
  continua respondível; tirar o líder do quadro deixa a equipe sem liderança e preserva a ficha dele.
  Excluir equipe exige quadro vazio, e a listagem conta no SQL só quem ainda está dentro.
- `SpecialtyController` é o catálogo curto: o slug nasce do nome (`Str::slug`), a lista mostra quantos
  técnicos cada especialidade cobre e a exclusão é recusada enquanto houver alguém usando-a.
- Endereço deixou de ser coisa só de cliente: `TemEnderecos` (relação polimórfica mais o endereço
  principal) e `CuidaDeEnderecos` (criar, editar, remover e garantir um único principal) agora servem
  cliente e técnico pelo mesmo caminho, cada controlador com três métodos de uma linha. `EmEdicao`
  tirou da tela de cliente a cópia do "qual registro está em edição pelo query string".
- Um técnico pode nascer ligado a uma conta de usuário, e o servidor não deixa ligar dois técnicos à
  mesma conta nem escolher conta de outra empresa: `Rule::exists` e `Rule::unique` ambos com a cláusula
  de `company_id`.
- `tests/Feature/TechniciansTest.php` (13 testes / 82 asserções) cobre o isolamento na lista, na ficha,
  no endereço e no quadro de equipe, os filtros de situação, região e especialidade, a paginação com a
  contagem do banco, a exclusão recusada quando há ordem e aceita quando não há, o `left_at` do quadro
  com a liderança que se desfaz, a especialidade que protege quem a usa e a matriz de permissões dos
  papéis.
- Fase 11 — o catálogo no ar. `ServiceController` (`/servicos`) lista com busca (nome e código),
  filtro por situação — ativas, inativas e o painel dos excluídos com `onlyTrashed()` — e por
  categoria, ordenação presa em `ORDENAVEIS`, paginação própria e colunas de uso lidas do banco
  (`service_orders_count` e `items_count`) para a pessoa saber o que está mexendo antes de inativar.
  O preço é string decimal no MySQL e sai formatado por `Formatters::money`; a duração estimada, em
  minutos, passa por `Formatters::duration`, que diz "45 min", "1 h" ou "1 h 30 min" e "—" quando
  ainda não foi preenchida — é ela que a agenda da fase 14 vai reservar.
- `ProductController` (`/produtos`) acrescenta dois filtros que não existem em nenhum outro lugar do
  projeto: situação de estoque (`abaixo` / `ok`) e unidade. A unidade aceita só o que está em
  `Product::UNIDADES`, e o seletor mostra apenas as unidades em uso pela empresa, contadas num
  `distinct` do próprio banco. O saldo central não é coluna da tabela: `scopeWithCentralBalance`
  calcula por subconsulta a soma sinalizada de `stock_movements` com `StockMovement::CENTRAL_SIGN`,
  e `belowReorderPoint()` / `atOrAboveReorderPoint()` repetem a expressão crua no `whereRaw`, porque
  o MySQL recusa alias de SELECT dentro do WHERE.
- `ServiceCategoryController` (`/categorias-de-servico`) é o grupo curto do catálogo: o slug nasce do
  nome em `Str::slug`, a lista conta os serviços de cada categoria e do catálogo inteiro numa
  consulta só, e excluir recusado enquanto houver serviço no grupo. As rotas herdam as permissões de
  `services.*`, porque quem cuida do serviço cuida do grupo dele.
- A sidebar ganha a seção "Catálogo" (Serviços, Produtos, Categorias de serviço) entre a escala e o
  que ainda não tem rota. Quem não tem `services.view` nem `products.view` não vê a seção: o
  `Navigation` filtra por permissão antes de montar o item.
- Ficha honesta dos dois lados. `services/show` mostra o cadastro, o escopo, quantas ordens e itens
  já cobraram aquele serviço e as últimas ordens em que ele apareceu; `products/show` mostra o
  cadastro, a margem (preço menos custo, calculada na tela a partir dos dois campos do banco), o
  saldo central com o ponto de reposição e o movimento a movimento com o efeito real no estoque —
  incluindo o ajuste, que carrega o sinal na própria quantidade e por isso aparece com `+` ou `−`
  conforme o número, não conforme o tipo.
- Ninguém digita saldo: a tela de produto diz em letras que o estoque só nasce da primeira
  movimentação, e a própria página de edição não tem campo nenhum para `central_balance`.
- `tests/Feature/CatalogTest.php` (11 testes / 148 asserções) cobre isolamento de tenant em serviço,
  produto e categoria, os três filtros do serviço e os quatro do produto, a paginação com a contagem
  do banco, nome e SKU únicos dentro da empresa (e repetíveis na empresa ao lado), categoria de outra
  empresa recusada, unidade fora do catálogo recusada, slug automático e duplicado de categoria, o
  saldo central somado tipo a tipo (`compra 10 − carga 4 − consumo 2 − ajuste 1 = 5`), o filtro
  `estoque=abaixo`, a exclusão vetada quando o registro já cobrou ou já foi movimentado, a restauração
  e a matriz de permissões: técnico e cliente levam 403 em tudo do catálogo, o funcionário lê mas não
  escreve, e o supervisor escreve sem poder excluir. A suíte fecha em 117 testes / 944 asserções.
- Fase 12 — a operação no ar. `OrderController` (`/ordens`) lista com busca (número, título, descrição
  e endereço), estado, prioridade, técnico, cliente e janela de agendamento, ordenação presa em
  `ORDENAVEIS` (`number`, `title`, `priority`, `status`, `scheduled_starts_at`, `scheduled_ends_at`,
  `created_at`), paginação própria e exportação CSV pela mesma `consulta()` da tela — inclusive os
  filtros, porque `export` chama o mesmo método com o mesmo `Request`. O número não é digitado:
  `ServiceOrder::proximoNumero()` acha a sequência do ano dentro da empresa num `DB::transaction` com
  `lockForUpdate` sobre o `max` da própria tabela, então `OS-2026-0007` é seguido por `OS-2026-0008` e a
  empresa ao lado tem a sequência dela.
- A ordem é documento, não vista do cadastro: ao criar, o endereço do cliente é copiado para as colunas
  da ordem (rua, número, complemento, bairro, cidade, UF, CEP e as coordenadas) e o fim previsto sai do
  `estimated_minutes` do serviço quando ninguém o digitou. Mudar o cadastro do cliente depois não
  reescreve ordem nenhuma — é o que estava combinado naquele dia que vale.
- Estado é máquina: `ServiceOrder::FLUXO` decide o caminho (`rascunho → aberta → em execução →
  concluída`, com `em espera` e `cancelada` nos pontos que fazem sentido), `podeMudarPara()` recusa o
  salto na tela e no servidor, e `mudarStatus()` grava a passagem em
  `service_order_status_history` com origem, destino, autor e nota. Cancelar sem motivo é recusado —
  "Cancelar uma ordem sem registrar o motivo não é cancelamento." A tela de edição não oferece seletor
  de estado, e o servidor ignora quem enviar um pelo formulário: carimbo é pelo botão, que registra quem
  fez e quando.
- `orders.approve` separa quem tira um rascunho do papel e quem cancela ordem de quem só executa;
  `orders.execute` e `orders.update` convivem no middleware (`permission:orders.update,orders.execute`),
  porque o técnico que mexe na própria fila não tem a permissão de edição do escritório. A matriz está
  em `PermissionCatalog`: supervisor aprova e exporta sem poder executar nem excluir; funcionário cria,
  edita e executa sem aprovar; técnico só vê, executa e edita a dele; cliente lê.
- Linha cobrada é foto do preço. `OrderItemController` (`POST/PUT/DELETE /ordens/{ordem}/itens`)
  congela descrição, quantidade, valor unitário e desconto na linha, e o `CHECK` de
  `service_order_items` (serviço **ou** produto, nunca os dois, nunca nenhum) é respondido por validação
  antes de virar erro de SQL. O desconto da linha tem teto dinâmico (`quantidade × unitário`) e o da
  ordem tem teto no líquido dos itens; alterar um item não muda o total já cobrado de outra ordem.
  Ordem concluída ou cancelada não aceita linha nova — a tela some com o formulário e o servidor repete
  a recusa.
- Total, bruto e descontos são contas do MySQL: `scopeWithTotals()` soma os itens por subconsulta
  correlacionada (`somaBrutaQuery`/`somaLiquidaQuery`) em vez de `join`+`groupBy`, que quebraria a
  paginação da listagem. `totais()` lê os aliases quando a linha veio da listagem e refaz a soma quando a
  ficha carrega a relação — duas origens, um número, e a tela de lista, a ficha e o CSV concordam.
- Quadro de comissão: `OrderAssignmentController` amarra técnico à ordem com data, autor e nota; sair
  do quadro marca `left_at` e preserva a passagem (o `pivot` com `withPivot(['joined_at','left_at'])`
  continua na tabela); o técnico que estava no quadro pode assumi-lo e a linha antiga é reaberta em vez
  de duplicada. Liberar o responsável da ordem só muda o `technician_id` quando não sobra ninguém ativo, e
  um aviso ("já está no quadro") impede a segunda linha sem apagar a primeira.
- Alcance é consulta, não filtro de UI: `ServiceOrder::scopeVisiveisPara()` dá ao técnico a própria fila
  (pela coluna **e** pelo quadro de comissão), à conta de cliente a carteira dela (por
  `users.client_id`, migration `2026_10_08_000002_add_client_id_to_users_table`) e ao escritório a
  empresa. `alcanceRestrito()` troca a legenda da tela e esconde os seletores de técnico e cliente de
  quem não pode usá-los. A prova do alcance é o número na tela: no mesmo painel, o técnico conta 1
  ordem aberta e o escritório conta 2, porque a KPI sai do mesmo `visiveisPara` da listagem.
- Ficha (`orders/show`) com o que a ordem é: identificação e local, conta com as linhas e os três
  totais, seletor dos estados disponíveis, cronologia das passagens, quadro de comissão, check-ins do
  campo (somente leitura — quem escreve é a fase 15) e o "O que esta tela não pergunta" que explica de
  onde vêm número, linhas e estado. `x-ui.action-form` em cada remoção, sem diálogo nativo nenhum.
- `tests/Feature/OrdersTest.php` (16 testes / 270 asserções) cobre isolamento de tenant na lista, na
  ficha, no estado e nos itens; o técnico e a conta de cliente vendo só o que é deles (inclusive 404 na
  ordem da carteira ao lado); a sequência anual por empresa; a edição sem seletor de estado e o `status`
  enviado por fora ignorado; a cópia do endereço e o fim previsto de 90 minutos; carimbo com passagem
  registrada; o salto recusado e o cancelamento sem motivo; `orders.approve` barrando rascunho e
  cancelamento; a linha que congela R$ 1.200,00 depois de o catálogo mudar o preço para R$ 999, e a soma
  `2.400 + 54 = 2.454 bruto`, `2.448 líquido`, `2.000 a cobrar` depois do desconto da ordem; a origem da
  linha não trocável; ordem encerrada recusando linha e quadro; o quadro preservando passagem e
  repassando responsabilidade; exclusão só do rascunho; filtros, ordenação, paginação e o CSV sendo a
  mesma contagem da tela; o painel do técnico não contando a fila alheia; e a matriz de permissões. A
  suíte fecha em 133 testes / 1201 asserções.
- Marca da plataforma nos navegadores: o medalhão da logo virou `public/favicon.ico` (16 e 32
  embutidos, cada um como PNG dentro do contêiner) e as resoluções que a interface realmente pede —
  `marca-nexus-32`, `-64`, `-128` e `-192`, mais `apple-touch-icon.png` achatado sobre preto porque o
  iOS descarta a transparência. `x-site-head` entrega os três links e `x-ui.brand-mark` ganhou
  `arquivo`/`lado`, para cada ponto da interface chamar a resolução nativa em vez de esticar o mesmo
  PNG de 64px. A cor da barra do celular continua saindo do `x-script.theme`, por tema — não de um
  `theme-color` fixo que mentiria para metade dos acessos.
- Auto nível nas telas abertas: `x-ui.brand-mark` aceita `animacao`, a boas-vindas ganhou o palco
  `.nf-brand-stage` com a logo inteira (`img/logo-nexus-480.png`) pendurada no próprio topo, e as três
  telas de acesso repetem o gesto em amplitude de relógio acima do título. O movimento é CSS
  (`@keyframes nf-auto-nivel` e `nf-nivelando`), não GIF, e o motivo é do projeto: o medalhão é
  gradiente com borda suave, e um GIF o quantizaria em 256 cores com transparência de 1 bit — além de
  não acompanhar o tema, já que esta mesma tela hoje é preta ou branca. Em troca, o halo acende no
  instante em que a marca assenta (`nf-acender`), um fio claro em volta do desenho devolve o "FIELD"
  que sobre o preto era ilegível, e quem pede movimento reduzido recebe a logo parada e nivelada pela
  regra global de `base.css`.
- Troca de identidade da conta raiz no schema: `2026_10_08_000004_replace_root_account_email.php`
  renomeia a linha em vez de criar outra, porque o `id` da raiz é o que papéis, auditoria e
  notificações penduram — identidade nova com histórico órfão seria só outro bug. A migration não
  encosta em credencial alguma (senha continua só no `.env`), passa direto quando o endereço antigo já
  não existe, e recusa em vez de escolher quem perde o índice único quando o endereço novo já está
  ocupado. `down()` lança exceção: desfazer recolocaria no sistema o endereço aposentado.
- `DatabaseSeeder::RETIRED_ROOT_EMAIL` e a recusa do seeder de semear raiz nele. Como
  `SEED_ADMIN_EMAIL` manda no endereço, sem essa guarda um `.env` desatualizado ressuscitaria por
  `db:seed` exatamente a identidade que saiu do sistema por medida de segurança.
- Guarda que faltava no `User::booted()`: a conta raiz não pode **desligar** a própria bandeira.
  `is_root` já era fora de `$fillable`, mas `forceFill(['is_root' => false])` deixava a linha sem
  proteção no save seguinte — e aí o `deleting` já não barrava nada. O seeder só liga a bandeira,
  então continua passando por aqui.
- Sete casos de teste na identidade raiz: `tests/Feature/RootAccountEmailSwapTest.php` (renomeia com o
  mesmo `id`, roda duas vezes sem dobrar, passa em banco vazio, recusa conflito e rollback recusado) e
  mais dois em `RootAccountTest` — a raiz que não deixa de ser raiz e o seeder que recusa o endereço
  aposentado, com `tearDown` devolvendo o ambiente para a classe seguinte não herdar o endereço
  recusado.
- Fase 13 — a voz do cliente no ar. `TicketController` (`/chamados`) lista com busca (protocolo, assunto,
  descrição e nome do cliente), estado, prioridade, categoria, técnico, cliente, prazo e janela de
  abertura, ordenação presa em `ORDENAVEIS` (`protocol`, `subject`, `status`, `priority`, `opened_at`,
  `resolved_at`, `created_at`), paginação própria e exportação CSV por `tickets.export` saindo da mesma
  `consulta()` da tela — inclusive o alcance de quem pede, para o CSV não entregar mais do que a pessoa
  vê.
- O protocolo nasce no banco, não na mão: `Ticket::proximoProtocolo()` acha `CH-2026-0007` pelo `max()`
  da sequência do ano **dentro da empresa**, em `DB::transaction` com `lockForUpdate` — a empresa ao lado
  continua em `CH-2026-0001` e dois chamados simultâneos não colidem.
- Prioridade é prazo, não cor: `Ticket::PRAZO_HORAS` (urgente 4h, alta 8h, média 24h, baixa 48h, sem
  pressa 72h) grava `prazo_em` na abertura e de novo em toda mudança de prioridade, e "atrasado" é o
  prazo vencido sobre um estado que ainda não encerrou. Reaberto volta a correr: `mudarStatus()` limpa
  a data de resolução e refaz o prazo a partir de agora. O painel lê o mesmo `prazo_em`, por isso a
  gravação dá `Cache::forget('nf.prazo-chamado.{id}')` — sem isso o KPI de atrasados continuaria
  contando um chamado que a tela já mostrou resolvido.
- Passagem de estado com dono e data: migration
  `2026_10_08_000003_create_ticket_status_history_table.php` (o schema passa a ter 19 migrations) cria
  `ticket_status_history` com `from_status`, `to_status`, `note` e `created_at` carimbado pelo model — o
  `timestamps = false` existe porque as duas colunas de tempo da tabela são a data do carimbo e nada
  mais. Abrir o chamado já escreve a primeira linha, então a ficha nunca mostra "ninguém moveu isso".
- `tickets.execute` é a nova permissão de conduzir a máquina de estados, e ela separou duas coisas que
  estavam misturadas na rota: antes `/chamados/{id}/estado` pedia `tickets.update` **ou** `tickets.close`,
  então o técnico — que tem a primeira — podia resolver um chamado, e a conta de cliente, que não tem
  nenhuma das duas, ficava sem o passo do dia a dia. Agora escritório, funcionário e técnico conduzem
  (`in_progress`, `waiting`), e resolver e fechar continuam pedindo `tickets.close` dentro do
  controlador. O catálogo (`PermissionCatalog::MODULES`), o middleware da rota, `proximosEstados()` e a
  ficha (`podeMover`) concordam entre si — 65 permissões no banco, e a matriz testada papel por papel.
- Conversa do chamado: `TicketCommentController` (`POST /chamados/{chamado}/notas`). Responder é
  continuar uma conversa que se tem o direito de ler, então a rota está em `tickets.view` e não em
  `tickets.create` — a conta de cliente não cria documento nenhum, ela fala na própria carteira, e o
  `client_id` da nota vem da sessão, não do formulário. A marca de nota interna (`is_internal`) só é
  aceita de quem tem `tickets.update`, e a consulta da ficha deixa essas notas fora do resultado para
  quem não pode lê-las: não é filtro de CSS, é a query que não as busca.
- `App\Support\TextoSeguro` é o que separa o HTML do editor de virar XSS estocado. A regra é lista
  fechada: tag que não está em `PERMITIDOS` é desembrulhada (a palavra fica, o rótulo sai), atributo que
  não está na lista da tag desaparece, `<script>`, `<style>`, `<iframe>`, `<img>`, `<form>` e companhia
  caem junto com o que têm dentro, e `href` só sobrevive em `http`, `https`, `mailto`, `tel` —
  `javascript:`, `#` e relativo são recusados, e o link que passa ganha `rel="noopener nofollow"`. O
  prefixo `<?xml encoding="utf-8" ?>` existe de propósito: sem ele o parser do libxml lê o corpo como
  CP1252 e come os acentos do texto.
- Summernote 0.9.1 entrou em uso real no bundle que já estava declarado: `x-ui.editor` é uma textarea
  com o mesmo contrato de rótulo, ajuda e erro do `x-ui.textarea`, e `resources/js/nexusfield/editor.js`
  a monta por cima dela (`Nf.editor`) com barra reduzida, altura configurável, botão de tela cheia e
  `codeview`. Sem JavaScript — ou com `prefers-reduced-motion` — atextarea aparece como área de texto
  comum e o formulário envia do mesmo jeito; é por isso que a peça é `textarea` e não `div`.
- `components.css` ganhou as 126 linhas que o módulo pedia: `.nf-conversa` e `.nf-conversa-bloco` (a
  conversa com autor, data e marca de interna), `.nf-assunto`, `.nf-chamado-topo`, `.nf-legenda-arquivo`
  e `.nf-form-aviso`, mais o acerto de altura que o Summernote precisa para não cortar a própria barra.
- Alcance do chamado é o mesmo das ordens, com `EnxergaOChamado` no meio do caminho: o técnico responde
  aos atribuídos a ele, a conta de cliente à carteira dela (`users.client_id`), e o escritório à empresa
  inteira. A ficha de um chamado que não é seu responde 404 — não 403 com aviso de "existe, mas você não
  pode" — e a listagem, a exportação e o `withCount` das notas seguem a mesma `consulta()`.
- `DemoSeeder` passou a desenhar o módulo inteiro: 11 chamados com conversa real, notas internas e a
  trilha de estados percorrida (22 notas e 27 passagens no banco local), incluindo um urgente estourado
  no prazo para a lista de atrasados ter o que mostrar.
- `tests/Feature/TicketsTest.php` (10 testes / 141 asserções) cobre o protocolo que não repete o da
  empresa ao lado, o prazo calculado da prioridade, o atraso medido pelo banco, o estado que só anda
  pelo fluxo, a nota obrigatória para resolver e para fechar sem resolução, o 403 de quem conduz sem
  `tickets.execute`, a nota interna que não chega a quem não pode ler, o payload malicioso que chega
  inteiro na requisição e volta limpo do banco, o alcance do técnico e da conta de cliente, e a matriz de
  permissões do módulo. A suíte fecha em 150 testes / 1386 asserções.
- Fase 14 — a escala da empresa num quadro só. `AgendaController` (`/agenda`) abre dez rotas e cada
  uma tem a porta certa: `agenda.view` para o calendário, para o feed JSON e para a ficha;
  `agenda.create` para `agenda/nova` e o `POST`; `agenda.update` para editar, mudar estado, reagendar
  e segurar o arraste; `agenda.delete` para riscar a janela. O técnico tem `agenda.view` apenas, e por
  isso recebe um quadro que não aceita dedo — a tela diz em português que ela lê a agenda e que
  remarcar é de quem conduz a escala, e o cursor combina com a verdade.
- Duas naturezas chegam num pedido só, porque o dia de um técnico é feito das duas. O **compromisso**
  é o que esta tela escreve. A **ordem de serviço agendada** (`open`, `in_progress`, `on_hold`,
  `completed`) é lida de `scheduled_starts_at`/`scheduled_ends_at` da própria ordem, desenhada
  hachurada, com a ficha como destino e `editable: false` — esconder a ordem deixaria o calendário
  bonito e inútil, e movê-la daqui tiraria o motivo da trilha da ordem, que é onde ela se move.
- O feed filtra antes de montar qualquer evento, então não existe lista escondida por CSS:
  `Appointment::visiveisPara()` decide o que sai do banco, e o `CompanyScope` já nem deixa a empresa
  ao lado aparecer. A janela pedida tem teto (`JANELA_MAXIMA_DIAS = 120`): `?fim=+10 anos` digitado na
  URL é recusado em vez de virar varredura na tabela, e início/fim são obrigatórios — o calendário não
  pergunta "tudo".
- Alcance é o mesmo da casa, aplicado em três camadas que se concordam: `alcanceRestrito()` decide se
  o filtro de técnico existe na tela, `visiveisPara()` decide a query, e `garantirVisivel()` responde
  403 quando a URL pede a ficha de uma janela que não é do usuário. O compromisso também alcança pelo
  que ele prende — se a ordem agendada é do técnico, a janela da ordem é dele mesmo sem
  `technician_id` preenchido, e a conta de cliente lê apenas o que é da carteira dela.
- Arrastar é a única escrita no quadro, e ela passa pelo servidor (`PATCH /agenda/{id}/janela`): o
  controlador confere permissão, alcance, estado e janela antes de gravar, a tela reabre o feed depois
  da resposta e mostra o que está no banco, não o que o dedo soltou. Se o servidor recusa,
  `info.revert()` devolve o evento ao lugar de origem com o motivo no toast. O arraste simples manda
  só o novo início, e a duração que estava gravada é recalculada do banco; o resize manda os dois
  lados.
- Concluído é fato passado. `Appointment::FLUXO` (agendado → concluído/cancelado, cancelado volta a
  agendado, concluído sem saída) é a única maneira de o estado andar, `estaTravado()` fecha a ficha e
  o feed nem oferece o arraste que a regra recusaria. `PUT /{id}/estado` exige o destino em `FLUXO`
  **e** na lista de quem conduz, com o rótulo dos dois lados na mensagem.
- Dia inteiro nas duas pontas: o banco grava `dura_o_dia_todo`, o FullCalendar trata o fim como
  exclusivo, então a leitura empresta um dia e a escrita devolve os 864e5 ms antes de gravar. Mover um
  compromisso de dia inteiro desloca dias inteiros e preserva a hora gravada; mudar a hora de um
  compromisso com horas é exatamente isso, e a validação continua aceitando início e fim no mesmo dia
  quando o dia é inteiro.
- O relógio é de parede, não de fuso: `APP_TIMEZONE` é `America/Sao_Paulo` e o MySQL atende no mesmo
  sistema, então a janela viaja em `Y-m-d\TH:i:s` sem offset, dos dois lados. Se o JavaScript mandasse
  ISO com `Z`, a escala de um técnico de São Paulo chegaria três horas mais cedo no servidor.
- `DELETE` risca a janela e não encosta no trabalho: a ordem e o chamado continuam donos do que
  acontece e do que se cobra, e apagar um compromisso do calendário não move nem fecha nada lá.
- Filtro que não é permitido não é aplicado: `opcaoPermitida()` descarta o `?tecnico=` de outra
  empresa, de um técnico inativo e o `?tipo=` fora do catálogo, em vez de fechar a query com o valor
  digitado — e a regra de validação do `technician_id` exclui o inativo pela mesma lista que o select
  mostra, para a tela não oferecer o que o servidor recusa. `data-filtro` chega ao JavaScript sempre
  como objeto (`{}` quando não há filtro), que é o contrato que `lerFiltros()` assina.
- Marcar a janela de uma ordem ou de um chamado herda o cliente daquele documento
  (`normaliza()`), e a conta de cliente tem o `client_id` imposto da sessão: o payload que trouxer uma
  carteira alheia recebe 422 dos vínculos, não uma linha escrita na agenda errada. `company_id` do
  formulário não existe para o gravador.
- FullCalendar 6.1 entra como chunk carregado sob demanda — `app.js` só importa
  `nexusfield/agenda.js` quando a tela tem um `[data-nf-agenda]`, então o calendário não pesa em
  nenhuma outra página. Locale `pt-br`, `nowIndicator`, madrugada visível mas a faixa aberta às 07:30,
  mês/semana/dia/lista sobre o mesmo feed, e celular caindo em `listWeek`. Sem JavaScript a casca diz a
  verdade em vez de desenhar um quadro vazio: os números continuam no banco e o painel mostra a agenda
  de hoje.
- `resources/css/nexusfield/agenda.css` (302 linhas) reveste a biblioteca só por variáveis: o `--fc-*`
  do FullCalendar é alimentado por `var(--nf-*)` e `color-mix()`, sem um hex escrito — é o que o
  `PaletteTest` vigia. A legenda das cores lê o tom do catálogo de estados, o claro/escuro troca o
  quadro sem recolorir nada no JavaScript, e os estados de interface (carregando, vazio, erro) ficam
  do mesmo tecido das outras listagens.
- Painel e calendário na mesma régua: `DashboardMetrics::compromissosDoUsuario()` passou a contar por
  `visiveisPara()`, então quem leu "três janelas hoje" no painel abre a agenda e conta as mesmas três.
  O menu de Operação ganhou a entrada "Agenda" (`agenda.view`, `fa-calendar-days`) e o
  `Auditable` entrou no `Appointment` — criar, mover janela, mudar estado e riscar ficam na trilha com
  autor, IP e hora, como no resto do domínio.
- `tests/Feature/AgendaTest.php` (12 testes / 129 asserções) cobre a tela montada e o escritório lendo
  a empresa inteira, a agenda do técnico saindo do banco só com as janelas dele, a conta de cliente que
  não abre a escala da empresa, a ordem agendada que aparece e não se arrasta da tela, o teto e a
  exigência da janela, o arraste que grava o que o servidor aceita, a janela de um concluído que não se
  move mais, o compromisso que nasce agendado e herda o cliente do que prende, a recusa de fim depois
  do início, o estado que só anda pelo fluxo e por quem conduz, a paridade painel/calendário e o apagar
  que não apaga o trabalho. A suíte fecha em 163 testes / 1542 asserções.
- FASE 15 — presença em campo medida, não declarada. A migration
  `2026_10_08_000005_add_distance_columns_to_service_order_checkins_table.php` acrescenta
  `checkin_distance` e `checkout_distance` (`decimal(8,2)`, nullable) a `service_order_checkins`: a distância
  é consequência de uma coordenada lida, então ela é gravada junto do ponto que a produziu, e as duas
  colunas ficam nulas quando não houve leitura.
- `App\Support\Distancia` calcula a distância sobre a superfície pela fórmula de haversine, no servidor, com
  o raio da Terra como constante nomeada. O projeto não ganhou biblioteca de geo: 400 metros de um endereço
  de assistência técnica não pedem pacote de projeção cartográfica. Coordenada que falta devolve `null` —
  e `null` não vira zero, porque visita sem GPS é visita sem medida, não visita no portão. O `decimal:7` do
  Eloquent devolve string, então `grau()` aceita `mixed` e recusa o que não é número: quem chama não precisa
  saber de onde o valor veio.
- `ServiceOrderCheckin::raioAceito()` lê `company_settings` pela chave `checkin_raio` (constante
  `CHAVE_RAIO`) e cai em `RAIO_PADRAO_METROS = 250.0` quando a empresa não escolheu um ou quando o valor
  gravado não é utilizável; `foraDoRaio()` responde `true`, `false` ou `null`, e o `null` aparece na tela
  como "sem posição lida" em vez de cor de aprovado. O raio é decisão guardada no banco da empresa, não
  número escrito em código — a tela que o edita entra na fase 21.
- `App\Http\Controllers\Orders\CheckinController` responde por chegada, saída, listagem e CSV. A chegada
  está aninhada na ordem (`POST ordens/{ordem}/chegada`) porque é ela que responde pelo endereço medido e
  pelo responsável; a saída é do registro (`PATCH visitas/{visita}/saida`) porque uma ordem pode ter dois
  técnicos em campo ao mesmo tempo. `checkin_at` e `checkout_at` são o relógio do servidor na conta de quem
  loga, `technician_id` vem da ficha de quem loga — ou do responsável da ordem quando quem registra é do
  escritório — e a medida é recalculada no servidor: um request que trouxer técnico, hora ou distância
  forjados recebe a linha que o banco manda, não a que ele pediu.
- Chegada em rascunho e em ordem encerrada são recusadas com a frase que diz o motivo, e um técnico não
  abre segunda passagem na mesma ordem enquanto a dele estiver aberta. Abrir a execução pelo check-in passa
  por `podeMudarPara('in_progress')` + `mudarStatus()`, dentro da mesma transação da passagem, com nota na
  trilha — não é um `update` que troca a coluna por fora do fluxo. A saída encerra a passagem e não encerra
  a ordem: ir embora não é terminar o serviço, e concluir continua sendo decisão com estado, nota e quem
  aprova.
- Validade da coordenada antes do byte: `latitude`/`longitude` pedem `nullable numeric between:-90,90`
  (resp. ±180) e `decimal:0,7`, com `required_with` impedindo meio par; `observacao` tem teto de 500
  caracteres. A saída aceita relato novo e, quando o campo volta vazio, preserva o da chegada em vez de
  riscá-lo.
- Autorização por rota, com o alcance respondendo primeiro: listar é `orders.view`, o CSV é `orders.export`,
  registrar a chegada é `orders.update,orders.execute` e encerrar passagem é `orders.execute,orders.approve`
  — o supervisor responde pela ordem e precisa fechar a visita que ficou aberta num aparelho sem bateria.
  `garantirVisitaVisivel()` devolve 404 para quem não alcança a passagem (inclusive a da empresa ao lado)
  antes de qualquer comparação de papel, e `garantirResponsavel()` devolve 403 para quem alcança mas não
  conduz. Na chegada, quem não tem ficha de técnico recebe a resposta do controlador, não um registro órfão.
- Tela `/visitas` ("Visitas de campo", `fa-location-crossing`, no menu de Operação) com o alcance de quem lê
  antes dos filtros: escritório conta a empresa inteira, técnico só as próprias passagens — e nem vê o
  seletor de técnico nem o de cliente —, conta de cliente só os técnicos da carteira dela. Período, técnico,
  cliente, situação, `?sem_posicao=1` e busca por número de ordem ou nome passam pelos `ListFilters`, com a
  paginação própria e o caption que declara o raio aceito por aquela empresa. O CSV é a mesma consulta, com
  "sem posição" e "sem medida" escritos onde não houve leitura.
- Card "Check-in de campo" na ficha da ordem: o formulário só aparece para quem pode registrar
  (`$podeRegistrar`), entrega raio e coordenada do endereço como `data-*`, lê a posição no aparelho pelo
  botão "Ler posição do aparelho" (`resources/js/nexusfield/checkin.js`) e mostra "Em campo desde …" com a
  ajuda de que sair do local não encerra a ordem. Sem JavaScript o mesmo formulário continua gravando a
  presença — só que sem medida, e dizendo isso na linha. As passagens antigas ficam listadas com entrada,
  saída, duração, distância, coordenadas e relato.
- `DashboardMetrics` ganhou o cartão "Técnicos em campo agora", contado em SQL sobre as passagens abertas
  dentro do alcance de quem lê, e o `DemoSeeder` agora grava o raio da empresa de demonstração (300 m) e
  produz 14 visitas com desvio medido de verdade — uma fora do raio (390,98 m), uma sem GPS e o resto
  dentro —, porque tela de check-in sem visita registrada é tela que não demonstra nada.
- `tests/Feature/CheckinsTest.php` (10 testes / 175 asserções) cobre o request que tenta forjar técnico,
  hora e distância, a posição ausente que não vira zero, a saída que fecha a passagem sem tocar na ordem,
  o rascunho / a encerrada / a passagem já aberta que recusam, o alcance de supervisor, funcionário, técnico
  e conta de cliente na chegada e na saída, a tela e o CSV por conta de cada papel (inclusive a visita da
  empresa ao lado, que é 404), o raio escolhido pela empresa mandando na marca e não na recusa, a queda
  para o padrão diante de valor inútil, o cartão do painel contando só o alcance de quem lê, a ficha que não
  oferece formulário a quem não pode registrar, e a coordenada absurda / relato imenso barrados na
  validação. A suíte fecha em 173 testes / 1719 asserções.

- FASE 16 — estoque em livro-caixa, não em campo de quantidade. O módulo inteiro foi construído sobre uma
  recusa: não existe tela para digitar quanto há de um produto, e `products` não tem coluna de saldo. O
  central é a subquery que soma as linhas com o sinal de cada tipo, e é a mesma consulta da lista de
  produtos, da ficha, do painel e do livro — um campo de quantidade viveria ao lado desse número e alguém
  acabaria acreditando nele.
- Migration `2026_10_08_000006_enforce_one_stock_line_per_technician_and_product`: índice único
  `(technician_id, product_id)` em `technician_stocks`. A migration original da fase 2 não o criou, e a
  trava pessimista que `TechnicianStock::travar()` promete só vale se houver exatamente uma linha por par
  técnico/produto — sem o índice, duas cargas simultâneas criam duas metades do mesmo saldo e o `SUM` passa
  a responder duas vezes.
- `App\Models\TechnicianStock`: o estado material da mala, por produto. É a única conta do estoque que é
  coluna e não derivação, e a distinção está escrita no docblock do model — o central é livro-caixa
  (nenhum direito de ajustá-lo por fora das linhas), a carga é material em movimento, que precisa de uma
  linha para travar contra si mesma. `scopeComSaldo()` esconde a zerada: produto devolvido inteiro não é
  carga.
- `App\Http\Controllers\Stock\MovementController` com `index`, `create`, `store` e `export` — e nada além.
  Não existe rota para editar, apagar ou estornar uma linha: movimentação é livro-caixa append-only, e o
  que estava errado se responde com outra linha que diz o que corrigiu. A imutabilidade é decidida no
  roteamento, não escondendo botão.
- Cinco tipos com dois donos: `purchase`/`return`/`adjustment` movem o estoque central
  (`CENTRAL_SIGN`), `load`/`consume`/`return` movem a mala (`TECHNICIAN_SIGN`). O consumo não baixa o
  central de novo porque a unidade já saiu na carga — e é isso que a asserção de saldo antes/depois do
  consumo prova. O ajuste carrega o sinal dentro do `quantity`, porque inventário acha e perde.
- Saldo negativo não nasce: a conta roda em `DB::transaction` com `lockForUpdate` no produto e na linha de
  carga, e a recusa sai como `ValidationException` no campo dono do número — carga que passa do central é
  recusada em `quantidade`, consumo ou devolução que passa da mala é recusada em `tecnico_id`, com o tanto
  que há escrito na frase. A asserção correspondente confere que a linha e o saldo da carga travada
  voltaram com a transação (`technician_stocks` com zero linhas).
- `recorded_at`, `user_id` e `company_id` nunca vêm do request: o relógio é o do servidor, o autor é quem
  está logado e a empresa é o contexto do middleware. O teste manda os quatro campos forjados (data em
  2020, autor de outra empresa, empresa ao lado, técnico em tipo sem técnico) e lê a linha gravada — todos
  ignorados. Tipo que a conta não registra morre na validação do campo, porque a lista aceita é a mesma
  que o `select` oferece.
- Vírgula decimal é recusada, não truncada: `numeric` + `decimal:0,4` barram `'0,5'` antes do
  `(float)`, que registraria um ajuste de nada. É o mesmo contrato de todo o app — os campos são
  `input type="number"`, que envia ponto.
- Aviso de reposição na resposta que baixou o saldo, não na semana em que alguém abrir o painel: cruzando
  o ponto, a redirect leva `aviso` com os dois números; acima de novo, o aviso se cala. O teste mede os
  três momentos (antes de cruzar, cruzando, voltando).
- Alcance decidido por responder pelo inventário: `StockMovement::alcanceRestrito()` é ter ficha de
  técnico e não ter `stock.adjust`, e `scopeVisiveisPara()` reduz o livro às linhas com o nome dele. A
  tela restrita não oferece seletor de técnico nem os tipos compra/ajuste, o `tecnico_id` forjado escreve
  na própria ficha, e o CSV é `stock.export` — 403 server-side para a conta de campo.
- Telas `resources/views/movements/index.blade.php` (o livro-caixa com saldo, efeito no central, carga do
  técnico, ordem, autor e observação; filtros de tipo, produto, técnico, ordem, busca e período;
  paginação própria; CSV) e `movements/create.blade.php` (o registro, com cada opção do seletor de
  produto declarando quanto há dele no central — escolher item para baixar sem saber quanto tem é escolher
  no escuro). Dois
  links que mentiam para a conta de campo foram corrigidos na listagem: nome de produto e nome de técnico
  agora só são link para quem tem `products.view` / `technicians.view`, porque ler o livro é
  `stock.view` e abrir a ficha do colega não é.
- Menu de Operação ganhou "Estoque" (`movements.index`, `stock.view`, `fa-right-left`), e o botão
  "Movimentações" da lista de produtos perdeu a guarda `Route::has('movements.index')` que só existia
  enquanto a rota não havia.
- Painel: o bloco de estoque passou a devolver dois KPIs ("Itens abaixo do ponto de reposição" e
  "Movimentações de hoje", contado no `recorded_at` de hoje dentro do alcance) e o cartão "Últimas
  movimentações", com o efeito de cada linha no saldo que ela realmente mexe e saída para o livro-caixa.
- Ficha do técnico: cartão "Carga no nome dele", lido de `stocks()->comSaldo()`, com o total em unidades e
  o estado vazio para quem está com a mala vazia. `DashboardMetrics` e a ficha respeitam o mesmo alcance da
  listagem, e quem tem a ficha não abre a do colega pelo cartão.
- `DemoSeeder::estoque()` grava 27 movimentações (compra por produto, sete cargas, os consumos das peças
  aplicadas nas ordens concluídas, uma devolução e um ajuste de −2) e `estoqueDosTecnicos()` materializa a
  carga a partir das próprias linhas por `SUM` — a demonstração não digita saldo. Rodar o seeder de novo
  com o índice único no lugar passou limpo, o que é a prova de que a materialização não cria par
  duplicado.
- `tests/Feature/StockTest.php` (10 testes / 145 asserções) cobre o saldo que é conta de linhas (e a
  coluna de quantidade que não existe em `products`), as três recusas que não deixam rastro, a forja de
  data/autor/empresa/técnico, o ajuste que é de quem responde pelo inventário (funcionário recusado no
  campo, cliente 403 nas duas pontas, zero recusado, meia unidade aceita no formato do app), as rotas do
  módulo conferidas uma por uma, o técnico falando da própria mala, o consumo que pede ordem (ausente,
  rascunho, cancelada, da empresa ao lado, do colega, e a que passou pelo quadro de comissão real), o
  aviso de reposição nos três momentos, os filtros com o CSV devolvendo o mesmo ponto, e o painel contando
  o mesmo que a listagem para escritório e para campo. `DashboardTest` atualizado para o décimo terceiro
  indicador.
- Prova ao vivo no servidor de desenvolvimento, com sessão de verdade: item no menu, `/estoque` com 16
  linhas e paginação, `/estoque/registrar` entregando token e o saldo de cada produto no `select`, POST
  gravando a linha (redirect para `/estoque?produto=75` com "Compra de 3,00" no aviso e a observação
  lendo na tabela), o mesmo POST sem token devolvendo **419**, CSV com BOM e `;` respeitando o filtro de
  tipo, painel mostrando o KPI "Movimentações de hoje" em 1 depois da escrita, e a conta de campo sem
  seletor de técnico, sem os tipos de inventário e com 403 no CSV. Conferência do banco: 27 linhas, 7
  cargas, zero divergência entre carga guardada e soma das movimentações, nenhum central negativo, nenhum
  tipo de técnico sem dono, nenhum consumo sem ordem. A suíte fecha em 185 testes / 1882 asserções.

- FASE 17 — o financeiro não tem campo de estado. Nada nesta fase digita "Pago": `status` é a soma dos
  pagamentos contra o valor, e o módulo inteiro foi construído sobre essa recusa. O `Payment` é o fato —
  valor, método, data, autor e referência — e o lançamento é a expectativa: o estado vem junto conforme o
  dinheiro entra.
- `FinancialRecord::recalcularEstado()` é o único caminho que escreve `status` depois que a linha nasce, e só é
  chamado dentro de `DB::transaction` com a conta travada por `lockForUpdate`. A soma volta do banco
  (`linhaDeCaixa()`: `sum(amount)` e `max(paid_at)` na mesma consulta), nunca da tela: dois pagamentos no
  mesmo segundo, cada um conferindo o saldo da própria leitura, fechariam uma conta de R$ 500,00 com
  R$ 800,00 pagos — que é exatamente o quadro que ninguém consegue explicar na hora do fechamento.
- Tolerância de `+0.005` na comparação com o valor, e nada de centavo perdido: `pending` sem pagamento,
  `partially_paid` abaixo do valor, `paid` a partir dele. `occurred_at` é o `max(paid_at)` — lançamento sem
  pagamento não tem data de ocorrência, porque o fato não aconteceu, e `due_date` continua previsto, sendo
  dele o "vencido".
- `canceled` é a única decisão digitada, e só existe sem pagamento registrado: cancelamento apaga a
  expectativa de caixa, não o dinheiro que mudou de mão. Desfazer pagamento tem outro verbo (estorno), outra
  permissão (`financial.approve`) e a auditoria de quem desfez, porque "pagamento negativo" não existe no
  extrato de ninguém.
- Rotas do módulo conferidas uma por uma no teste, inclusive pela ausência: `financial.index`, `export`,
  `create`, `store`, `show`, `edit`, `update`, `payments.store`, `payments.destroy`, `cancel`, `reopen`,
  `destroy` e `restore` — treze, e nenhuma delas serve para "marcar como paga". O teste que lista o quadro de
  rotas do prefixo `financeiro`, uma por uma, é a prova estrutural de que o atalho não existe.
- Degraus separados de propósito: registrar dinheiro é `financial.create` (o escritório digita e o estado vem
  junto), conduzir a conta é `financial.update`, estornar, cancelar e reabrir são `financial.approve`, e
  apagar a ficha é `financial.delete`. Supervisor fica sem a exclusão, funcionário para no registro, técnico e
  conta de cliente recebem 403 antes de ver qualquer botão.
- Categoria é vocabulário fechado por tipo: `FinancialRecord::CATEGORIAS` tem cinco receitas e seis despesas, e
  o `Rule::in` é a mesma lista que o `select` mostra. O legado deixava digitar categoria livre, e em dois anos
  "Peças", "pecas", "pç" e "Mão de obra + peças" eram quatro categorias diferentes que o relatório da fase 18
  somaria separadas. Rótulo fora do catálogo se descreve sozinho (`rotuloCategoria`) em vez de sumir da tela.
- Despesa não tem cliente: `client_id` que chega numa despesa é descartado no `prepare()`, não aplicado, e
  receita sem cliente é recusada no campo — cobrar de ninguém não é conta, é distrato. Ordem e cliente precisam
  apontar para a mesma carteira, e é `garantirMesmaCarteira()` que confere o par, porque a validação de campo
  enxerga cada um isoladamente.
- `cobrar()` (`ordens/{ordem}/cobranca`, com `financial.create`) recomputa o valor no banco a partir das linhas
  e dos descontos da ordem: `valor`, `cliente_id` e `company_id` do request são ignorados. Só ordem `completed`
  cobra, ordem sem linhas não cobra, ordem cancelada não cobra, e segunda cobrança ativa da mesma OS é recusada —
  duplicata de cobrança não é segunda via, é conflito. O caminho de volta existe: cancelar a conta libera a
  reemissão, e é o que o teste confere depois de medir os R$ 1.480,00 de 2×700 + 1×120 − 40.
- Pagamento acima do saldo é recusado com o número que falta na frase ("faltam R$ 120,00 para fechar esta conta
  de R$ 500,00"), conta quitada não recebe pagamento, conta cancelada também não, e a data tem teto de hoje e
  piso de dois anos para trás: dinheiro que ainda não mudou de mão é previsão, e reabertura de exercício não se
  faz por uma tela de caixa. Valor com vírgula decimal é recusado, não truncado, porque o `input type="number"`
  do app envia ponto.
- Estorno procura a linha dentro da conta: `financial.payments.destroy` com um `pagamento` de outra conta
  responde 404 antes de qualquer escrita, e o teste confere que a linha do colega continua lá depois do 404.
- Valor e tipo só se mexem enquanto não houve pagamento (`garantirContaEstavel`): mover o valor de uma conta
  meio paga é mover a régua debaixo do dinheiro que já entrou, e a resposta aponta o caminho honesto — estornar
  e ajustar, ou cancelar e reabrir. Descrição, vencimento, categoria e observação continuam editáveis.
- Exclusão é caso de cadastro que nunca existiu: `financial.destroy` recusa conta com pagamento registrado,
  porque o pagamento ficaria sem dona na hora em que o relatório somar o mês. A exclusão é lógica e
  `?estado=excluidos` acha e restaura a linha.
- Uma expressão SQL, três leituras: `PAGADA_SQL` — `coalesce((select sum(payments.amount) …), 0)`, sem binding e
  sem concatenação de valor de usuário — alimenta a coluna da listagem (`scopeComPagado`), o rodapé de totais
  (`totais()`) e o `valorPago()` da ficha. O saldo da tabela não é parecido com o da ficha: é o mesmo. `totais()`
  devolve registros, bruto, pago, em aberto e o vencido (contado e em valor) sobre o recorte que a pessoa está
  olhando, não sobre a empresa inteira.
- Vencida é relação entre previsto e hoje, não coluna: `estaVencida()` e `scopeOverdue()` deixam o tempo passar
  sem que alguém edite a linha, e `diasEmAtraso()` mede os dias corridos na leitura — a ficha do teste mostra
  "13 dias em atraso" sem que nada tenha sido digitado.
- Rótulo de estado segue a direção do dinheiro: receita é "A receber / Recebido em parte / Recebido", despesa é
  "A pagar / Pago em parte / Pago", e vencida é "Vencido" nos dois lados. Dizer "Pago" numa conta que entrou
  dinheiro é a frase que faz alguém conferir o extrato à toa.
- Telas `financial/index.blade.php` (carteira com busca, tipo, estado — inclusive vencido e excluídos —,
  categoria, cliente, ordem e período de vencimento, ordenação e paginação próprias, rodapé de totais e CSV),
  `financial/form.blade.php` (create e edit sobre o mesmo formulário, com as categorias agrupadas por tipo no
  `select` e a recusa do lado errado conferida no servidor)
  e `financial/show.blade.php` (ficha com saldo, linha do tempo de pagamentos, formulário de registro, estorno,
  cancelamento com motivo, reabertura e a travessia de volta para a ordem cobrada). Filtros, ordenação e
  por-página nos `ListFilters` da casa; CSV com BOM e `;` pelo `Export`, da mesma `consulta()` da tela.
- Ficha da ordem ganhou o cartão "Conta" — bruto, descontos e "Total a cobrar" somados no banco — com o bloco
  "Emitir a cobrança" que só aparece em ordem concluída sem cobrança ativa, e a lista das cobranças já emitidas
  com vencimento, valor, pago e estado. A ficha do cliente ganhou a ponte "Financeiro deste cliente", que abre a
  carteira filtrada na carteira dele.
- Menu de Operação ganhou "Financeiro" (`financial.index`, `financial.view`, `fa-sack-dollar`), e `Navigation` é
  o único lugar de onde o item sai.
- Painel: o cartão "Carteira financeira" com quatro KPIs novos — "A receber", "A pagar", "Recebido no mês" e
  "Despesa do mês" —, todos contados pela mesma `FinancialRecord::totais()` da carteira. "A receber" e "a pagar"
  somam o **saldo** (`em_aberto`), não o valor previsto, porque numa conta meio paga o dinheiro que já entrou não
  pode ser contado de dois jeitos; "Recebido no mês" sai de `occurred_at` no período e traz o delta contra o mês
  anterior; "Despesa do mês" fecha com o saldo do período. O cartão ainda lista as contas que "Vencem em até 15
  dias" e o "Dinheiro que mudou de mão" — os cinco últimos pagamentos com valor, método, autor e data. O
  escritório passa a contar catorze indicadores, e o `DashboardTest` foi atualizado para o quadro exato.
- Dois defeitos do próprio módulo encontrados pelos testes e corrigidos aqui, antes do commit: a regra de cliente
  existente tipava o `Eloquent\Builder` no fechamento do `Rule::exists(...)->where()`, e o verificador de
  presença entrega o `Query\Builder` cru — cada conta criada com cliente devolveria 500; e `reopen()` escrevia a
  nota, chamava `recalcularEstado()` e anunciava a conta de volta à carteira, mas a derivação se recusa a tocar
  numa linha ainda marcada como cancelada, então o cancelamento nunca saía do banco. A reabertura agora retira a
  decisão e deixa a soma dizer o estado, com a mensagem acompanhando o que o banco tem — inclusive a conta que
  reabre parcial porque ainda tem dinheiro registrado.
- `DemoSeeder::financeiro()` grava 17 contas na empresa de demonstração — dez cobranças de ordem (OS-2026-0103 a
  0112, no valor que o próprio seeder soma das linhas), uma receita cancelada de uma ordem que foi cancelada
  (OS-2026-0102, com o motivo na ficha) e seis despesas. As dezesseis contas vivas nascem `pending` com
  `occurred_at` nulo — a cancelada é a única que nasce com decisão digitada — e o resto vem dos 10 pagamentos
  gravados como linhas reais em `payments`, com método, data de caixa, autor e referência, cada um seguido de
  `recalcularEstado()`: o seeder não escreve estado derivado, faz o que a tela faz. Uma
  receita recebe 40% por cartão e o resto por PIX em duas linhas, e fecha quitada com a data do último dinheiro;
  35% e 60% são as frações que deixam uma receita e uma despesa parciais; o vencimento atrasado gera "vencido"
  sem que ninguém edite a linha, e a notificação `financial.overdue` da fase 19 já tem conta para apontar. A
  demonstração roda em qualquer dia: `vencimento()` e `dataDeCaixa()` são relativos a hoje, para "Recebido no
  mês" não zerar no começo do mês.
- `tests/Feature/FinancialTest.php` (10 testes / 264 asserções) cobre a ausência de rota de "marcar como paga",
  o request que tenta forjar estado, data do fato e empresa, o dinheiro que move a conta e a data que volta do
  último pagamento, as recusas de saldo/tipo/categoria/cliente/data, o estorno como degrau de quem responde pelo
  caixa com o 404 da linha alheia, a conta com dinheiro que não muda de valor nem de tipo nem se cancela nem se
  apaga, cobrar a ordem no valor que o banco calcula (inclusive o caminho de reemissão depois do cancelamento),
  os filtros com o CSV devolvendo o mesmo saldo da ficha, e a carteira de fora que não existe — nove verbos
  cross-tenant como 404, técnico e conta de cliente como 403, restauração de exclusão lógica. A suíte fecha em
  195 testes / 2149 asserções.
- Prova ao vivo no servidor de desenvolvimento, com sessão de verdade e a senha lida do `.env` sem nunca aparecer
  na saída: 57 verificações, zero falha. Painel com os quatro números de caixa, carteira com rodapé e filtros,
  ficha de conta meio paga e vencida, `/financeiro/nova` sem campo de estado nem de ocorrência, cobrança emitida
  da OS-2026-0101 no valor das linhas, recusa de duplicata, dois pagamentos fechando a conta, recusa acima do
  saldo, dois estornos devolvendo-a a "A receber", cancelamento recusado em motivo de três letras e aceito com o
  motivo gravado, reabertura derivando o estado de novo, CSV com BOM e `;`, e a conta de campo com 403 na
  carteira e na ficha — com a travessia de cobrança ausente onde não há permissão.

- FASE 18 — relatório é a mesma consulta da listagem, fatiada por período. `App\Support\Relatorio` materializa
  quatro fechamentos (financeiro por categoria, operação por técnico, chamados por prioridade, por categoria do
  serviço ou por técnico, estoque por produto), e cada um parte dos mesmos `visiveisPara()` que as telas de ordem,
  chamado, carteira e movimentação usam: o número do fechado é conferível linha por linha por quem o lê, e não
  existe uma query paralela que pode discordar da tabela.
- O catálogo carrega a pergunta, não só o título (`CATALOGO`: `rotulo`, `aba`, `pergunta`, `modulo`, `arquivo`). A
  aba curta existe porque a navegação entre relatórios com o título inteiro não cabe em tela nenhuma, e o módulo é
  o degrau de leitura que cada fechado exige — ver o dinheiro só faz sentido para quem lê a carteira.
- A janela é resolvida no servidor: sem datas é o mês corrente, com uma ponta só a outra entra a `DIAS_PADRAO` (30)
  dias, início depois do fim é invertido em vez de virar período vazio, e acima de `DIAS_MAXIMO` (366) o início é
  trazido para dentro com um aviso que diz quantos dias foram cortados e manda exportar em duas janelas. O teto
  existe porque um `between` de 1900 a hoje não é relatório — é a consulta que derruba o banco da empresa pelo
  caminho mais fácil.
- Tela e arquivo são a mesma coleção: `fechamento()` devolve a lista já ordenada, a tela fatia em `paginar()` com a
  página e o `porPagina` vindos da query string, e `linhasParaExportar()` escreve o inteiro na mesma ordem. O link
  de exportação carrega a janela resolvida, o tipo, o ângulo e a ordenação vigentes, então o CSV é o que estava na
  frente da pessoa, não uma reconsultada depois.
- CSV honesto com o Excel brasileiro: separador `;`, BOM no início, `Formatters` em todo dinheiro, duração e
  percentual — exportado que discorda da tela é exportado que ninguém assina — e nome de arquivo com o período que
  aquele arquivo resume (`nexusfield-relatorio-financeiro-2026-04-01-2026-10-09-<carimbo>.csv`).
- Ordenação por lista fechada (`ORDENAVEIS` por relatório): a coluna pedida nunca entra em SQL, porque a ordenação
  acontece na coleção materializada, com duas passadas estáveis e medida inexistente por último nos dois sentidos.
  Técnico sem tempo medido não é zero minuto, e `—` que abre a tabela é o mesmo que dizer que a pessoa tem o mais
  rápido da empresa sem ter medido ninguém.
- Dois defeitos encontrados e tratados dentro da fase, ambos pela prova ao vivo. O `linhasFinanceiro()` não
  importava `$tipo` no closure e toda tela e todo CSV de financeiro respondia 500 — a suíte ainda não tinha aberto
  aquela rota. E o `ordenar()` tratava `ordena=rotulo` como "nada pedido" e devolvia a ordem do catálogo: o
  cabeçalho clicável pelo nome, que existe justamente porque a tabela abre na ordem do vocabulário, era a
  propaganda de uma ordenação que não acontece. Descoberto porque a prova imprimiu a primeira linha em `asc` e em
  `desc` e as duas eram a mesma; agora "nada pedido" e "pediu o nome" são perguntas diferentes, e o teste cobra os
  dois sentidos.
- Permissão em degraus, recusada antes de o SQL rodar: `reports.view` abre o hub e as quatro telas,
  `reports.export` faz o arquivo — que atravessa a fronteira da empresa numa planilha anexada —, e cada fechado
  pede ainda a leitura do módulo que resume (`financial.view`, `orders.view`, `tickets.view`, `stock.view`). Sem
  ela, o servidor responde 403 dizendo qual degrau falta, e o cartão não aparece no hub: cartão que leva a uma tela
  zerada é interface contando vantagem. A rota parametrizada de exportação é constrained às quatro chaves do
  catálogo, então `relatorios/{qualquer-coisa}/exportar` responde 404 em vez de montar agregação inexistente.
- O hub fecha o período com o resumo dos módulos que a conta alcança — a receita realizada no período e os quatro
  fatos de operação, visita, chamado e estoque, todos contados no banco daquela empresa — e só lista os cartões
  abertos para quem tem a leitura do módulo. Conta com `reports.view` mas sem leitura de módulo nenhum recebe a
  frase que diz qual permissão pedir, não uma lista vazia.
- `ReportsTest` (19 testes, 185 asserções) cobra a janela nos quatro cantos (padrão, uma ponta, invertida, cortada
  pelo teto), o fechado somando a mesma consulta da listagem, o CSV na mesma ordem da tela com o mesmo dinheiro e o
  período no nome do arquivo, a coluna pedida por injeção caindo no catálogo sem virar query, a ordenação pelo nome
  nos dois sentidos, a medida inexistente que não vira zero, o 403 que nomeia o degrau faltante, o cartão que some,
  o técnico sem `reports.view`, o funcionário que lê o estoque mas não exporta, e o fato da empresa ao lado que não
  entra no fechado. A suíte fecha em 214 testes / 2336 asserções.
- Prova ao vivo no servidor de desenvolvimento, com sessão de verdade e a senha lida do ambiente sem nunca aparecer
  na saída: as cinco telas em 200 com os números reais da empresa de demonstração e a janela de 192 dias desenhada
  no cabeçalho, os quatro CSVs bem formados (9, 6, 5 e 9 linhas, zero linha torta) com o período no nome, `ordem
  asc` e `ordem desc` finalmente diferentes, a query string de injeção respondendo 200 na ordem do catálogo, e a
  conta de campo com 403 no hub, no financeiro e no exportar.

- Fase 19 — notificações: o sino toca para quem tem de ouvir, e a bandeja é de cada conta.
  O `Notifier` ganhou o resto do vocabulário (`ordem.atribuida`) e três endurecimentos que a
  auditoria pediu: `quemPode()` agora filtra por `company_id` — o modelo `User` não tem escopo
  global de empresa, e um sino que atravessa a fronteira do tenant não é alerta, é vazamento —,
  `paraQuemPode()` aceita o autor do ato e não toca sino para quem acabou de praticá-lo, e
  `jaAvisaram()` deduplica: enquanto a conta tiver por ler um aviso daquele tipo apontando para
  aquela origem, o fato repetido não martela. O `StatusCatalog` ganhou o grupo `notification`, e
  é dele que a central tira rótulo, tom e as opções do filtro — tela nenhuma conhece texto solto.
- Seis gatilhos em atos de negócio reais: ordem criada toca na conta do técnico escolhido — e,
  sem responsável, sobe para quem tem `orders.approve`; comissionar toca a conta do quadro com a
  nota de comissão como corpo; concluir ou cancelar toca as contas do quadro ativo, do
  responsável e do cliente dono da carteira (o motivo do cancelamento viaja no aviso); chamado
  novo toca uma vez para cada conta com `tickets.execute`; resolver toca o responsável e a conta
  de cliente com a nota da resolução como corpo; e a falta no central — o flash de quem digitou —
  agora também acorda quem repõe (`stock.adjust`), um toque por conta sem leitura.
- Central de notificações (`/notificacoes`, `NotificationController` + `notifications/index`):
  posse antes de permissão — a consulta filtra `user_id` de quem logou antes de qualquer filtro,
  o PATCH de marcar lida responde 404 para aviso alheio sem confirmar que a bandeja existe, e o
  "marcar todos" varre só a conta de quem clicou. Busca, tipo, leitura, período, ordenação e
  paginação são as primitivas das listagens, e aviso não se apaga: fica com o carimbo da chegada
  e o da leitura.
- Sino na barra (`navbar`): contagem de pendentes com teto em 99+, dropdown com os seis últimos
  avisos, fio de marca nos ainda não lidos e atalho para o centro; a consulta do sino é sempre
  por conta, nunca por empresa, e o item entrou na seção Gestão da sidebar para todo papel com
  `notifications.view` — inclusive o cliente.
- Varredura diária (`nf:notificacoes:diaria`, agendada às 07:00 com `withoutOverlapping`): ordem
  vencida no prazo previsto toca o quadro, o responsável e quem aprova escala; receita ou despesa
  a vencer em até dois dias toca quem lê o financeiro; e a agenda de amanhã toca o técnico da
  janela e a conta do cliente. Cada empresa é varrida dentro do próprio contexto de tenant, e a
  conta de cliente fica fora do atraso de propósito — atraso sem ação junto é ansiedade.
- `NotificationsTest` (12 testes, 75 asserções) trava as três decisões do servidor: bandeja por
  conta com 404 para o sino alheio, autor que não ouve o próprio ato, sino único por permissão,
  dedup que só libera o segundo toque depois da leitura, varredura que não empilha o mesmo fato,
  e o escritório da empresa ao lado que não ouve nada. A suíte fecha em 232 testes / 2433
  asserções, com o filtro de empresa em `quemPode()` aprovado pelo teste de fronteira antes de o
  sino existir em tela.
- Prova ao vivo no servidor de desenvolvimento: `/notificacoes` capturado em 1440×900 nos dois
  temas com os badges por tipo e os dois carimbos visíveis, o dropdown do sino aberto no painel
  com "7 pendentes" e o rodapé do centro, e a varredura rodou de verdade — 14 avisos gerados na
  primeira passada, zero na segunda, o deduplicador funcionando sem leitura no meio.

- Gestão de contas e papéis (FASE 20): a regra do núcleo é "ninguém concede o que não
  tem". `UserController` (`/usuarios`) e `RoleController` (`/papeis`) chegam com folha de
  ponto, filtros das primitivas compartilhadas, ordenação, paginação e quotas — o topo da
  listagem diz "X CONTAS NA FOLHA · PLANO PERMITE N CONTAS", e criar conta acima do teto do
  plano é recusada antes de qualquer INSERT. O editor só vê, no formulário, os papéis cujo
  conjunto de permissões cabe dentro das dele (`podeConceder`); mexer numa conta que enxerga
  mais do que você responde 403; a conta raiz não se edita nem se exclui por tela; e ninguém
  desativa a própria conta, tira de si mesmo o último papel de administrador ou exclui a conta
  que ainda é o acesso de uma ficha de técnico — a exclusão é soft delete.
- Pareamento papel Cliente ↔ carteira, recusado em flash porque regra em closure não roda
  sobre valor nulo: conta de papel Cliente precisa apontar para uma carteira da empresa, só a
  conta de papel Cliente se vincula a carteira, e uma carteira não aceita duas contas de acesso.
- Papéis de sistema são intocáveis por tela (403 em editar/excluir) porque o provisionamento é
  dono deles; papéis personalizados têm CRUD completo, slug imutável de nascimento, exclusão
  recusada enquanto houver conta atrelada, e a matriz de permissões do formulário só desenha
  permissões que o próprio editor já tem. `nf:papeis:sincronizar` regrava os cinco papéis de
  sistema de todas as empresas a partir do catálogo, é idempotente e não encosta nos
  personalizados.
- Supervisor ganha `orders.execute`, `users.view/create/update` e `roles.view` (sem
  `users.delete`), o status de conta vira `inactive`/«Inativo» no `StatusCatalog`, e a sidebar
  recebe Usuários e Papéis na seção Gestão. Seis telas novas (listagem, ficha e formulário de
  conta; listagem, ficha com alcance por módulo e formulário de papel), todas em 1440×900
  verificado nos dois temas.
- `UsersTest` (13 testes) e `RolesTest` (10) cobram o servidor de perto: isolamento de tenant,
  concessão fora do alcance recusada, teto do plano, raiz 403, auto-degradação bloqueada, ficha
  de técnico segurando a exclusão, papel de sistema imutável, slug reservado, delete de papel em
  uso, edição de papel reposicionando os efeitos das contas atreladas, e o comando de
  sincronização regravando de verdade e sem duplicar na segunda passada. A suíte fecha em
  255 testes / 2529 asserções.

- Configurações da empresa (FASE 21): `SettingController` (`/configuracoes`) arruma a casa sem
  inventar nada. O perfil (nome, documento, telefone, e-mail, site) é editável, mas a chave de
  endereço e o plano não se mexem por esta tela nem para o administrador — a primeira é única para
  sempre, o segundo é contrato com a plataforma. O e-mail do perfil é único por empresa e o site só
  aceita URL de verdade. A marca sai do medalhão genérico e entra na faixa do sidebar e no menu da
  conta: o envio valida PNG/JPG/WEBP entre 64 e 1200 pixels de cada lado, até 2 MB, e o SVG armado
  de script fica do lado de fora — o arquivo é gravado na pasta da empresa com nome gerado por nós,
  e a marca antiga sai do arquivo junto com a troca, sem imagem órfã em storage.
- Cinco preferências no `SettingsCatalog`, cada uma com consumo vivo no código e nenhum campo morto
  em tela: o raio aceito no check-in (`ServiceOrderCheckin::raioAceito`), a janela do alerta de
  vencimento (os dias da varredura diária) e três chaves que ligam ou desligam, por empresa, as três
  famílias do sino. Número vazio é «sem opinião»: a linha sai da `company_settings` e o padrão da
  casa volta a valer, e o `Settings::valor` lê o valor já com o tipo certo.
- Supervisor vê, administrador gere (`settings.view` contra `settings.manage`): a tela em modo
  leitura mostra o cartão «Modo leitura» e desabilita cada campo, e o PUT sem `settings.manage` é
  barrado com 403 antes de encostar em qualquer valor. Cada campo alterado sai carimbado na
  auditoria, com o antes e o depois; se nada mudou, o flash avisa em vez de gravar poeira. A
  sidebar ganha Configurações na seção Gestão.
- `SettingsTest` (12 testes) aperta o servidor: 403 do supervisor, tabela vazia devolvendo o padrão,
  raio salvo e relido pelo check-in, esvaziar a linha restaurando o padrão, a janela de vencimento
  mexendo na varredura, as chaves silenciando o sino, salvar sem tocar, o carimbo na auditoria,
  slug imutável, colisão de e-mail entre empresas, raio e dias fora da faixa, e o ciclo da marca
  inteira — entra, troca, a anterior sai, e a falsa (SVG armado, png vazio, imagem miúda ou
  larga demais) não passa. A suíte fecha em 267 testes / 2603 asserções.

- Trilha de auditoria virou tela (FASE 22): `/auditoria` desenha o que cada conta fez na
  empresa — quando, quem, que ação, sobre o quê, de onde (IP e navegador) e o antes/depois campo
  a campo. A listagem usa as primitivas compartilhadas (busca, filtro por entidade e por conta,
  período, ordenação, paginação) e o topo conta "N atos registrados · página X de Y"; a ficha do
  ato congela o nome do autor gravado à época — quem mudou de nome não reescreve a história — e
  avisa quando a conta que fez o ato saiu do acesso. Exportar CSV tem degrau próprio
  (`audit.export`) e sai exatamente pela mesma consulta filtrada da listagem. Administrador e
  supervisor enxergam a trilha; funcionário, técnico e cliente recebem 403 do servidor antes de
  qualquer link aparecer, e ato de outra empresa responde 404.
- O gravador da trilha foi corrigido na raiz: `Auditor` codificava o diff em JSON e a coluna
  `changes`, que já tem cast `array`, codificava de novo — a leitura devolvia string JSON dentro
  de string JSON. Agora o diff entra cru e o cast é o único codificador. A armadilha que
  disfarçava o bug saiu no mesmo golpe: dentro do escopo da classe, `$ato->changes` resolve a
  propriedade protegida `$changes` do dirty-tracking do Eloquent, nunca a coluna com cast —
  `AuditLog::mudancas()` lê por `getAttribute()`, a única porta que enxerga o cast, e ainda
  decodifica tolerante as linhas antigas, que continuam legíveis na tela.
- Rótulo de entidade mora no model: `AuditLog::rotuloEntidade` traduz o `class_basename`
  gravado (`StockMovement` → "Movimentação de estoque", `User` → "Conta de acesso"), com
  `Str::headline` de chão para o que ainda não está na lista — código-fonte nunca aparece na
  gestão. `pintarValor` trata nulo por traço e booleano por sim/não.
- `Auditoria` entrou na sidebar na seção Gestão, depois de Configurações. `AuditTest` (9 testes)
  cobre degraus de permissão, isolamento de tenant, os quatro filtros, a ficha com diff, linha
  antiga duplamente codificada, ficha alheia 404, export com o mesmo filtro, nome congelado e a
  regressão do gravador gravando array de verdade. A suíte fecha em 276 testes / 2642 asserções.

- Prontidão de deploy (a casa fecha a porta da rua): cinco páginas de erro com a marca
  (`resources/views/errors/403|404|419|500|503.blade.php`) vestem a casca pública no lugar da página
  crua do Symfony. O 403 e o 404 contam o motivo que o `abort()` escolheu contar — "Você não tem
  permissão para esta ação.", "Este pagamento não pertence a esta conta." — e ficam mudos para o
  texto que o framework inventa: `App\Support\MotivoErro` é a única porteira do critério, então nome
  de rota pedida, nome de `Model` e inglês de Symfony não sobem para a tela. O 503 é auto-suficiente
  por desenho — CSS embebido, nenhuma rota, nenhum asset do Vite, nenhuma sessão — porque a
  manutenção derruba justamente o que as páginas normais usam.
- `flash-messages` deixou de depender do middleware `web`: em rota sem esse middleware o `$errors`
  nunca é compartilhado, o componente estourava, e a página de erro caía na casca crua que ela mesma
  veio substituir. O guard `isset($errors)` fecha o ciclo — e é ele quem devolve ao 404 e ao 500 a
  cara da casa.
- A suíte ganhou trava contra o próprio ambiente: `config:cache` escreve `bootstrap/cache/config.php`
  e esse cache vence o `<env>` do `phpunit.xml`, então a suíte passaria a rodar contra o banco de
  desenvolvimento — onde `RefreshDatabase` apaga o que encontra. `tests/TestCase.php` lê o nome do
  banco e recusa antes do primeiro migrate, dizendo qual cache tirar do caminho.
- `ErrorPagesTest` (5 testes) prova as cinco portas sem debug: 404 de quem não entrou, 403 de quem
  não tem o degrau — com o motivo na tela e a stack fora dela —, 419 disparado na fonte da exceção,
  porque em teste o framework dispensa a conferência de origem, 500 de rota que explode e 503 com
  `artisan down` de verdade, verificado também ao vivo num servidor com `APP_DEBUG=false`. A suíte
  fecha em 282 testes / 2670 asserções.
- README ganhou "Em produção": instalação sem dev, chave, migrate, seed, build, link e os quatro
  caches, a linha de cron do agendador, a ausência deliberada de worker de fila (nada implementa
  `ShouldQueue`), o que trocar no `.env` e o rodapé de marca nas páginas de erro. As quatro fases
  entraram no roadmap e nas seções de módulo, e as seis telas foram recapturadas do estado atual —
  painel com os catorze indicadores nos dois temas e o celular 390×844 emoldurado no quadro 1440×900.

- Canal no YouTube na vitrine: a seção Desenvolvedor do README ganha o selo do canal
  [C. Marcelo Dev. Brasil](https://www.youtube.com/@c.marcelodev.brasil) ao lado de GitHub e LinkedIn,
  no mesmo padrão `flat-square` dos outros contatos.

- Perfil da conta: `/perfil` (GET e PUT), `/perfil/chave` e `/perfil/senha` com
  `App\Http\Controllers\Auth\ProfileController`. A cadeia que a plataforma promete — welcome → acessar →
  login → dashboard → módulos → **perfil** → logout — parava no painel porque não existia espelho. Nenhum
  degrau de permissão no caminho: perfil é a única coisa que pertence de fato a quem está logado. A tela lê
  do banco empresa, plano, papéis, chave, telefone, último acesso, vínculos de ficha e, para quem conduz
  campo, as próximas janelas da própria agenda — as mesmas linhas que o calendário desenha. Os links de
  destino só aparecem para quem tem a permissão de abri-los, para o espelho não virar corredor de 403.
- Troca de chave de acesso e de senha própria autenticadas, as duas exigindo `current_password`: o e-mail é
  o que o login digita, e sem prova um terminal deixado aberto seria a porta para assumir a identidade
  alheia. A senha nova passa pelo mesmo `Password::min(10)->letters()->numbers()` do reset, tem que ser
  diferente da atual, e a renovação encerra as demais sessões da conta quando o driver é `database` — a que
  renovou continua viva. Senha não entra na auditoria: o que fica é o ato. A conta raiz mantém no modelo a
  guarda do próprio e-mail, e a recusa chega como 302 com flash, não como 500.
- "Meu perfil" no dropdown do cabeçalho, um degrau antes de "Sair".
- `ProfileTest` (10 testes / 65 asserções): espelho aberto para um técnico sem nenhum degrau de módulo,
  gravação com carimbo na trilha, salvamento igual que não inventa carimbo, recusa de chave sem a senha
  atual e com e-mail de outra conta, login que passa a valer pelo e-mail novo, raiz que não troca a própria
  chave, senha renovada que aposenta a antiga sem vazá-la para o log e a agenda do técnico que não mostra a
  janela de outro campo. A varredura de navegação passou a 73 endereços GET com `/perfil` no mapa.

- Camada de serviços entre a tela e o banco (`app/Services`), o degrau do meio que faltava no desenho
  interface → serviço → backend → dados. Até aqui o controller era ao mesmo tempo a fachada e a caneta:
  abria transação, criava a linha, gravava a passagem de estado, tocava o sino e escrevia na trilha.
- `App\Services\Orders\FluxoDeOrdem` é dono da vida da ordem — número sequencial com a passagem de
  abertura e a comissão do técnico numa única transação, cadastro que nunca toca em estado, travessia com
  carimbo e sino de fim de percurso, e o apagar que só vale para o rascunho que nunca virou trabalho.
  `App\Services\Orders\RegistroDePresenca` é dono da escrita em campo: a visita nasce do relógio do
  aplicativo, a distância se mede deste lado da linha e a chegada abre a execução pelo fluxo, não por fora.
- `App\Services\Recusa` é a porta pela qual o domínio diz não: o salto que o fluxo não tem e a chegada
  depois do fim voltam como 302 com o motivo na tela, em vez de exceção na cara do visitante. `tom()`
  escolhe entre `erro` e `aviso`, então a régua de tonalidade continua sendo de quem apresenta.
- A régua não foi duplicada no caminho: quem oferece o botão e quem deixa passar leem a mesma
  `proximosEstados()`, e a máquina de estados continua sendo `ServiceOrder::FLUXO` no modelo. Qualificar
  quem registra presença ficou na apresentação de propósito — autorização é o degrau acima do serviço.
- `CamadaDeServicosTest` (6 testes / 33 asserções) chama o serviço sem HTTP e depois lê a fonte dos dois
  controllers: nenhum deles abre transação, cria linha, toca sino nem escreve na trilha. A suíte fecha em
  298 testes / 2768 asserções, Pint PASS em 173 arquivos, e as 27 provas de ordens e chegadas passaram sem
  uma mensagem ou um redirecionamento alterado.

- FASE 23 — micro-visualização no painel: o traço da série e o anel da proporção, desenhados pela própria
  casa (`x-ui.sparkline` e `x-ui.gauge`, Blade + SVG) e alimentados por consulta real por período. Nenhuma
  biblioteca de gráfico entrou no projeto e nenhuma classe ficou esperando uso.
- `DashboardMetrics` ganhou as quatro séries que o traço pede: `serieDiaria()` conta no SQL um ponto por
  dia, `serieJanelas()` conta a agenda pela sobreposição da janela — a mesma régua do `scopeBetween` do
  quadro —, `serieMensal()` soma o dinheiro que mudou de mão mês a mês lendo `Payment`, nunca a coluna
  prevista do lançamento, e `pontualidade()` mede, dos serviços concluídos em trinta dias, quantos
  terminaram dentro do prazo que a própria ficha previu. Ordem, agenda, estoque e financeiro têm traço; no
  financeiro o pontilhado é o que saiu, ao lado do que entrou.
- O desenho não enfeita: menos de dois pontos, ou série toda zero, não viram linha — o cartão fica com o
  número dele, que já é a verdade inteira — e ficha sem fim previsto não gera anel, porque sem prazo não há
  atraso a medir. O `aria-label` devolve os números que a linha conta ("começa em, termina em, maior
  ponto"), e o anel é `aria-hidden` porque repete o percentual escrito ao lado dele.
- Nada de tinta nova: traço, área e anel bebem `--nf-tone`/`--nf-tone-soft` do registro único de tom
  (`.tone-*`), e o traço usa `vector-effect: non-scaling-stroke` para contar a mesma história no celular de
  390px e na Smart TV. O ícone continuou em cada KPI — o anel mora no rodapé da distribuição de estados, não
  no lugar do ícone de ninguém.
- `PainelMicroVisualizacaoTest` (7 testes / 60 asserções) amarra traço e número: a série bate com o SQL
  ponto a ponto, cartão e traço respondem ao mesmo período (soma de sete pontos para "7 dias", último ponto
  para "hoje" e "no mês"), o desenho só existe com dado, todo KPI mantém o ícone e a folha da
  micro-visualização não tem uma tinta fora do registro. A suíte fecha em 307 testes / 2904 asserções, e o
  `DemoSeederTest` ganhou a prova de que a demonstração é uma história só: a hora em que a ordem terminou é a
  mesma hora da trilha de estado, da saída da visita e da liberação da comissão.
- O carregamento da agenda deixou de ser silêncio: a faixa de esqueleto com as cinco linhas que o quadro
  preenche nasce escondida, o callback `loading` do FullCalendar a acende enquanto a janela vem do servidor,
  o quadro esmaece em 200ms e `aria-busy` marca o calendário como ocupado. Ela mora fora do `.nf-agenda`
  porque é lá dentro que a biblioteca escreve — filho sobrevivente seria apagado na montagem. O `nf-skeleton`
  que a folha de componentes já desenhava, e nenhuma tela vestia, passou a ter dono.
- A virada de tema ganhou janela: `nf-tema-virando` no `<html>` anima fundo, fio, letra e `fill` em 200ms
  com o `--nf-ease` da casa, e a classe sai 260ms depois. A pintura inicial continua instantânea — o
  `data-bs-theme` chega certo do `<head>`, e o script inline não conhece a classe. Quem pediu
  `prefers-reduced-motion` continua com a duração neutralizada pela régua global de sempre.
- `CoerenciaVisualTest` (4 testes / 41 asserções) amarra a camada de apresentação inteira: o esqueleto está
  na página, é irmão do quadro e quem o conduz é o callback com `aria-busy`; a virada anima cor e não
  anima forma; esqueleto e virada bebem dos tokens sem uma tinta nova; e a varredura das 202 classes `nf-`
  dos seis arquivos de estilo, contra Blade, JavaScript, controller e seeder, não acha uma órfã — o sufixo
  montado em tempo de execução (`nf-fc-t--{tom}`) é reconhecido pelo tronco, que é o contrato. A suíte
  fecha em 311 testes / 2945 asserções, Pint PASS em 175 arquivos e a varredura de navegação nos mesmos 73
  endereços GET.
### Alterado

- Os middleware de `bootstrap/app.php` saíram do FQCN em linha para imports: a lista de
  prioridade e os apelidos leram `ResolveCompany::class`, e o arquivo entrou no estilo que
  o próprio framework dita.

- A paleta "Premium Gourmet + Technology" foi harmonizada com a nova logo: a marca saiu do teal
  `#1b6a5b` / `#46b39b` e entrou no verde cromado do medalhão (`#1c6b28` no claro, `#5eb85e` no
  escuro), com o latão (`#8a6212` / `#d9b25c`) mantido como contra-ponto gourmet. O aviso mudou para
  `#95551a` / `#e0a05a` para não se confundir com o latão, e foram recalculados de uma vez os
  `--bs-*-rgb`, os sutis, as bordas, os links, o gradiente da marca e as tintas de item ativo da
  sidebar. Fundo preto no escuro e branco neutro no claro continuam intocados, e o contraste da letra
  sobre as seis cores de marca segue acima de 4.5:1 nos dois temas.
- O wordmark `NEXUS-FIELD` passou a repetir o desenho da logo: `NEXUS` na cor da marca e `-FIELD` na
  tinta neutra, com entreletra mais aberta — antes o sufixo era acento bronze, que não existe no
  arquivo novo.
- `x-ui-brand-mark` troca o SVG do "N" desenhado em tokens pelo medalhão da logo oficial, e as seis
  capturas do README foram refeitas contra o aplicativo já com a marca e a paleta novas.

- As seis capturas do README passam a medir exatamente **1440×900**. Os dois painéis e o celular
  foram recapturados logados na empresa de demonstração, porque o painel da empresa raiz sem dados é
  o estado vazio — que tem lugar no README, mas não é a vitrine. Os painéis, que vinham em
  1440×1000, entraram no quadro da série; o celular continua fotografado no viewport real do
  aparelho (390×844) e montado sobre um quadro 1440×900 na cor de superfície do próprio tema, porque
  um celular não tem 1440×900 de viewport — e a legenda diz isso em vez de esconder.
- README reescrito no formato de referência que o dono do projeto apontou: cabeçalho centralizado
  com a marca, fileira de selos de versão/stack, separadores horizontais, telas em tabela Markdown
  centralizada de duas por linha, e as seções Sobre o projeto, Funcionalidades no ar, Módulos,
  Tecnologias, Estrutura de pastas, Arquitetura, Banco de dados, Instalação, Configuração, Segurança,
  Testes, Demonstração, Roadmap, Histórico de versões e Desenvolvedor. Nada foi inventado para
  preencher seção: a tabela de módulos marca como 🚧 tudo que ainda é só schema, a demonstração diz
  que não há demo online nem senha publicada, e a linha de uploads saiu da tabela de segurança
  porque ainda não há código de upload no repositório. A marca do README é a mesma
  `x-ui-brand-mark` do aplicativo, exportada em `docs/branding/nexusfield-marca.svg` com os hexes
  atuais do `tokens.css` no lugar das variáveis de tema.

- Paleta global reescrita nas duas pontas. O escuro larga o grafite azulado
  (`#101319`/`#151922`/`#1b2130`) e passa a ter **fundo preto puro** (`--nf-bg: #000000`), com a
  hierarquia vindo da superfície acima dele (`#0c0c0d`, `#151516`) e do fio de borda, não de cor no
  fundo; a sombra no escuro virou anel de luz, porque sombra sobre preto não aparece. O claro larga
  a porcelana rosada (`#f3eee6`/`#faf6ef`/`#fdfbf7`) e passa a branco neutro-frio (`#f5f6f8`,
  `#fafbfc`, `#fdfdfe`) — sem casta rosada e sem `#fff` de fundo. Texto, muted, faint, links,
  bordas, sombras, gradientes e os `--bs-*`/`--lte-*` derivados foram recalculados nos dois temas, e
  a sidebar — que é painel escuro nos dois — ganhou grafite neutro preso pelo tema da página, já
  que o próprio elemento declara `data-bs-theme="dark"`.
- Tinta sobre a cor de marca virou token (`--nf-on-brand`), porque no escuro a marca clareou e o
  branco fixo do Bootstrap sumia em cima dela: botão primário, botão accent, pill ativo, paginação,
  item de lista ativo, avatar, knob da pílula de tema e os botões do diálogo passaram a ler a mesma
  tinta, clara no claro e escura no escuro. O mesmo vale para `badge.text-bg-*` no escuro, onde o
  `!important` do utilitário só pôde ser alcançado com `!important` de resposta.
- Âncora das capturas padronizada: as seis telas passam a caber no mesmo padrão de duas por linha,
  com largura declarada no `<img>` (640px para desktop, 292px para o celular) em vez do tamanho
  natural do arquivo, e as duas linhas de painel — que antes ocupavam a largura inteira cada uma —
  agora comparam lado a lado na mesma escala. Nenhuma altura foi declarada de propósito: com altura
  fixa, o `max-width: 100%` do GitHub amassa a imagem fora da proporção; sem ela o navegador
  respeita a razão nativa, e foi isso que se mediu no navegador (proporção declarada = proporção
  desenhada nas seis).
- Âmbar e aviso escureceram um degrau no claro (`#b96c2c` → `#a45d1f`, `#a9761a` → `#96670f`) para
  segurar 4,5:1 com a letra por cima, e as bordas de interação subiram para 3:1 — o campo de
  formulário era o componente que precisava aparecer como limite, não o cartão.
- Cor da barra do navegador (`meta theme-color`) sincronizada com os novos fundos nos dois lugares
  que ela existe: `theme.js` e o script pré-pintura `x-script.theme`.
- `tests/Unit/PaletteTest.php` (5 testes / 185 asserções) prende a paleta como contrato: fundo
  escuro preto, fundo claro sem casta rosada e sem branco puro, rampas de superfície neutras,
  contraste AA de texto/fundo e de tinta/marca calculados do hex ao WCAG, e uma lista de hexes da
  paleta antiga que não pode voltar em nenhum arquivo de CSS, JavaScript ou Blade. A suíte fecha em
  73 testes / 541 asserções.
- Instalação do README sem credencial à mostra: o trecho que copiava `SEED_ADMIN_EMAIL` com o
  endereço real e `SEED_ADMIN_PASSWORD` com um valor de exemplo saiu. Quem instala continua
  encontrando os nomes das chaves e o que cada uma faz nos comentários do `.env.example`, e a
  explicação de por que a conta raiz é permanente ficou no lugar.
- Saiu o latão da paleta. A família `--nf-accent*` era o "contra-ponto" do gourmet anterior e não
  existe na logo atual, que é verde sobre preto: o botão `Acessar` da chamada, o filete do item ativo
  da sidebar, a cauda do `--nf-gradient-brand` (que desenhava as linhas de baixo dos cartões de KPI) e
  o `--bs-code-color` passaram a ler a rampa verde — `--nf-primary` no botão, `--nf-brand-vivid` no
  filete e no código do escuro, `--nf-brand-vivid`/`--nf-brand-mint` na cauda do gradiente. Onde a cor
  fazia papel de categoria — o tom `waiting`, que veste "em espera", "aguardando cliente", "sem
  coordenadas", "sem agendamento", cartão de crédito, ponto comercial, visita técnica e devolução —
  entrou `--nf-waiting`, verde ainda não maduro (`#4e7112` no claro, `#a8cf52` no escuro), escolhido
  para não brigar com o `--nf-success` de conclusão nem repetir o âmbar de `--nf-warning`. Os dois
  valores cumprem 4,5:1 sobre as três superfícies do tema, prova que entrou no `PaletteTest` junto
  com os nove hexes do latão, agora na lista que não pode voltar.
- Rodapé das telas abertas virou uma linha: 41px no lugar dos 141px medidos antes, com a marca à
  esquerda e a assinatura mais o fato da sessão à direita. Saíram os quatro selos de capacidade
  ("Isolamento por empresa", "Permissões por papel", "Trilha de auditoria", "Fuso …"), a frase de
  efeito e a navegação repetida (Início, Painel, Entrar, Recuperar acesso) — quem está numa tela
  aberta já tem o cabeçalho para andar pela casa, e o rodapé não precisa dizer duas vezes o que a
  página acabou de mostrar. Os selos continuam no pé autenticado, onde o nome da empresa é fato lido
  do banco e não anúncio.
- Altura do topo cortada na fonte única: `--nf-header-height` desceu de 3.25rem para 2.75rem, o piso
  de 44px que ainda se acerta com o polegar, e o forro vertical dos dois cabeçalhos se unificou em
  `.25rem`. Medido no navegador com o DevTools Protocol: 53px → 48px no público e 53px → 49px no
  autenticado, com o mesmo corte de moldura dos dois lados da aplicação. As seis telas do README
  foram recapturadas em 1440×900 depois disso, porque o topo, o filete da sidebar e as linhas dos
  cartões de indicador mudaram de verdade.
- Identidade raiz trocada por decisão do dono: `DatabaseSeeder::ROOT_EMAIL` passa a
  `nexusfield.admin@gmail.com`, com a senha rotacionada no `.env` de quem mantém a máquina — nenhum
  caractere de credencial entra em arquivo versionado, e a regra de produção do seeder (recusar senha
  gerada automaticamente) continua valendo como antes. Como `User::booted()` prende as guardas na
  bandeira `is_root` e não no endereço, a identidade nova herdou a proteção absoluta no instante em
  que a migration terminou; `nexusfield` seguiu sendo a empresa dela.
- `.env.example` e README acompanhando a troca: o exemplo mostra o endereço vigente, avisa que senha
  com `#` precisa de aspas (sem aspas o dotenv corta a senha no `#` e o seeder recebe truncado) e
  aponta para `RETIRED_ROOT_EMAIL` em vez de repetir o endereço aposentado no texto. Na tabela de
  segurança, a linha da conta raiz passou a listar também "perder a própria bandeira".
- README relido inteiro contra o repositório, linha por linha, com as seis telas conferidas contra o
  aplicativo rodando. Cinco frases estavam atrás do código: o `favicon.ico` embute 16, 32 **e 48**
  (contado no cabeçalho do próprio arquivo, não no que o README dizia); `Auditor`/`Auditable` faz mais
  que registrar mudança de estado — o trait grava criar, alterar e excluir sozinho em `audit_logs`, e a
  ação de negócio entra escrita à mão; `Roles` movimenta os dois seeders e não tinha linha na tabela de
  camada própria; `TrataRegistrosAninhados` (a peça só é editável pela janela do cadastro-pai) estava
  de fora da linha dos concerns de ficha; e `anyCompany()` era descrito como "reservado para agregação
  interna e console", quando o único uso no projeto é `CompanySetting::valueFor()`, que desliga o
  escopo global para filtrar na linha seguinte pelo `company_id` de quem está logado — não é porta de
  saída, e o texto passou a dizer isso com o nome do ponto.
- Árvore de pastas do README corrigida onde ela mentia: `Contact` não existe como model (o nome é
  `ClientContact`), a lista de `app/Support` nomeava seis classes de doze, e a de models era um
  "…" em vez de um número. Agora são 29 models contados no diretório, agrupados pelo que tem tela e
  pelo que ainda é só schema, e `Notifier` aparece nominado como o que é — classe escrita, módulo na
  fase 19. O parágrafo de testes ganhou quebra honesta e teve o número conferido na suíte: 151 testes,
  1411 asserções, verde.
- As seis capturas foram **conferidas**, não recapturadas por hábito. `04` e `05` já tinham o menu
  claro do último commit; `01`, `02`, `03` e `06` são do commit anterior, e o único código que mudou
  desde então é a sidebar — que não existe nas três telas abertas e está fora da tela no celular.
  Prova medida antes de decidir: as três públicas foram capturadas de novo no quadro 1440×900 e
  voltaram com o mesmo layout, os mesmos textos e o mesmo medalhão novo, variando 0,05% a 3% em bytes
  pela fase da animação de auto nível no instante do clique — a série em produção ficou com o quadro
  nivelado, então continua sendo a imagem fiel do que a aplicação desenha hoje.
- Assinatura do desenvolvedor com a porta de entrada profissional ao lado do GitHub: o quadro de
  autor do README ganha o selo `LinkedIn` apontando para `linkedin.com/in/clayton-marcelo-dev`, na
  mesma linha e no mesmo estilo flat-square do selo de GitHub. É link de contato, não promessa de
  produto — por isso fica na assinatura e não na fileira de selos do topo.
- O painel recebeu o primeiro grupo do refino visual "Premium Gourmet + Technology", aplicado inteiro
  sobre a camada de tokens da casa: sem Tailwind, sem dependência nova, sem classe utilitária que não
  exista no build. `resources/views/dashboard.blade.php` mudou uma classe (`tone-{{ $kpi['tone'] }}`
  no cartão do indicador) e nada mais — nenhuma rota, consulta, handler ou assinatura de componente
  foi tocada.
- Entraram três tokens de matéria por tema: `--nf-sheen` é a luz que pega na quina de cima do cartão,
  `--nf-tint` é o wash que separa cabeçalho de corpo, `--nf-etch` é o fio gravado na divisão. No claro
  são traços de 3,5% a 9% de tinta sobre o branco neutro; no escuro, 2,8% a 7,5% de luz sobre o preto.
  Os três são decorativos e não carregam letra, por isso `PaletteTest` continua medindo as mesmas
  superfícies e o mesmo contraste de antes — e continua verde.
- Mono virou letra de instrumento, não de corpo: `--nf-font-mono` (ui-monospace → SFMono → JetBrains
  → Menlo → Consolas) serve ao rótulo de coluna, à etiqueta de KPI e à pílula de variação. O número
  fica no corpo da casa com figura tabular, porque `R$ 4.095,50` alinhado com a linha de baixo é
  requisito, não estética.
- O vocabulário de tom deixou de ser dezessete regras copiadas. `.tone-draft/open/progress/waiting/done/canceled`
  agora só declaram `--nf-tone` e `--nf-tone-soft`, e quem pinta — o quadrado do ícone, a barra de
  distribuição e o trilho do KPI — lê a variável. Estado novo passa a existir num lugar só, e o tom que
  o Blade já mandava veste o cartão inteiro em vez de só o ícone.
- Matéria do cartão: o corpo recebe o wash e um filete de luz interna no topo (`inset 0 1px 0
  --nf-sheen`); cabeçalho e rodapé descem de tom e ganham o fio gravado, de modo que a divisão é um
  corte no material, não uma borda genérica.
- KPI: a faixa de marca embaixo do cartão — que era o gesto de template mais visível da tela — saiu, e
  entrou um trilho de 2px na lateral esquerda, do tom do indicador ao transparente. A etiqueta passou
  a versalete mono entreletrado com um fio gravado atrás dela, a variação virou cápsula na cor do
  próprio sinal, e o ícone respira 4% quando o cartão inteiro vai ao hover.
- Vida medida, não decorada: os pontos de estado pulam apenas nos estados que ainda estão acontecendo
  (`open`, `progress`, `waiting`) — 11 dos 17 pontos do painel, porque concluído e cancelado não têm o
  que respirar — e a régua de distribuição cresce de zero com `nf-medidor` e brilho no preenchimento.
  Os dois continuam dentro do clamp de `prefers-reduced-motion` da base.
- Cabeçalho de tabela em versalete mono entreletrado sobre o wash, que é o que o pedido pedia, feito no
  seletor `.nf-table > thead > tr > th` que a casa já usa.
- Adiado de propósito: sparkline e medidor radial. Desenhar curva sem série temporal guardada no banco
  é exatamente a funcionalidade falsa que este projeto proibiu — `DashboardMetrics` compara dois
  períodos, mas não guarda a série ponto a ponto. Entram quando a agregação existir, não antes.
- Prova do grupo: `npm run build` limpo, suíte completa verde em 214 testes / 2336 asserções, e as duas
  capturas autenticadas do painel em 1440×900 — claro e escuro — medindo 14 cartões, `trilho: 2px`,
  etiqueta e cabeçalho em `ui-monospace`, `nf-medidor` ativo, 11 de 17 pontos pulsando e
  `transborda: false` com a lista de culpados vazia.
- O refino desceu do painel para as telas de listagem pelo mesmo caminho: token da casa, nenhum
  hex literal, nenhuma classe de utilitário que o build não conheça. Nenhuma tela de listagem foi
  tocada — o que mudou foi `listings.css` e um leitor de cabeçalho em `listas.js`.
- A barra de filtros virou cartão de serviço (wash, luz na quina de cima e filete interno, iguais aos
  do painel) e o rótulo de campo entrou em mono. A régua acima da tabela — "25 ordens encontradas ·
  página 1 de 2" — é mono versalete com figura tabular, porque é a única parte da frase que muda de
  valor quando a consulta muda.
- Coluna ordenável: o alvo do clique deixou de ser a palavra e passou a ser a célula inteira, com o
  padding indo do `<th>` para o link. A coluna vigente não troca a cor da letra — ela carrega um fio
  de marca por baixo, que é o suficiente para dizer "por aqui" sem gritar.
- Paginação: o Bootstrap soldava as páginas numa barra só, comendo a borda esquerda e quadrando as
  pontas de fora. Cada página voltou a ser pílula independente, em mono tabular para a barra não
  tremer quando a lista passa de 9, e a corrente do meio é cheia na cor da marca.
- A linha responde ao cursor com tinta de passagem, não com a cor de marca: a marca pertence ao que
  está ativo, não ao que está sendo olhado.
- A ficha de celular. Abaixo de 768px a linha da tabela deixa de ser linha e vira cartão: rótulo à
  esquerda em mono versalete, valor à direita, divisão gravada entre os pares. O rótulo é lido do
  próprio `<th>` em `etiquetarCelulas()`, porque um `data-rotulo` digitado à mão em cada tela é a
  primeira coisa a discordar do cabeçalho quando alguém renomeia uma coluna. Do cabeçalho só
  interessa a palavra que a pessoa vê — o `visually-hidden` que explica a ordenação ao leitor de tela
  e os ícones ficam fora da leitura.
- A ficha veste a tabela inteira ou nenhuma: uma linha com `colspan` quebraria o alinhamento do resto
  sem avisar, então a tabela que não casa célula com coluna continua rolando como sempre. Sem
  JavaScript nada quebra — a ficha é melhoria, não pré-requisito. E o cabeçalho não é apagado, é
  recolhido ao quadro de um pixel: as colunas continuam existindo para quem precisa delas.
- Célula em grade, não em flex: uma célula costuma trazer dois ou três blocos empilhados (nome,
  fantasia, selo de excluído) e como item de flex eles sairiam lado a lado, espremidos.
- Colhido na própria captura, dentro do grupo: o `text-align: end` de uma coluna de número arrastava
  junto o rótulo gerado pela célula, e "Total" aparecia solto no meio da ficha. O rótulo é sempre a
  borda esquerda do par, alinhado por cima do valor que ele nomeia.
- Prova do grupo: `npm run build` limpo, suíte completa verde, e as capturas de `/clientes` em
  1440×900 nos dois temas, `/clientes` em 390×844 e `/ordens` em 390×1700 nos dois temas, e
  `/financeiro` em 1440×900 — medindo célula etiquetada (`rotuloPintado: "Ordem"`), exibição `grid`
  no celular e `table-cell` no desktop, cabeçalho recolhido (`absolute 1px`), pílula de paginação em
  `ui-monospace` com raio 999px, fio de ordenação ativa na cor da marca e `transborda: false` em
  todas.
- Os quatro fechados e o resumo de `/relatorios` receberam a mesma matéria, sem tocar em nenhuma
  view: o que mudou foi um bloco "Matéria do relatório" em `listings.css`. A faixa "Período fechado"
  largou a borda de 3px na cor da marca — o mesmo gesto de cartão com listrinha que o KPI aposentou
  — e ganhou wash, luz na quina e o trilho de 2px que some antes do fim. A etiqueta virou versalete
  mono, e o período em figura tabular. Quando o teto de dias poda a janela, o trilho troca para o tom
  de perigo (`:has(.nf-relatorio-aviso)`), então a faixa já avisa antes de a pessoa ler a frase.
- As abas entre os fechados agora são um seletor segmentado: um trilho só, em cápsula, com a aba
  ativa levantada sobre ele. Cinco pills soltas pareciam cinco botões de ação. No mesmo trilho elas
  passam a ser o que são, vistas diferentes do mesmo período. No celular o trilho rola de lado dentro
  da própria faixa (579px de conteúdo em 350px de faixa) e a página não ganha rolagem horizontal.
- Colhido na própria captura: no cartão de fechamento, o valor em fonte display partia em duas linhas
  ("R$" em cima, "8.456,80" embaixo) quando a coluna estreitava, e lia como dois números. O valor de
  `.nf-fact-list` agora não quebra, e quem cede espaço é o rótulo. A correção vale também para o
  painel, que usa a mesma lista.
- Prova do grupo: `npm run build` limpo, suíte completa verde e as capturas de
  `/relatorios/financeiro` em 1440×900 nos dois temas, `/relatorios` em 1440×900 nos dois temas e
  `/relatorios/chamados` em 390×1400 no claro. Medido em cada uma: trilho `2px` com borda esquerda de
  volta a `1px`, etiqueta em `ui-monospace`, trilho de abas `inline-flex` com raio 999px, aba ativa
  na cor da marca de cada tema e `transborda: false`.
- A ficha e o formulário vestiram a mesma matéria. O título de seção de cadastro passou a versalete
  mono com fio gravado correndo até a borda; o rótulo do `<dl>` de ficha desceu para o mesmo mono do
  cabeçalho de tabela; e a linha de item (endereço, contato, membro) ganhou wash e luz na quina, com
  o foco subindo da célula para a linha inteira — em formulário de item o olho acompanha a régua, não
  o campo. O spinner de envio, que já existia em CSS, parou de brigar com o ícone estático do botão:
  durante o carregamento o ícone sai de cena.
- Colhido ao provar o grupo 3 e consertado aqui: o `nowrap` que impedía "R$" e o número de se
  partir foi aplicado a todo `<strong>` de lista de fato — inclusive nos cartões de ajuda que
  carregam frase, que estouraram a tela no celular. O `nowrap` virou a utilitária `.nf-valor` e foi
  marcado, campo por campo, nos fortes que somam quantia ou contagem (nove telas). Quem carrega frase
  voltou a quebrar; quem carrega número voltou a caber inteiro.
- Também colhido na prova: o par "Total a cobrar" do `nf-total-linha` espremido a 390px quebrava o
  valor no meio — no polegar o rótulo agora fica em cima e o número inteiro embaixo, alinhado à
  direita; e a coluna do carimbo da linha do tempo era 3.4rem, que bastava para a hora do painel mas
  fazia a data inteira da ficha invadir o texto, e passou a crescer pelo próprio carimbo.
- Prova do grupo: build limpo, suíte completa verde, e as capturas de `/ordens/302` em 1440×1500 nos
  dois temas e em 390×3600 no escuro, `/ordens/302/editar` e `/ordens/nova` em 390px,
  `/financeiro/nova` em 1440×1100, `/relatorios/financeiro` em 390×1600 e o painel em 390×2400 — com
  rótulo de seção e `<dl>` medidos em `ui-monospace`, valor monetário inteiro numa linha, e
  `transborda: false` em todas depois que o detector aprendeu a respeitar o contêiner que rola de
  propósito (`.table-responsive`, trilho de abas).
- As telas abertas fecharam a série. A caixa "O ciclo de um serviço" na apresentação trocou a borda
  grossa de marca pelo mesmo trilho de 2px que morre antes do fim, o fio que liga os passos agora
  desvanece em vez de cortar, e o número de cada passo pisou no verde de texto sobre a marca
  (`--nf-on-brand`) em vez do fundo de superfície. Cartão de login e faixa de chamada ganharam a luz
  na quina e o wash de topo, iguais aos cartões autenticados. O auto nível da marca não foi tocado —
  nenhuma das três animações públicas (`nf-auto-nivel`, `nf-nivelando`, `nf-acender`) mudou de
  seletor, de chave ou de tempo.
- Prova do grupo: capturas de `/` e `/login` em 1440×900 nos dois temas e de `/forgot-password` e
  `/reset-password` em 390×844 — trilho da caixa de fluxo medido em `2px` com borda de volta a
  `1px`, luz interna presente no cartão de acesso e na faixa de chamada, `transborda: false` em
  todas, e a suíte completa verde com as asserções de markup do `<strong>` atualizadas para
  `nf-valor nf-mono`.

- As janelas de conclusão do painel passaram de rolagem de 24 horas para dias de calendário: "Concluídas em
  7 dias" e os sete dias anteriores leem dias inteiros, a mesma régua da série diária que desenha embaixo do
  número. Um traço dia a dia ao lado de um total de 7×24 horas contaria dois períodos diferentes com as
  mesmas palavras, e o painel respondia um número enquanto a linha mostrava outro.
- A série de agenda conta janelas que se sobrepõem ao dia, não compromissos começados no dia, porque o
  cartão "Agenda de hoje" e o quadro já medem por sobreposição — o traço passou a desenhar o mesmo número
  que o cartão escreve e que a agenda mostra.
- A demonstração ganhou histórico de caixa: dez contas dos cinco meses que antecedem este (contrato mensal
  de uma carteira e conta da base, pagas no dia em que teriam sido pagas) mais duas baixas datadas de hoje,
  fechando em 29 contas (17 receitas, 12 despesas) e 22 pagamentos com dinheiro real em seis meses para a
  série desenhar.

### Corrigido

- A chave de tema trocava a cara da página e deixava o navegador com a tinta antiga.

  * O AdminLTE 4 escreve `color-scheme` como estilo inline no `<html>` quando liga, e estilo
    inline vence a regra do `tokens.css`. O `apply()` do `theme.js` atualizava
    `data-bs-theme`, o cookie, a barra do celular e o `aria-pressed`, mas não aquele estilo:
    depois de clicar, a página virava clara enquanto barra de rolagem, campo de data e
    checkbox continuavam escuros até a recarga — e o contrário também.
  * Agora `syncNativeScheme()` acompanha cada troca, na pintura inicial e no `apply()`, e o
    contrato está travado por teste em `PaletteTest`. A varredura de navegador (MCP
    `browser-use`) é que viu o defeito: o CSS servido está correto, então nenhum teste de
    HTTP o pegaria.

- A ficha de edição de um compromisso concluído devolvia 500 em vez de recusar com aviso.

  * `AgendaController::edit()` tinha o tipo de retorno assinado como `Illuminate\View\View`
    e, quando o compromisso estava travado, devolvia `back()->with('erro', …)`. O flash entrava
    na sessão antes do `return`, o tipo chegava errado e o PHP encerrava a requisição com
    `TypeError`. A assinatura agora é `View|RedirectResponse`, a mesma régua do `update()`
    vizinho: o porte continua o mesmo (fato passado não se reescreve), só que devolvido pela
    porta certa.
  * O teste que já cobria essa recusa passou batido sobre o 500, e a razão é a armadilha
    inteira: `assertSessionHas('erro')` olha a sessão, e a sessão já estava escrita. Agora a
    asserção cobra o 302 de ida e volta com o recado exato, travando o porte e a forma.
  * A caçada foi uma varredura de navegação: logou de verdade na aplicação e bateu os 72
    endereços GET do sistema, um por um. O 500 apareceu ali, e não apareceria em teste nenhum
    que só olhe o flash.

- Auditoria técnica da fase 1 à 18, com cada erro corrigido travado por teste novo:

  * O servidor aceitava técnico desligado em ordem, chamado e quadro de comissão — a tela
    não oferece, mas um pedido mexido na mão entrava na escala. A validação agora é a mesma
    régua da agenda (`status <> inactive`), e a ordem só aceita equipe ativa, exatamente
    como o próprio formulário já filtrava.
  * Cadastro arquivado vivia escondido atrás de 404: as bindings de cliente, técnico,
    serviço e produto resolviam só linha viva, então o selo "Excluído em…" que a ficha
    desenha era código morto e uma ordem antiga que aponta para quem já saiu fechava em
    porta. A binding resolve o arquivado na leitura e a escrita continua recusada pelo
    mesmo 404 — a única porta de volta é o botão restaurar, que anda pelo id. A ficha do
    produto arquivado lê o saldo central pela mesma régua da listagem, sem receber nulo.
  * CSV baixado é vetor de injeção: célula de texto começando com `=`, `+`, `-` ou `@`
    o Excel executaria como fórmula no lugar de mostrar o cadastro. `Export::celula()`
    agora põe apóstrofo de escape nessas células — só as de texto: número negativo é
    saldo, não fórmula, e perderia a aritmética da coluna.
  * `x-ui.button` desenhava dois atributos `class` na mesma tag: o navegador fica com o
    primeiro e a classe que a tela passava era descartada em silêncio — o vermelho do
    "Excluir" das ações, por exemplo. Agora a classe do componente e a da tela se mesclam
    num atributo só.
  * Duas guardas `Route::has('orders.show')` que juravam fase 12 e o texto "na fase 16"
    do formulário de produto, aposentados: a tela prometia o que já era realidade.

- Avatar com glifo solto: `User::initials()` montava "A(" a partir de "Administrador (demo)",
  porque todo token separado por espaço entrava no cálculo. Agora só palavra que começa com letra
  conta; nome de uma palavra só usa as duas primeiras letras ("Ad") em vez de repetir a inicial, e
  nome sem letra nenhuma cai na marca.
- Ícone quadrado nos botões de acesso: "Entrar" (cabeçalho público e envio do login) passou a usar
  `fa-key`, o mesmo da chamada da página pública, e "Enviar link de redefinição" usa `fa-envelope`
  — o ícone agora diz o que o botão faz. `.btn` virou `inline-flex` com `gap`, porque o espaço entre
  ícone e rótulo dependia de um caractere de espaço no markup.
- Assinatura de rodapé com o ano fixo no Blade: `now()->format('Y')` no lugar do "2026" digitado, e
  os separadores viraram fio vertical em CSS em vez de "·" no texto.
- Capturas do `README.md` refeitas contra o aplicativo rodando, já com o topo baixo, a pílula de
  tema e o rodapé novo; a legenda da tela de entrada passou a mencionar o botão de revelar senha.
- Fuso do aplicativo ajustado para `America/Sao_Paulo` (`APP_TIMEZONE`, com padrão no
  `config/app.php`): o MySQL local atende com `time_zone = SYSTEM`, então com o app em UTC a
  janela de "hoje" do painel, a agenda e todo `NOW()` escrito em SQL divergiam três horas do
  relógio do PHP. Timestamps gravados antes desta mudança continuam corretos no banco; a leitura em
  desenvolvimento é que adiantava.
- `Client::addresses()` devolvia `HasMany` sobre uma relação polimórfica (`morphMany` em
  `Address`). O painel de clientes, que é a próxima fase, ia quebrar no primeiro `with('addresses')`.
- `protected $casts` virou o método `casts()` nos models já existentes, igual aos novos: no
  Laravel 13 a propriedade ainda funciona, mas misturar os dois estilos faz um model herdado
  sobrescrever o cast do pai em vez de somar.
- `DatabaseSeeder` perdeu a criação de papéis duplicada e passou a chamar `Roles::provision()`, o
  mesmo caminho dos papéis por empresa. Os rótulos em português moram ali; `Str::headline($slug)`
  gerava "Administrator" e "Technician" na tela de permissões.
- O subtítulo do cartão "Ordens na fila" prometia uma janela de datas diferente da consulta que o
  alimentava. Texto, estado vazio e legenda agora dizem os mesmos sete dias que o `WHERE` usa.
- Valor em reais quebrava em duas linhas dentro do KPI (são ~11 caracteres num corpo estreito): os
  cartões de dinheiro ganharam escala própria (`.nf-kpi-value-money`) e `overflow-wrap`, e o corpo
  do KPI recebeu `min-width: 0` de verdade — a classe `min-width-0` que estava na marcação não
  existe no Bootstrap 5.3 nem no AdminLTE 4, então era classe morta.
- Tabelas do painel rolavam na horizontal entre 768px e 1440px dentro do cartão, escondendo coluna
  sem aviso: a regra global de `white-space: nowrap` (FASE 4, para tabelas de cinco colunas) vale
  dentro de `.table-responsive`. As tabelas do painel marcaram `.nf-table-wrap`, que abre exceção
  para a célula quebrar; em 320px o `min-content` ainda manda e a tabela rola, que é o
  comportamento honesto para cinco colunas num celular.

- As cores da sidebar do design system não chegavam à tela: o AdminLTE declara `--lte-sidebar-*`
  com `[data-bs-theme=dark].app-sidebar` (0,2,0), e a sobrescrita feita no `<html>` perdia por
  especificidade. A sobrescrita agora ataca o mesmo seletor.
- Título e subtítulo do cartão colavam no cabeçalho: o AdminLTE flutua `.card-title` para ele
  dividir linha com as ferramentas, o que empurrava o subtítulo para o lado do título. O
  `.card-header` passou a ser uma grade de duas colunas, com as ferramentas centradas à direita.
- Em 320px o nome do usuário no navbar empurrava a seta do dropdown para fora da tela. Abaixo de
  576px sobra o avatar, e abaixo de 480px a lista de definição empilha rótulo e valor.
- `.nf-status` perdia a cor semântica no tema escuro: uma regra `[data-bs-theme="dark"] .nf-status`
  pintava todo estado com `--nf-text` e apagava a diferença entre concluído, atrasado e cancelado.

- O guarda de envio bloqueava mesmo: com `novalidate` no formulário, o navegador não cancela o
  request por conta própria, e o listener devolvia sem `preventDefault()` — um clique com campo
  obrigatório vazio saía para o servidor do mesmo jeito.
- Campo de senha não volta preenchido para a tela: `x-ui.input` renderizava `old($name)` também em
  `type="password"`, devolvendo a senha digitada no HTML da reposta.
- AdminLTE 4 injeta os próprios links de salto, o aviso `(required)` e um segundo balão de erro,
  tudo em inglês. As três saídas que o pacote oferece para isso foram usadas: `.skip-links` já
  existe em português, `.required-indicator` é o nosso rótulo escondido, e os campos marcam
  `disable-adminlte-validations`.
- `.card-title` dentro de `.card-body` volta a ser bloco: o AdminLTE define `float: left` nesse
  elemento para o título dividir a linha com as ferramentas do cabeçalho, e isso fazia o título
  invadir o parágrafo seguinte.

- `BelongsToCompany` passa a impor o `company_id` do contexto no `create`; antes, um
  `company_id` enviado pelo request escrevia o registro em outra empresa — vazamento entre
  tenants. O valor explícito continua valendo quando não há contexto (console e seeders).
- `tests/TestCase.php` limpa o `TenantContext` no `tearDown`: como o contexto é estático, um
  teste podia contaminar o seguinte e esconder falha de isolamento.
- `phpunit.xml` roda sobre MySQL/InnoDB com `LOG_CHANNEL=null`; com o log em arquivo, o monolog
  derrubava a stack do teste ao gravar (`errno=9`) e a falha real ficava escondida.
- `routes/web.php` usa um `WelcomeController` em vez de fechar a rota de entrada numa closure,
  mantendo a página pública testável.
- **Abertura de tenant pela rota**: `ResolveCompany` entrava no grupo `web` depois de
  `SubstituteBindings`, então o modelo da rota era resolvido com o `CompanyScope` ainda cego e
  `GET /clientes/{id}` de outra empresa respondia 200. A resolução do tenant agora é registrada na
  lista de prioridade imediatamente antes de `SubstituteBindings` (`prependToPriorityList` em
  `bootstrap/app.php`), depois de sessão e autenticação e antes de qualquer binding. O teste
  `test_um_cliente_de_outra_empresa_nao_e_alcancavel_pela_rota` fecha essa porta.
- Parametrização das rotas de clientes: `{client}` ao lado de `Client $cliente` no controller. O
  binding implícito do Laravel compara o nome do parâmetro com o nome da variável, e a divergência
  injetava um `Client` vazio montado pelo container em vez de procurar no banco — a ficha abria
  sem registro, `clients.show` não conseguia gerar URL e o update de um endereço recebia string.
  Toda a rota passou a se chamar `{cliente}`, e `{contato}`/`{endereco}` entraram no mesmo padrão.
- `x-app.content-header` usava `$iterator->last` dentro do `@foreach` do breadcrumb. A variável
  nem existe no Blade 13: qualquer tela com trilha de dois níveis caía em `Undefined variable`.
  Agora `$loop->last`, que é o nome certo.
- `Rule::unique` do documento tinha verificador com `Closure(Builder $query)`; o
  `DatabasePresenceVerifier` entrega um query builder cru, não o builder do Eloquent, e a tipagem
  derrubava a validação com `TypeError` antes de dizer que o documento já existia.
- O seletor de cidade da listagem nascia vazio: a lista de cidades vinha como lista simples e o
  componente de select esperava mapa rótulo→rótulo. Agora `pluck('city', 'city')`.
- **`GET /equipes` com erro 500 no banco real**: a contagem do quadro usava
  `withCount(['technicians as membros_count' => fn (Builder $q) => $q->wherePivotNull('left_at')])`,
  mas dentro do `withCount` quem chega é um `Eloquent\Builder`, não a relação — e o `__call` dinâmico
  de `where*` converteu o método inexistente em `where('pivot_null', null)`, coluna que o MySQL
  respondeu com `1054 Unknown column 'pivot_null'`. Agora `whereNull('team_members.left_at')`, escrito
  contra a tabela intermediária. A falha passou despercebida no teste unitário porque ninguém tinha
  aberto a listagem autenticado: `test_saida_de_membro_registra_data_e_preserva_a_passagem` agora abre.
- As ordens "que a equipe tem em aberto" incluíam quem já tinha saído do quadro, ao contrário do que a
  legenda da tela promete. A consulta passou a filtrar `team_members.left_at`.
- `User::technician()` era um `belongsTo(Technician::class)` apoiado em `users.technician_id`, coluna
  que não existe no schema: qualquer leitura dela derrubaria a ficha. Passou a `hasOne`, que é como a
  relação está desenhada — a ficha do técnico guarda o `user_id`.
- `Technician`, `Team` e `Specialty` não usavam `Auditable`: mexer na escala, no quadro ou no catálogo
  não deixava nenhum rastro em `audit_logs`, enquanto cliente já deixava.
- A matriz de permissões do teste assumia que o Funcionário via equipes. O `PermissionCatalog` não lhe
  dá `teams.view`, e quem manda é o catálogo: o teste passou a esperar o 403 real.
- README dizia que `Formatters` formata telefone (ele formata dinheiro, decimal, data e hora), e
  continuava em 78 testes / 621 asserções quando a fase 9 já tinha fechado em 93 / 697. A contagem e a
  árvore de pastas foram postas em dia com o que existe no repositório hoje.
- `pint --test app` fechou em `PASS` nos 61 arquivos: `DashboardMetrics`, `ListFilters` e
  `TenantContext` carregavam importações sem uso e `!` colado na variável, e os controladores de
  cliente e técnico importavam classe que não usavam mais depois dos traits compartilhados.
- A ficha do produto caía em 500 com `ParseError: syntax error, unexpected token "endif"`: o
  atributo `:subtitle='…'` foi escrito com aspas simples e o PHP de dentro também usa aspas simples,
  então o atributo se encerrava no primeiro `'SKU '` e a tag de abertura de `<x-layouts.app>` nunca
  era compilada — sobrava um `@endif` órfão no texto cru, e a linha do erro do PHP apontava para o
  último `endif` do arquivo, não para a causa. Atributo Blade com expressão leva aspas duplas.
- Cabeçalho da listagem de categorias quebrado em duas linhas fonte (`{{ total }}` de um lado, o
  rótulo do outro): a interpolação multilinha injeta quebra e indentação no HTML, e a frase
  "1 categoria cadastrada" não existia junta na tela nem no teste. Entrou numa linha só, como já
  faz a listagem de clientes.
- Os controladores de catálogo importavam `EmEdicao`, pediam `Request` no `show()` e mandavam
  `situacoes` para uma ficha que não desenha seletor de situação — herança do esquema da fase 10 que
  não servia a nada. Saíram os três, e `pint --test app resources/views` fecha em `PASS` nos
  64 arquivos.
- `OrderItemController` caía em `ErrorException: Undefined array key "item_descricao"` sempre que a
  linha era adicionada sem descrição própria — o campo é `nullable` e, não vindo no request, não entra
  em `validated()`. `?? null` dentro de `filled()` nos dois pontos (`store` e `update`), com o nome do
  catálogo como descrição quando ninguém escreve outra. Pego no primeiro `assertSessionHasNoErrors` da
  linha de serviço em `OrdersTest`.
- `orders/index` lia `$ordem->service` na coluna de serviço sem ter feito eager load daquilo: cada
  linha da página pagava uma query. Entrou `service:id,name` no `with()` — e o `export` continua sem
  ele, porque o CSV não imprime a coluna.
- `ServiceOrder::scopeVisiveisPara()` e `alcanceRestrito()` resolviam a ficha do técnico antes de olhar
  `users.client_id`, então todo painel — inclusive o de quem é conta de cliente, que não tem ficha
  nenhuma — ia ao MySQL buscar um técnico. Invertida a ordem, o ramo barato (a coluna que já veio com o
  usuário) decide primeiro e a relação só é lida quando pode existir. `DashboardTest` recuperou a
  garantia de que a conta de cliente não toca a tabela `technicians`.
- O filtro de equipe em `consulta()` (`?equipe=`) era query sem tela: a listagem nunca desenhou o
  seletor e `FILTROS` nunca o listou. Saiu o `relacionado`, para a consulta responder exatamente pela
  interface que existe.
- `DashboardTest` tinha uma proibição larga demais depois do alcance de ordens: a lista de tabelas
  vetadas tratava `technicians` como se só o bloco de equipe a lesse. A prova foi afunilada — a ficha do
  próprio usuário (um `select *` com `limit 1`) é o escopo de `orders.view`, permissão que a conta tem; o
  que segue proibido é qualquer contagem sobre o quadro sem `technicians.view`, que é o bloco respondendo
  pela operação inteira na tela de quem não o viu.
- `pint --test app resources/views tests` fechou em `PASS` nos 88 arquivos. Saíram do controlador de
  ordens um `@return Closure` que só repetia a assinatura e um `!` colado na variável, e de fases
  anteriores três nits: `(new DemoSeeder())` com parênteses, concatenações com espaço ao redor do `.` e
  um nome de método de teste em camelCase no meio da frase em português.
- O `g-5` da linha do hero abria barra de rolagem horizontal no celular: a margem negativa do row
  (-24px) passava do respiro do container (12px) e o documento media 432px dentro de uma janela de 420.
  A linha passou a `gx-4 gy-5`, que devolve os 3rem de respiro vertical entre os blocos sem sair da
  grade — medido no navegador, `scrollWidth` voltou a bater com a largura da janela em 420px.
- Contagem de migrations no README: a árvore dizia 17 e a prosa dizia 16 para o mesmo diretório, e
  nenhuma das duas batia com o que `git ls-files database/migrations` devolve. Agora os dois lados
  dizem 18, e a linha da Fase 2 perdeu o número — ela descreve o que a fase entregou, não o total de
  hoje, que é exatamente o tipo de cifra que apodrece em documentação.
- `nota` obrigatória que não obrigava nada: a regra era uma `Closure` sobre o valor, e o Laravel não
  chama regra de valor quando o campo **não veio** na requisição — num formulário com `nullable`,
  omitir a linha `nota` resolvia um chamado sem dizer o que foi feito. A obrigatoriedade agora é
  calculada antes da validação (`notaObrigatoria()` responde pelo estado de origem e pelo destino) e
  entra como `required` com a mensagem do passo. Descoberto no smoke HTTP, não no teste: a primeira
  versão do teste mandava `nota=''`, que passava pela closure.
- Onze formulários cadastrados com `:action="$..."` — sintaxe de outro framework colada em Blade. O
  atributo chegava ao HTML como `:action` literal, sem `action` nenhum, e o navegador enviava o POST
  para a URI da própria página: em `categories/index`, `clients/form`, `clients/show`, `orders/form`,
  `orders/show`, `products/form`, `services/form`, `specialties/index`, `teams/form`, `teams/show`,
  `technicians/form` e `technicians/show` toda gravação batia num GET de listagem. Trocados por
  `action="{{ ... }}"`; as telas novas de chamado já nasceram certas, e foi a comparação com elas que
  entregou o resto.
- Quem conduzia o estado era decidido em três lugares que não conversavam: a rota pedia
  `tickets.update|tickets.close`, o controlador oferecia os passos do fluxo e a ficha desenhava o botão
  para quem tem `tickets.view`. Resultado: o técnico via "Iniciar atendimento" e levava 403 no clique,
  e o teste correspondente passava verde por engano — sem técnico na ficha, a rota devolvia 404 antes
  da autorização. Agora a rota pede `tickets.execute`, o controlador usa o mesmo critério em
  `proximosEstados()` e a tela só monta o formulário com `podeMover`; o 404 do teste virou asserção
  explícita de que a conta de cliente não move estado.
- Ficha de chamado desenhada em inglês: o subtítulo do card de estado ("The status flow decides the way;
  every pass stays with author and date"), os rótulos "Notes", "Actions", "Submit Change", "Loading…" e
  "Internal note" e a frase de chamada de "Voltar" saíram em português, com as mesmas frases que o
  restante do painel usa. A proibição dos diálogos nativos já estava coberta; a do idioma não estava, e
  esta tela passou.
- Linhas de ordem e de chamado no painel eram texto morto: `dashboard.blade.php` imprimia número e
  protocolo dentro da tabela sem link nenhum, então o número que a fase 8 contou no banco não levava a
  lugar nenhum. As duas colunas agora abrem a ficha correspondente (`orders.show`, `tickets.show`), e os
  links só aparecem dentro dos blocos que a permissão de leitura já desenhou.
- `pint --test` nos arquivos da fase: `DemoSeeder` carregava dois imports sem uso e docblocks com
  `\Carbon\Carbon` onde a classe já estava importada; `TicketsTest` tinha um import fora de ordem.
  Rodados e conferidos depois, com a suíte ainda verde.
- Menu do tema claro pintado de painel escuro: a sidebar declarava o próprio
  `data-bs-theme="dark"` no Blade, então no dia claro a casa se partia em duas metades que não se
  conhecem — conteúdo branco, menu grafite. O atributo saiu do `<aside>` e o menu passou a herdar o
  tema do `<html>`. Os `--lte-sidebar-*` do claro agora apontam para tokens do tema (tinta
  `--nf-text-muted` sobre `--nf-elevated`, item ativo `--nf-brand-deep` sobre `--nf-primary-soft`,
  cabeçalho de seção `--nf-text-faint`), todos medidos acima de 4,5:1, e o filete de item ativo saiu
  do hex solto para a mesma cor do rótulo ativo. O escuro ficou como estava, tinta por tinta.
- Tokens de sidebar e de campo de busca declarados no `<html>` não produziam efeito nenhum: o
  AdminLTE redeclara `--lte-sidebar-*` no próprio `.app-sidebar` (0,1,0), e declaração no elemento
  vence herança. Os valores mortos saíram dos blocos de tema e a sobrescrita foi para o elemento, na
  especificidade que realmente ganha; `.sidebar-search`/`.navbar-search` não existem nesta interface,
  e os `--lte-search-field-*` que só existiam no papel foram apagados junto.
- As duas capturas de painel do `README.md` refeitas contra o aplicativo rodando, no quadro
  1440×900 de sempre: a do tema claro ilustraria um menu que a aplicação não desenha mais. A conta de
  demonstração entrou com a senha emprestada do ambiente e o hash original dela voltou ao banco
  byte a byte depois da foto — a troca de credenciais da raiz continua sem efeito nas contas demo até
  alguém rodar `db:seed --class=DemoSeeder`.
- Vinte e quatro formulários de escrita não emitiam token de CSRF, e nenhum teste perceberia: o
  `VerifyCsrfToken` do framework se isenta enquanto a suíte roda, então o `post()` do teste passava e o
  `419` pegava só quem usa navegador. As telas de acesso (`login`, `forgot-password`, `reset-password`),
  o logout da barra, o componente `x-ui.action-form` e a ficha de check-in já traziam `@csrf`; todo
  `<form method="POST">` escrito à mão estava sem o campo oculto — os cadastros de cliente, técnico,
  equipe, serviço, produto, ordem, chamado e compromisso, e as fichas embutidas de endereço, contato,
  item, comissão, nota e mudança de estado. Na prática nenhuma tela de cadastro gravava nada. A primeira
  prova estava errada e foi refeita: `Invoke-WebRequest` seguiu o redirecionamento de convidado sem
  avisar, a página medida era a de login (que tem token) e o `200` do POST era uma recusa disfarçada.
  Autenticado de verdade, com a URI final conferida, `/servicos/novo` mostrou dois formulários POST e um
  único token — o do logout — e o POST sem token respondeu `419`. Depois da correção o mesmo POST volta
  com erro de validação, o que prova que o pedido atravessou a barreira em vez de ser barrado por ela, e
  nada foi gravado. `CsrfTokenTest` (2 testes, 16 asserções) varre as 30 views de POST e ainda renderiza
  serviço, cliente e movimentação para conferir o `name="_token"` no HTML servido; a linha de CSRF do
  `README.md`, que afirmava uma cobertura que não existia, foi reescrita.

- O parágrafo de estágio do README ainda parava na fase 16, anunciando "o que ainda é só schema"
  depois de o plano ter fechado nas 22 fases mais a prontidão de deploy. Agora ele percorre as quatro
  faixas entregues e aponta para a tabela de Módulos, que é a fonte do que está no ar.

- O `DemoSeeder` remanejava para o mês corrente todo dinheiro recebido antes dele: `dataDeCaixa()` tinha
  um piso artificial na abertura do mês. Enquanto o painel só somava "Recebido no mês" o efeito era
  invisível; com a série mensal desenhada, o traço contaria cinco meses vazios e um pico que não aconteceu —
  a data do caixa mentindo para desenhar um número certo. A data do caixa agora é a data do caixa (número é
  dia relativo a hoje, `Carbon` é o dia em si) e o único teto que restou é hoje, porque dinheiro que ainda
  não entrou não é fato. O mês corrente continua garantido, mas por uma baixa datada de verdade no dia de
  hoje — a ordem em execução que tem peça no carrinho — e não por data empurrada.
- As doze ordens concluídas da demonstração terminavam todas vinte minutos depois do fim previsto, o que
  faria o medidor de pontualidade ler 0% numa carteira que se vende como entrega no prazo. Agora nove
  fecham antes do prazo e três depois (as de final 2 e 7), e o painel lê 75% vestindo o tom de espera que a
  própria proporção escolheu.
- Corrigida junto a hora dupla que o seeder passou a carregar: com as entregas dentro do prazo, a ficha
  dizia um momento e a trilha de estado, a saída da visita e a liberação da comissão diziam outro — o
  técnico aparecia deixando o endereço quarenta e cinco minutos depois de o serviço ter sido dado por
  concluído. A demonstração agora calcula `$entrega` uma vez e escreve a mesma hora nos quatro lugares.

### Removido

- `tests/Unit/ExampleTest.php` do skeleton, que apenas afirmava `true === true`: teste de
  fachada, sem regra de negócio para proteger.
- `tests/Feature/ExampleTest.php`, também do skeleton e em inglês, que só pedia `GET /` com 200. A
  mesma verificação ganhou nome em português e corpo em `AccessScreenTest`, com o título e o rótulo
  que a página de apresentação realmente desenha.
- A família de tokens `--nf-accent`, `--nf-accent-hover`, `--nf-accent-active` e `--nf-accent-soft`
  dos dois temas, o `.btn-accent` que os vestia e a variante `accent` do `x-ui.button` — que não tinha
  mais nenhum chamado no projeto. Latão não é cor desta marca, e token sem uso é cor emprestada.
- O CSS órfão do rodapé público que a linha única dispensou: `-top`, `-brand`, `-id`, `-tagline`,
  `-capacities`, `-side`, `-links` com o estado de hover, `-bottom` e as duas regras de celular que
  só reorganizavam essas peças. Ficou `-row`, que é o que existe na tela.
- As quatro classes que nenhuma interface vestia: `.form-text.nf-error` (o erro de campo é desenhado por
  `.invalid-feedback`, e o `form-text` desta casa é ajuda, não erro), `.nf-divider`, `.nf-surface` (a
  superfície é a variável `--nf-surface`, e o cartão já existe como `.card`) e `.nf-item-valor` (renomeada
  para `.nf-valor` na régua de dinheiro, e o nome antigo ficou na folha). A varredura do
  `CoerenciaVisualTest` passou a não permitir órfãs de novo.
### Conhecido

- O painel lê o banco e nada mais: os blocos operacionais existem, e as telas que produzem os de ordem,
  chamado, agenda, presença em campo e estoque já estão no ar. Falta a tela que alimenta o bloco
  financeiro, que chega na fase 17. Clientes, técnicos, equipes, especialidades, catálogo, ordens,
  chamados, visitas, agenda e estoque já estão no `Navigation` e alimentam os indicadores que dependem de
  cadastro; o que ainda não tem rota não tem link no menu, por decisão e não por descuido.
- A ficha da equipe e a do serviço já abrem a ordem pelo botão, porque `orders.show` existe desde a fase
  12, e a lista de produtos e a ficha do técnico já abrem o livro-caixa, porque `movements.index` existe
  desde a fase 16. O que continua sem link é o que não tem rota: nada na tela aponta para caminho que o
  aplicativo ainda não desenha.
- O raio aceito pelo check-in é lido de `company_settings` (`checkin_raio`, 250 m por padrão), mas ainda não
  há tela para a empresa escolhê-lo: hoje ele entra pelo banco — e a demonstração grava 300 m de propósito,
  para que a marca "fora do raio" apareça em tela. A tela de configurações é a fase 21. Registrar a chegada
  com o aparelho bloqueado continua sendo possível: a visita entra sem medida, marcada como tal.
- A conta de cliente já tem alcance (`users.client_id`), mas quem amarra o login à carteira hoje é o
  seeder e a mão do escritório no banco; a tela que escolhe a carteira é da fase 20 (usuários e papéis).
- O estoque tem um armazém só: o saldo central é a soma das linhas da empresa, sem coluna de localização,
  porque um depósito por empresa é o que o schema modela. Quanto há de um produto é leitura do banco, nunca
  campo editado — a tela que registra movimentação entrou na fase 16, e a que digita saldo não vai entrar
  em fase nenhuma. Ajuste de inventário continua sendo linha com sinal, não sobrescrita.
- A peça gasta na ordem é consumida do técnico que está na ficha ou no quadro de comissão da ordem; não há
  reserva de estoque nem baixa automática por linha de ordem, porque reservar material que pode não ser
  usado é outro desenho de operação. A linha cobrada da ordem e o consumo do estoque são fatos separados,
  ligados pela ordem que o consumo cita.
- O aviso de reposição vive na resposta que cruza o ponto e no cartão do painel. Avisar por e-mail ou
  notificação interna é a fase 19, e a tela que escolhe o ponto de reposição de cada produto já existe
  desde a fase 11.
- A demonstração mora na empresa `nexusfield-demo` e cria quatro usuários, um por papel de sistema
  que o painel distingue (`admin.demo@`, `gestor.demo@`, `campo.demo@`, `cliente.demo@`, todos em
  `nexusfield.local`), com senha vinda de `SEED_DEMO_PASSWORD` — que cai para `SEED_ADMIN_PASSWORD`
  e, sem nenhum dos dois, é gerada e mostrada no console. É fixture de tela, não histórico de
  operação: rodar o `DemoSeeder` limpa aquela empresa e regrava, e ela nunca deve ser semeada num
  ambiente que opere de verdade.
- O link de redefinição de senha sai pelo canal `log` (`MAIL_MAILER=log`), porque ainda não há SMTP
  configurado. Nada de credencial de e-mail no repositório: o servidor de envio entra por variável
  de ambiente quando for definido.
- Servida de um subdiretório (`http://localhost/nexusfield/public/`), a aplicação fica sem as
  fontes próprias e sem os ícones: o Vite gera as URLs de `@font-face` a partir da raiz do host.
  O caminho esperado é servir na raiz — `php artisan serve` ou um vhost apontando o
  `DocumentRoot` para `public/`, que foi como as telas foram verificadas.
- O `php` do `PATH` desta máquina é o 8.4.23 de `C:\Program Files\PHP`, sem `mbstring`; os
  comandos de artisan e de teste precisam usar o build do WAMP (`php8.3.28`), o mesmo que o
  Apache carrega.
- A suíte precisa rodar de um processo com permissão de escrita em `C:\wamp64`: sem isso a
  compilação de views cai em `tempnam()` e o teste devolve 500 no lugar da falha real. É restrição
  da máquina, não do projeto — `php vendor/phpunit/phpunit/phpunit` chamado do PowerShell grava as
  views compiladas e passa.
- `pint --test app resources/views tests` passa limpo nos 100 arquivos. O preset do Pint quer snake_case
  nos nomes de método de teste, e aqui o nome é frase em português — os dois convivem, e o único
  verdadeiro desvio (`test_anyCompany_...` no meio das frases) foi corrigido em vez de o padrão ser
  abandonado.

## [0.1.0] — 2026-10-07

### Adicionado

- Fundação do projeto com Laravel 13.35 (skeleton `laravel/laravel` v13.11) sobre PHP 8.3.28,
  o mesmo build carregado pelo Apache do WAMP, para que CLI e servidor web coincidam.
- `.env.example` documentando a conexão MySQL local (`127.0.0.1:3306`, schema `nexusfield`)
  e o aviso de que produção exige usuário próprio com senha forte.
- 15 migrations organizadas, criando 42 tabelas com 73 chaves estrangeiras e 1 constraint
  `CHECK`, todas em InnoDB: planos e empresas (núcleo multi-tenant), RBAC completo
  (`roles`, `permissions`, `permission_role`, `role_user`), usuários por empresa, clientes com
  contatos e endereços, técnicos com especialidades e equipes, catálogo de serviços e produtos,
  ordens de serviço com itens, responsáveis e histórico de status, check-in/check-out,
  chamados com comentários, agenda, financeiro com pagamentos, estoque por movimentação,
  notificações, configurações, auditoria e anexos.
- Constraint de domínio que impede um item de ordem de serviço de não ser nem serviço nem
  produto (`item_is_service_or_product`).
- Estoque sem coluna de saldo duplicada: a quantidade é derivada das movimentações, evitando
  a inconsistência entre saldo e histórico.
- Documentação de instalação no `README.md` e este `CHANGELOG.md`.

### Corrigido

- Valores monetários modelados em `DECIMAL(14,2)`, substituindo o `double` do modelo anterior,
  que acumulava erro de arredondamento em somas do financeiro.
- Textos operacionais (descrições, observações, notas de execução) migrados de `varchar(191)`
  para `TEXT`, eliminando truncamento silencioso de conteúdo.
- Campo de endereço da ordem de serviço separado em colunas próprias (rua, número, complemento,
  bairro, cidade, UF, CEP) em vez de uma string única.
- Geolocalização tipada como `DECIMAL(10,7)` em colunas de check-in e check-out distintos,
  no lugar de coordenadas guardadas como texto.

### Removido

- Dependência de código da implementação anterior (Node/Prisma): nada foi reaproveitado.
  As 15 tabelas legadas foram preservadas no schema `nexusfield_legacy` e em dump fora do
  repositório, e o schema `nexusfield` foi reconstruído do zero pelas migrations.
- Colunas duplicadas de anexos do modelo legado (`beforePhoto` e `before_photo_url`,
  `afterPhoto` e `after_photo_url`, `signatureBase64` e `signature_url`), substituídas pela
  tabela única `attachments`.

### Conhecido

- O MySQL deste servidor tem MyISAM como engine padrão. O projeto força InnoDB por
  configuração (`DB_ENGINE`), mas outras aplicações do mesmo servidor continuam expostas.
- O PHP disponível no `PATH` é um build reduzido (sem `mbstring`, `openssl`, `pdo_mysql`,
  `curl`); o Composer e o Artisan devem usar `C:\wamp64\bin\php\php8.3.28\php.exe`.
