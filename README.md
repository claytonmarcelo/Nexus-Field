<p align="center">
  <img src="docs/branding/marca-nexus.png" alt="NEXUS-FIELD" width="96">
</p>

<h1 align="center">NEXUS-FIELD</h1>

<p align="center">
  <strong>Field Service Management — a operação externa inteira em um só lugar</strong><br>
  Plataforma multiempresa para gerenciar clientes, ordens de serviço, chamados, agenda, check-in em
  campo com geolocalização, estoque, financeiro, relatórios e auditoria no mesmo painel.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/vers%C3%A3o-0.1.0-blue" alt="Versão 0.1.0">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
  <img src="https://img.shields.io/badge/AdminLTE-4.10-343a40?logo=laravel&logoColor=white" alt="AdminLTE 4.10">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white" alt="Vite 8">
  <img src="https://img.shields.io/badge/testes-117%20testes%20%2F%20944%20asser%C3%A7%C3%B5es-brightgreen" alt="117 testes, 944 asserções">
</p>

<p align="center">
  <sub>Estágio atual: fundação completa (fases 1 a 8) e os cadastros e o catálogo no ar
  (fase 9 — clientes, fase 10 — técnicos, equipes e especialidades, fase 11 — serviços, produtos e
  categorias). A tabela
  <a href="#módulos">Módulos</a> diz, um por um, o que já está no ar e
  o que ainda é só schema.</sub>
</p>

---

## Telas

Capturas do aplicativo rodando (Laravel + AdminLTE 4 sobre MySQL), nos dois temas e em celular, todas
no mesmo quadro de **1440×900**, duas por linha e na mesma escala de exibição. Cada imagem é um link:
o clique abre o arquivo no tamanho capturado. O painel de celular é fotografado no viewport real dele
(390×844) e montado sobre o mesmo quadro de 1440×900 — é o único quadro diferente da série, e a
legenda diz isso. Os painéis mostram a empresa de demonstração criada pelo `DemoSeeder` — é ela que
tem ordens, chamados, financeiro e estoque para os indicadores calcularem; na empresa real sem dados,
os mesmos blocos aparecem nos estados vazios.

<div align="center">

| Apresentação pública · tema claro | Entrada · tema escuro |
| :--: | :--: |
| <a href="docs/screenshots/01-boas-vindas.png"><img src="docs/screenshots/01-boas-vindas.png" alt="Página de apresentação pública do NEXUS-FIELD em tema claro, com o ciclo de um serviço ao lado do título" width="360"></a> | <a href="docs/screenshots/02-entrada.png"><img src="docs/screenshots/02-entrada.png" alt="Tela de entrada com e-mail, senha com botão de revelar, manter conectado e recuperação de acesso em tema escuro" width="360"></a> |

| Recuperação de acesso · tema claro | Painel no celular · tema escuro |
| :--: | :--: |
| <a href="docs/screenshots/03-recuperar-acesso.png"><img src="docs/screenshots/03-recuperar-acesso.png" alt="Tela de recuperação de acesso pedindo o e-mail da conta em tema claro" width="360"></a> | <a href="docs/screenshots/06-painel-celular.png"><img src="docs/screenshots/06-painel-celular.png" alt="Painel com os indicadores empilhados em uma coluna, capturado num celular de 390px e montado sobre quadro 1440×900 em tema escuro" width="360"></a> |

| Painel operacional · tema claro | Painel operacional · tema escuro |
| :--: | :--: |
| <a href="docs/screenshots/04-painel-claro.png"><img src="docs/screenshots/04-painel-claro.png" alt="Painel em tema claro com doze indicadores, a fila de ordens da semana e a distribuição por estado" width="360"></a> | <a href="docs/screenshots/05-painel-escuro.png"><img src="docs/screenshots/05-painel-escuro.png" alt="Painel em tema escuro com os mesmos indicadores, tabelas e distribuição por estado" width="360"></a> |

</div>

---

## Sobre o projeto

O **NEXUS-FIELD** é uma plataforma FSM (Field Service Management): ela acompanha o serviço que
acontece fora da empresa, do chamado aberto até a assinatura do cliente no encerramento.

