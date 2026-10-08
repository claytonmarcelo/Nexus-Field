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
- 24 testes (64 asserções) cobrando login válido e inválido, usuário inativo, assinatura vencida,
  throttle, mudança de ID de sessão, logout, gate de permissão por papel, reset de senha com token
  válido/forgiado/fraco e isolamento entre tenants.
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

### Corrigido

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

- As telas de login, recuperação de senha e o layout AdminLTE autenticado chegam nas fases 6 e 7;
  os links de acesso da página de entrada apontam para rotas cuja view ainda não existe.
- Servida de um subdiretório (`http://localhost/nexusfield/public/`), a aplicação fica sem as
  fontes próprias e sem os ícones: o Vite gera as URLs de `@font-face` a partir da raiz do host.
  O caminho esperado é servir na raiz — `php artisan serve` ou um vhost apontando o
  `DocumentRoot` para `public/`.
- O `php` do `PATH` desta máquina é o 8.4.23 de `C:\Program Files\PHP`, sem `mbstring`; os
  comandos de artisan e de teste precisam usar o build do WAMP (`php8.3.28`), o mesmo que o
  Apache carrega.
- No Windows deste posto, `php artisan test` executa o `vendor/bin/phpunit` (`.bat`) através do
  `cmd.exe`, que não tem permissão de escrita em `C:\wamp64`; isso derruba a compilação de views
  durante os testes. Rodar `php vendor/phpunit/phpunit/phpunit` diretamente contorna o problema,
  que é da máquina e não do projeto.

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
