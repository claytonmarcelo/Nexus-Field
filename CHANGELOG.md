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

### Corrigido

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

- O painel com KPIs operacionais chega na fase 8, junto do `DemoSeeder` local separado dos dados
  reais. O que existe hoje em `/dashboard` é sessão, empresa e o que já está no banco — a estrutura
  autenticada (barra lateral, cabeçalho, rodapé) está entregue e verificada em 320px, 768px,
  1440px e 2560px, nos dois temas.
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