### Problema que resolve

Operação de campo costuma viver espalhada: a agenda em uma planilha, o cliente em outra, o técnico
no grupo de mensagens e a ordem de serviço em um documento anexado. O resultado é retrabalho,
histórico que não fecha e nenhum indicador confiável no fim do mês.

O NEXUS-FIELD centraliza essa cadeia em um fluxo só — cliente → ordem de serviço → chamado →
agenda → execução em campo com check-in e check-out geolocalizado → financeiro → relatório — com
cada etapa autorizada por permissão verificada no servidor e registrada em auditoria.

### Para quem

- Assistências técnicas e empresas de instalação e manutenção
- Field service de telecom, energia, refrigeração e TI
- Equipes de campo com ordens de serviço, SLA e visita agendada
- Operadoras que precisam medir custo, tempo e produtividade por técnico

### O que a plataforma entrega

- **Rastreabilidade**: cada estado de ordem de serviço e de chamado fica registrado com autor e data
- **Autorização real**: o servidor recusa antes de qualquer botão existir na tela
- **Multiempresa de verdade**: isolamento por empresa em toda consulta, não por convenção de tela
- **Indicador sem número inventado**: todo KPI do painel sai de uma contagem no MySQL daquela empresa
- **Interface nos dois temas**: claro e escuro, com a escolha persistida no navegador e no servidor

---

## Funcionalidades no ar

