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

### Corrigido

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

### Removido

- `tests/Unit/ExampleTest.php` do skeleton, que apenas afirmava `true === true`: teste de
  fachada, sem regra de negócio para proteger.

### Conhecido

- O painel lê o banco e nada mais: os blocos operacionais existem, mas as telas que produzem esses
  dados (clientes, ordens, chamados, agenda, estoque, financeiro) chegam das fases 9 em diante. Por
  isso o painel de hoje se comporta como agregação de tabelas que o `DemoSeeder` preencheu — as
  rotas de CRUD ainda não estão no `Navigation`, e sem rota não há link no menu.
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