São as telas e regras que existem hoje no repositório. O que ainda não está aqui está listado em
[Módulos](#módulos) e no [Roadmap](#roadmap), com a fase em que chega.

### Autenticação

- Login com e-mail e senha, hash bcrypt e custo definido em `BCRYPT_ROUNDS`
- Sessão persistida em banco, com troca do identificador após entrar (contra session fixation)
- Bloqueio de usuário inativo e de empresa com assinatura vencida
- Limite de 5 tentativas por e-mail e IP, e throttle de 10 requests/min na rota de entrada
- Recuperação de acesso pelo password broker do framework: token de uso único, expiração própria e
  senha nova com no mínimo 10 caracteres, letras e números
- Botão de revelar senha em todo campo de senha, inclusive na redefinição

### Autorização e papéis

- Catálogo único de permissões (`PermissionCatalog`) com 17 módulos e ações por módulo
- Cinco papéis de sistema sincronizados pelo seeder: Administrador, Supervisor, Funcionário,
  Técnico e Cliente
- `User::hasPermission` com cache por request e middleware `permission`, que responde 403 no servidor
- Menu montado a partir da permissão: item sem rota ou sem permissão não é desenhado

### Multiempresa

- `TenantContext` por request, middleware `ResolveCompany`, escopo global `CompanyScope` e o trait
  `BelongsToCompany`
- `anyCompany()` reservado para agregação interna e console — tela nenhuma enxerga fora da própria empresa

### Painel

- Doze indicadores contados no banco da empresa logada, mais a fila de ordens da semana e a
  distribuição por estado
- Estados de interface reais: carregando, vazio, sem permissão e erro
- `DemoSeeder` local, que grava a demonstração numa empresa separada (`nexusfield-demo`) e recusa produção

### Cadastros

- **Clientes**: lista com busca, situação e cidade, paginação própria e exportação CSV; ficha com
  contatos e endereços cadastrados em linha; exclusão lógica com restauração, e o servidor recusa
  excluir quem já gerou ordem, chamado ou lançamento
- **Técnicos**: escala com busca, situação, região e especialidade; ficha com especialidades, equipes
  (com data de entrada e de saída), base de trabalho com endereços e os últimos check-ins medidos em
  campo, contados na tabela de check-in
- **Equipes**: quadro com líder, entrada e saída de membro gravadas na tabela intermediária — a
  passagem anterior continua histórica —, as ordens que a equipe tem em aberto e exclusão só permitida
  com quadro vazio
- **Especialidades**: catálogo com o tanto de técnico que cada uma cobre; o slug nasce do nome e a
  exclusão é recusada enquanto houver alguém usando-a
- Endereço é relação polimórfica compartilhada (`TemEnderecos`): cliente e técnico têm o mesmo
  formulário, o mesmo ciclo de vida e a mesma regra de endereço principal único
- As três telas de lista passam pelos mesmos `ListFilters`, pela mesma paginação própria e pelos mesmos
  estados desenhados de vazio e de nenhum resultado com estes filtros

### Catálogo

- **Serviços**: preço e duração estimada por linha do catálogo; a ficha mostra onde o serviço já foi
  aberto como ordem e já foi cobrado como item, contado no banco, e é essa soma que segura a exclusão
- **Produtos**: SKU único por empresa, unidade vinda do catálogo do projeto e saldo que ninguém digita —
  ele é a subquery que soma as movimentações com o sinal de cada tipo (`StockMovement::CENTRAL_SIGN`),
  então consumo não mexe no estoque central e ajuste de inventário pode baixar
- **Categorias de serviço**: agrupamento que a listagem de serviços usa como filtro; o slug nasce do
  nome e a exclusão é recusada enquanto houver serviço no grupo
- Nome de serviço, SKU e slug são únicos **dentro da empresa**, não no banco inteiro — a mesma regra de
  tenant que vale para cliente e técnico
- Serviço e produto com histórico não somem: inativar tira da escolha e preserva o preço praticado;
  excluir só é permitido quando a contagem do banco dá zero

### Interface

- AdminLTE 4 na estrutura oficial, Bootstrap 5.3 nos componentes e camada de tokens própria
- Paleta "Premium Gourmet + Technology": o verde cromado medido do próprio medalhão como marca,
  latão como contra-ponto, fundo preto no escuro e branco neutro no claro, sem casta de cor
- Tema claro/escuro persistido em `localStorage` e em cookie, aplicado antes da primeira pintura
- Diálogos e avisos só por SweetAlert2 e Toastr — `alert()`, `confirm()` e `prompt()` nativos são
  vetados no projeto e cobertos por teste
- Layout responsivo, com o painel em uma coluna no celular

---

## Módulos

O banco já modela o domínio inteiro (fase 2). As telas vêm uma fase por vez.

| Módulo | Schema | Permissões | Tela |
| --- | :---: | :---: | :---: |
| Autenticação e recuperação de acesso | ✅ | ✅ | ✅ |
| Painel e indicadores | ✅ | ✅ | ✅ |
| Página pública de apresentação | — | — | ✅ |
| Temas, diálogos e estados de interface | — | — | ✅ |
| Clientes e contatos | ✅ | ✅ | ✅ |
| Técnicos, equipes e especialidades | ✅ | ✅ | ✅ |
| Catálogo de serviços e produtos | ✅ | ✅ | ✅ |
| Ordens de serviço | ✅ | ✅ | 🚧 fase 12 |
| Chamados | ✅ | ✅ | 🚧 fase 13 |
| Agenda e compromissos | ✅ | ✅ | 🚧 fase 14 |
| Check-in / check-out com geolocalização | ✅ | ✅ | 🚧 fase 15 |
| Estoque e movimentações | ✅ | ✅ | 🚧 fase 16 |
| Financeiro | ✅ | ✅ | 🚧 fase 17 |
| Relatórios e exportações | ✅ | ✅ | 🚧 fase 18 |
| Notificações | ✅ | ✅ | 🚧 fase 19 |
| Usuários e papéis | ✅ | ✅ | 🚧 fase 20 |
| Configurações da empresa | ✅ | ✅ | 🚧 fase 21 |
| Auditoria | ✅ | ✅ | 🚧 fase 22 |

Legenda: ✅ no ar · 🚧 planejado, com a fase em que entra.

---

## Tecnologias

### Backend

| Tecnologia | Versão | Para quê |
| --- | --- | --- |
| PHP | 8.3 | Runtime da aplicação |
| Laravel | 13 | Framework: rotas, Eloquent, autenticação, sessões, seeders |
| MySQL | 8 | Banco relacional, InnoDB obrigatório por chave estrangeira e transação |
| PHPUnit | 12 | Testes unitários e de feature contra o MySQL real |

### Frontend

| Tecnologia | Versão | Para quê |
| --- | --- | --- |
| AdminLTE | 4.10 | Estrutura do painel (sidebar, navbar, cards) |
| Bootstrap | 5.3 | Grade, formulários e componentes |
| FontAwesome | 6.7 | Ícones |
| jQuery | 3.7 | Base UMD que Toastr e Summernote esperam |
| SweetAlert2 | 11 | Confirmação, informação e entrada de texto no lugar dos diálogos nativos |
| Toastr | 2.1 | Avisos efêmeros de canto, com HTML escapado |
| Summernote | 0.9 | Editor de texto rico — já declarado no bundle, entra em uso com o módulo de chamados |
| Vite | 8 | Build e dev server |
| Fontaine | 0.8 | Métricas de fonte para evitar troca de layout |

### Camada própria

| Nome | Para quê |
| --- | --- |
| `PermissionCatalog` | Módulos e ações de permissão em um único lugar, lidos por seeder, menu e middleware |
| `TenantContext` / `CompanyScope` / `ResolveCompany` | Isolamento por empresa em toda query |
| `DashboardMetrics` | As contagens do painel, todas em SQL contra a empresa logada |
| `StatusCatalog` / `Formatters` | Estados e formatações (dinheiro, decimal, data, hora e duração) num único lugar |
| `ListFilters` | Busca, filtro por coluna, ordenação e por-página lidos do query string |
| `Export` | CSV com BOM e separador `;`, escrito a partir da mesma consulta da tela |
| `Auditor` / `Auditable` | Trilha de auditoria gravada nas mudanças de estado, sem mudar tela nenhuma |
| `TemEnderecos` | Endereços polimórficos e o endereço principal de um cadastro |
| `EmEdicao` / `CuidaDeEnderecos` | Edição em linha na própria ficha e o ciclo de vida do endereço aninhado |
| `Navigation` | Menu montado por permissão e rota existente |
| `Nf` (`theme`, `toast`, `confirm`, `forms`, `flash`, `passwords`) | Únicos caminhos permitidos para tema, aviso e diálogo na tela |

---

## Estrutura de pastas

```text
nexusfield/
├── app/
│   ├── Http/
│   │   ├── Controllers/     → Welcome, Auth, Dashboard, Clients, Technicians, Catalog e os Concerns compartilhados
│   │   └── Middleware/      → ResolveCompany (tenancy) e EnsurePermission (autorização)
│   ├── Models/              → Company, Plan, User, Role, Permission, Client, Contact, Address,
│   │                          Technician, Team, Specialty, Service, ServiceCategory, Product…
│   └── Support/             → PermissionCatalog, Navigation, StatusCatalog, Formatters, TenantContext
├── bootstrap/               → inicialização e registro de rotas
├── config/                  → banco, sessão, filesystem, temas
├── database/
│   ├── migrations/          → 16 migrations do schema nexusfield
│   └── seeders/             → DatabaseSeeder (plano, empresa, RBAC, conta raiz) e DemoSeeder
├── docs/
│   ├── branding/            → o medalhão e a arte completa da marca oficial
│   └── screenshots/         → as seis capturas das telas
├── lang/pt_BR/              → validação e mensagens de senha em português
├── public/                  → index.php, favicon, img/ com a marca e assets compilados
├── resources/
│   ├── css/nexusfield/      → tokens.css, base.css, components.css, public.css, listings.css
│   ├── js/nexusfield/       → theme, notify, dialog, confirm, forms, flash, passwords, listas, jquery
│   └── views/               → Blade: componentes ui/ e layouts, páginas públicas, de entrada,
│                              de clientes, de técnicos, de equipes, de especialidades, de serviços,
│                              de produtos e de categorias
├── routes/                  → web.php
├── storage/                 → logs, cache e uploads (fora da raiz pública)
├── tests/
│   ├── Feature/             → entrada e recuperação, autorização por papel, tenancy, layout
│   │                          autenticado, painel, demonstração, conta raiz, clientes, técnicos
│   │                          e catálogo
│   └── Unit/                → paleta dos dois temas, contrato das capturas, iniciais do usuário
└── CHANGELOG.md             → histórico por fase
```

---

## Arquitetura

```text
┌────────────────────────────────────────────┐
│  Navegador                                 │
│  AdminLTE 4 + Bootstrap 5.3 + camada Nf    │
│  tema claro/escuro · SweetAlert2 · Toastr  │
└──────────────────┬─────────────────────────┘
                   │ HTTPS · sessão em banco · CSRF
                   ▼
┌────────────────────────────────────────────┐
│  Laravel 13                                 │
│  rotas web → middleware                     │
│  auth → company → permission                │
│  controllers finos, regras no model/support │
└──────────────────┬─────────────────────────┘
                   │ Eloquent escopado por empresa
                   ▼
┌────────────────────────────────────────────┐
│  MySQL 8 · InnoDB · utf8mb4                 │
│  companies ─┬─ users ─ roles ─ permissions  │
│             ├─ clients ─ contacts ─ addresses │
│             ├─ technicians ─ teams ─ specialties │
│             ├─ services ─ categories ─ products │
│             ├─ orders ─ tickets ─ appointments │
│             ├─ check-in ─ estoque ─ financeiro │
│             └─ settings ─ notifications ─ auditoria │
└────────────────────────────────────────────┘
```

A regra de dependência é uma só: tela nenhuma decide autorização. O middleware `permission` responde
403 antes de a view ser renderizada, e `CompanyScope` limita toda leitura à empresa do request.

---

## Banco de dados

**MySQL 8+**, schema `nexusfield`, engine InnoDB obrigatória (chave estrangeira e transação), charset
`utf8mb4` / `utf8mb4_unicode_ci`.

O schema é versionado em 16 migrations (`database/migrations/`) e cobre o domínio inteiro: planos e
empresas, usuários e RBAC, clientes e endereços, técnicos e equipes, catálogo de serviços e produtos,
ordens de serviço, estoque, check-in, chamados, agenda, financeiro, configurações, notificações,
auditoria e anexos.

```bash
php artisan migrate        # aplica o schema
php artisan db:seed        # plano, empresa, catálogo de permissões, papéis e a conta raiz
php artisan db:seed --class=DemoSeeder   # dados de demonstração, em empresa à parte
```

`db:seed` não é opcional: sem ele não há permissão cadastrada nem papel atribuído, e o login cai em
uma conta sem autorização nenhuma.

---

## Instalação

### Pré-requisitos

- PHP 8.3 com `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `zip`, `gd` e `intl`
- Composer 2
- Node.js 20+ e npm
- MySQL 8.x escutando em `127.0.0.1:3306`

No WAMP, use o PHP que o Apache carrega (`C:\wamp64\bin\php\php8.3.28\php.exe`). O `php` do `PATH`
pode ser um build reduzido, sem `mbstring` nem `openssl`, e faz Composer e Artisan falharem com erros
desconectados do problema real.

### Passo a passo

```bash
# 1. Clone o repositório
git clone https://github.com/claytonmarcelo/Nexus-Field.git
cd Nexus-Field

# 2. Instale as dependências e prepare o .env
composer install
cp .env.example .env
php artisan key:generate

# 3. Crie o schema no MySQL
mysql -u root -p -e "CREATE DATABASE nexusfield CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Ajuste as credenciais do banco e as chaves de credencial no .env (veja Configuração abaixo)

# 5. Aplique o schema, semeie o catálogo e monte a interface
php artisan migrate
php artisan db:seed
npm install
npm run build

# 6. Suba o servidor
php artisan serve
```

A aplicação sobe em `http://localhost:8000`.

### Atalho

O comando abaixo executa, nesta ordem, `composer install`, cópia de `.env.example` para `.env`,
`key:generate`, `migrate`, `db:seed`, `npm install` e `npm run build`:

```bash
composer run setup
```

### Em desenvolvimento

```bash
composer run dev   # servidor, filas e Vite juntos
php artisan serve  # apenas o servidor HTTP
```

### Contas e acesso

A conta criada pelo `db:seed` é a **conta raiz** do sistema: recebe o catálogo inteiro de permissões
e não pode ser excluída, desativada, remanejada de empresa nem ter o e-mail trocado — a regra está no
model (`User::booted()`), então vale mesmo para request de administrador. A senha é rotacionável:
troque o valor no `.env` e rode `php artisan db:seed` de novo, ou altere a senha por dentro da
aplicação. Em produção o seeder recusa senha gerada automaticamente; credencial nunca entra no código
nem no Git.

**Não há cadastro por conta própria (`/register`).** As contas deste estágio vêm do seeder: o seeder
padrão cria a conta raiz, e `DemoSeeder` cria as quatro contas de demonstração. O motivo de não abrir
auto-cadastro é de escopo, não de pressa: cada usuário nasce vinculado a uma empresa e a um papel,
então uma tela de cadastro teria que criar a empresa, escolher o plano e nomear o administrador na
mesma operação — decisão comercial, não um campo de formulário. A recuperação de acesso existe para
quem já tem conta e esqueceu a senha.

---

## Configuração

As variáveis de ambiente vivem no `.env`, que **não** é versionado; só o `.env.example` entra no Git,
com as chaves nomeadas e nenhuma credencial preenchida.

| Variável | Para quê |
| --- | --- |
| `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG` | Identidade e modo de execução |
| `APP_LOCALE` | Idioma da aplicação (`pt_BR`) |
| `APP_TIMEZONE` | Fuso da operação (`America/Sao_Paulo`); precisa bater com o `time_zone` do MySQL para as leituras de data do painel e da agenda |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexão MySQL |
| `DB_ENGINE` | Engine das tabelas; deve ser `InnoDB` |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Persistência e expiração de sessão |
| `CACHE_STORE`, `QUEUE_CONNECTION` | Cache e filas |
| `FILESYSTEM_DISK` | Disco padrão de upload |
| `MAIL_*` | Envio de e-mail (recuperação de acesso, notificações) |
| `BCRYPT_ROUNDS` | Custo do hash de senha; em teste usa valor baixo de propósito |
| `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | Credenciais do administrador semeado pela instalação |
| `SEED_DEMO_PASSWORD` | Senha dos usuários da empresa de demonstração (`DemoSeeder`) |

---

## Segurança

| Camada | Como está implementado |
| --- | --- |
| Senha | Hash bcrypt com custo em `BCRYPT_ROUNDS`; nunca em texto claro e nunca ecoada em `old()` |
| Sessão | Guardada em banco, com regeneração do ID no login e `invalidate` + `regenerateToken` no logout |
| CSRF | Habilitado em todo formulário POST |
| Força bruta | 5 tentativas por e-mail e IP, mais throttle de 10 requests/min na rota de entrada |
| Autorização | `EnsurePermission` no servidor, por permissão do catálogo; a tela não decide nada |
| Tenancy | `CompanyScope` global; leitura fora da empresa exige `anyCompany()` explícito |
| Conta raiz | `is_root` não é atribuível por request e a conta raiz resiste a exclusão, desativação e remanejamento |
| Diálogos | `alert()`, `confirm()` e `prompt()` nativos vetados e cobertos por teste; SweetAlert2 e Toastr escapam HTML |
| Segredos | `.env` fora do Git; `.env.example` sem valor; nenhuma credencial em código, seed ou teste |

---

## Testes

Os testes de feature usam MySQL (schema `nexusfield_test`, engine InnoDB), então crie o schema uma vez:

```sql
CREATE DATABASE IF NOT EXISTS nexusfield_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Depois:

```bash
php artisan test
```

Hoje são **117 testes / 944 asserções**, cobrindo login válido e inválido, usuário inativo, assinatura
vencida, throttle, troca de ID de sessão, logout, gate de permissão por papel, reset de senha com
token válido/forgiado/fraco, isolamento entre tenants, as três telas abertas de acesso, o contrato
do seletor de tema entre Blade e JavaScript, a paleta dos dois temas calculada até o contraste WCAG
e a marca verde medida do medalhão, o contrato das seis capturas deste README (existem, estão
linkadas e medem 1440×900), a proibição dos diálogos nativos do navegador, o CRUD de clientes com
contatos e endereços, o de técnicos, equipes e especialidades, e o do catálogo — inclusive o saldo
central somado das movimentações, a unidade fora do catálogo recusada e a exclusão vetada quando já
existe histórico.

No Windows, se `php artisan test` falhar ao compilar views com o aviso
`tempnam(): file created in the system's temporary directory`, rode o PHPUnit direto pelo
interpretador — o wrapper `vendor/bin/phpunit` é um `.bat` e herda as restrições de escrita do
`cmd.exe`:

```bash
php vendor/phpunit/phpunit/phpunit
```

---

## Demonstração

Não há demonstração online: o projeto roda localmente. A demonstração de dados é um seeder, grava
tudo numa empresa à parte (`nexusfield-demo`) e recusa produção:

```bash
php artisan db:seed --class=DemoSeeder
```

Os usuários criados são `admin.demo@nexusfield.local` (administrador),
`gestor.demo@nexusfield.local` (supervisor), `campo.demo@nexusfield.local` (técnico) e
`cliente.demo@nexusfield.local` (cliente). A senha não está neste README nem em lugar nenhum do
repositório: ela vem de `SEED_DEMO_PASSWORD`, que cai para `SEED_ADMIN_PASSWORD` quando não tem
valor; sem nenhum dos dois, o seeder gera uma e mostra no console. Rodar de novo limpa a empresa de
demonstração e regrava — é fixture de tela, não histórico de operação.

---

## Roadmap

### Concluído

- [x] **Fase 1** — Estrutura base e Git
- [x] **Fase 2** — Modelagem e migrations do banco `nexusfield` (16 migrations, InnoDB, FKs)
- [x] **Fase 3** — Autenticação, autorização por permissão e multi-tenancy
- [x] **Fase 4** — Design system "Premium Gourmet + Technology" sobre AdminLTE 4
- [x] **Fase 5** — Página pública de apresentação
- [x] **Fase 6** — Entrada, recuperação e redefinição de acesso
- [x] **Fase 7** — Layout autenticado com AdminLTE 4 oficial
- [x] **Fase 8** — Indicadores do painel contados no banco e `DemoSeeder` local
- [x] **Fase 8.1** — Conta raiz permanente, protegida no model
- [x] **Fase 8.2** — Refino de cabeçalho e rodapé, chave de tema em pílula e revelar senha
- [x] **Fase 8.3** — Paleta global: fundo preto no escuro, branco neutro no claro, texto acima do AA
- [x] **Fase 9** — Clientes: CRUD, filtros, paginação própria, ficha com contatos e endereços
- [x] **Fase 10** — Técnicos, equipes e especialidades: escala, quadro com histórico de passagem e base de trabalho
- [x] **Fase 11** — Catálogo de serviços, produtos e categorias: preço, duração, SKU e saldo lido das movimentações

### Em curso

- [ ] Fase 12 — Ordens de serviço

### Planejado

- [ ] Fase 13 — Chamados
- [ ] Fase 14 — Agenda (FullCalendar 6)
- [ ] Fase 15 — Check-in e check-out com geolocalização
- [ ] Fase 16 — Estoque e movimentações
- [ ] Fase 17 — Financeiro
- [ ] Fase 18 — Relatórios e exportações
- [ ] Fase 19 — Notificações
- [ ] Fase 20 — Telas de usuários e papéis
- [ ] Fase 21 — Configurações da empresa
- [ ] Fase 22 — Auditoria

---

## Histórico de versões

O detalhamento por fase está em [CHANGELOG.md](CHANGELOG.md). O baseline desta reconstrução é
`0.1.0`, e commits seguem a mesma regra: um por etapa funcional validada, em português brasileiro,
dizendo o que mudou e por quê.

---

## Desenvolvedor

<table>
  <tr>
    <td width="90" align="center">
      <img src="https://github.com/claytonmarcelo.png" width="90" alt="Clayton Marcelo">
    </td>
    <td>
      <strong>Clayton Marcelo</strong><br>
      Full stack — Laravel, MySQL, JavaScript<br><br>
      <a href="https://github.com/claytonmarcelo"><img src="https://img.shields.io/badge/GitHub-claytonmarcelo-181717?style=flat-square&logo=github" alt="GitHub"></a>
    </td>
  </tr>
</table>

---

<p align="center">
  <sub>NEXUS-FIELD · Clayton Marcelo · 2026</sub>
</p>
