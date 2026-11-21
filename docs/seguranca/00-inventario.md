# Fase 0 — Baseline e inventário de segurança

**Projeto:** NEXUS-FIELD  
**Branch:** `seguranca/producao` (criada a partir de `main`/`1dabbc06ccdd4fd9bac80145df47ab453cf9d5ae`)  
**Data do inventário:** 21/11/2026  
**Estado:** documentação da Fase 0. Nenhum arquivo de aplicação (`app/`, `config/`, `routes/`, `resources/`, `database/`, `tests/`) foi alterado nesta fase.

## 1. Entendimento em 15 linhas

1. O NEXUS-FIELD é um SaaS multiempresa de Field Service Management em Laravel 13.43.0, PHP 8.4.15, MySQL 8, AdminLTE 4/Bootstrap 5.3, Vite 8, FullCalendar 6, Summernote, SweetAlert2 e Toastr.
2. A suíte atual está verde: **345 testes / 3.330 asserções**; o badge do README (`318 / 3050`) está desatualizado e só será corrigido com número real na Fase 14.
3. As convenções da casa são nomes em português, camada `Services`, `Recusa`, `ListFilters`, `Export`, `Nf`, `Navigation` e permissões centralizadas em `PermissionCatalog`.
4. O isolamento multiempresa assenta em `TenantContext` + `CompanyScope` + trait `BelongsToCompany`, aplicados antes de `SubstituteBindings` (`bootstrap/app.php:18-28`).
5. A autorização tem duas camadas: middleware `permission:` na rota e alcance de registro nos controllers/services (`visiveisPara`, `Enxerga*`, `whereKey`).
6. `EnsurePermission` usa `hasAnyPermission` e devolve 403; `ResolveCompany` resolve o tenant a partir de `$request->user()`.
7. A auditoria é centralizada em `Auditor::gravar` e já exclui `password`, `remember_token`, tokens e datas técnicas do diff.
8. Exportações CSV passam por `App\Support\Export`: BOM UTF-8, separador `;`, streaming e neutralização parcial de fórmulas (`^[-+=@]` após `trim`).
9. Todo HTML rico renderizado com `{!! !!}` está em `resources/views/tickets/show.blade.php:143,279` e recebe saída de `TextoSeguro`.
10. O único upload atual é a marca da empresa em `SettingController`: valida `image|mimes|max|dimensions`, gera nome aleatório, mas grava bytes originais em `Storage::disk('public')`.
11. Não existe `routes/api.php`; os endpoints JSON/AJAX relevantes são `agenda.feed`, notificações e ações com `expectsJson`.
12. O ambiente local de baseline usa `APP_DEBUG=true`, `DB_USERNAME=root` sem senha, `SESSION_SECURE_COOKIE` ausente e `MAIL_MAILER=log`; `.env.example` traz um e-mail administrativo realista.
13. Foram encontradas rotas públicas de ferramenta (`POST /_boost/browser-logs`) e rotas assinadas do framework (`GET/PUT /storage/{path}`) sem auth/permissão de aplicação.
14. `composer audit` e `npm audit --omit=dev` estão limpos; Semgrep, gitleaks, Nuclei, ZAP e scanners equivalentes não estão instalados como binários locais (Docker está disponível).
15. A identidade visual “Premium Gourmet + Technology”, tokens, temas claro/escuro, marca e tipografia devem permanecer inalterados; o gate desta fase era entregar inventário e plano antes de editar código, e foi respeitado.

## 2. Evidências do baseline

| Comando | Resultado | Evidência |
|---|---|---|
| `php artisan test` | **345 testes, 3.330 asserções, 345 passados, 0 falhas** (175,1 s) | `C:\Users\Public\nexusfield-baseline-test.txt` |
| `composer audit` | `No security vulnerability advisories found.` | `C:\Users\Public\nexusfield-baseline-composer-audit.txt` |
| `npm audit --omit=dev` | `found 0 vulnerabilities` | `C:\Users\Public\nexusfield-baseline-npm-audit.txt` |
| `php artisan route:list --json` | **159 rotas** | `C:\Users\Public\nexusfield-baseline-routes.json` |
| `php artisan about` | Laravel 13.43.0; PHP 8.4.15; Composer 2.11.2; env `local`; debug habilitado; MySQL; cache/sessão/fila em banco | `C:\Users\Public\nexusfield-baseline-about.txt` |
| Versões de runtime | PHP 8.4.15, Composer 2.11.2, Node v26.12.1, npm 11.20.0 | saídas diretas |

> Nota: a suíte foi executada com PHP 8.4.15 embora o README e o `composer.json` declarem PHP ≥ 8.3. O `phpunit.xml` força `APP_ENV=testing`, MySQL `nexusfield_test` e `BCRYPT_ROUNDS=4`.

## 3. Classificação das rotas

Critério adotado:

- **pública**: nenhum middleware `auth` (inclui `guest`, rotas sem middleware e healthcheck);
- **autenticada sem permissão**: exige `auth` mas não possui `permission:` — são autoatendimento ou navegação básica;
- **autenticada com permissão**: exige `auth` + ao menos um `permission:`.

| Classe | Quantidade |
|---|---:|
| Pública | 11 |
| Autenticada sem permissão | 5 |
| Autenticada com permissão | 143 |
| **Total** | **159** |

Métodos HTTP: GET/HEAD 75, POST 29, PUT 25, DELETE 19, PATCH 11.

### 3.1 Rotas de escrita sem middleware de permissão

| Método | URI | Middleware | Avaliação Fase 0 |
|---|---|---|---|
| POST | `/_boost/browser-logs` | nenhum | Expoção de ferramenta de desenvolvedor; precisa ser desligada em produção (Fase 2). |
| POST | `/forgot-password` | `web, guest, throttle:5,1` | Pública por desenho; Fase 3 deve equalizar resposta e tempo. |
| POST | `/login` | `web, guest, throttle:10,1` | Pública por desenho; Fase 3 deve equalizar resposta e tempo. |
| POST | `/logout` | `web, auth` | Autoatendimento; já é POST com CSRF do grupo `web`. |
| PUT | `/perfil` | `web, auth, company` | Autoatendimento; sem permissão própria é aceitável, mas Fase 3 endurece validação/auditoria. |
| PUT | `/perfil/chave` | `web, auth, company` | Autoatendimento; Fase 3 exigirá confirmação do novo e-mail. |
| PUT | `/perfil/senha` | `web, auth, company` | Autoatendimento; Fase 3 exigirá política e invalidação de sessões. |
| POST | `/reset-password` | `web, guest, throttle:10,1` | Pública por desenho; Fase 3 verifica token/enumeração. |
| PUT | `/storage/{path}` | nenhum | Rota assinada do framework (`ReceiveFile`); sem auth/tenant. Não há geração de assinatura no código da aplicação no baseline; Fase 5 decide manter, proteger ou desligar. |

As demais rotas de escrita possuem ao menos um `permission:` e estão listadas na tabela completa abaixo.

## 4. Inventário de controllers

| Controller | Rotas | Ações observadas nas rotas |
|---|---:|---|
| `App\Http\Controllers\Agenda\AgendaController` | 10 | `index`, `store`, `feed`, `create`, `update`, `destroy`, `show`, `edit`, `mudarStatus`, `reagendar` |
| `App\Http\Controllers\Audit\AuditController` | 3 | `index`, `export`, `show` |
| `App\Http\Controllers\Auth\LoginController` | 3 | `create`, `store`, `destroy` |
| `App\Http\Controllers\Auth\PasswordResetController` | 4 | `request`, `email`, `reset`, `update` |
| `App\Http\Controllers\Auth\ProfileController` | 4 | `show`, `update`, `email`, `password` |
| `App\Http\Controllers\Catalog\ProductController` | 8 | `index`, `store`, `create`, `update`, `destroy`, `show`, `edit`, `restore` |
| `App\Http\Controllers\Catalog\ServiceCategoryController` | 4 | `index`, `store`, `update`, `destroy` |
| `App\Http\Controllers\Catalog\ServiceController` | 8 | `index`, `store`, `create`, `update`, `destroy`, `show`, `edit`, `restore` |
| `App\Http\Controllers\Clients\AddressController` | 3 | `store`, `update`, `destroy` |
| `App\Http\Controllers\Clients\ClientContactController` | 3 | `store`, `update`, `destroy` |
| `App\Http\Controllers\Clients\ClientController` | 9 | `index`, `store`, `export`, `create`, `update`, `destroy`, `show`, `edit`, `restore` |
| `App\Http\Controllers\DashboardController` | 1 | `__invoke` |
| `App\Http\Controllers\Finance\FinancialRecordController` | 12 | `index`, `store`, `export`, `create`, `update`, `destroy`, `show`, `cancel`, `edit`, `reopen`, `restore`, `cobrar` |
| `App\Http\Controllers\Finance\PaymentController` | 2 | `store`, `destroy` |
| `App\Http\Controllers\Notifications\NotificationController` | 3 | `index`, `marcarTodos`, `marcar` |
| `App\Http\Controllers\Orders\CheckinController` | 4 | `store`, `index`, `export`, `checkout` |
| `App\Http\Controllers\Orders\OrderAssignmentController` | 2 | `store`, `destroy` |
| `App\Http\Controllers\Orders\OrderController` | 9 | `index`, `store`, `export`, `create`, `update`, `destroy`, `show`, `edit`, `mudarStatus` |
| `App\Http\Controllers\Orders\OrderItemController` | 3 | `store`, `update`, `destroy` |
| `App\Http\Controllers\Reports\ReportController` | 6 | `index`, `chamados`, `estoque`, `financeiro`, `operacao`, `export` |
| `App\Http\Controllers\Settings\SettingController` | 2 | `index`, `update` |
| `App\Http\Controllers\Stock\MovementController` | 4 | `index`, `store`, `export`, `create` |
| `App\Http\Controllers\Technicians\SpecialtyController` | 4 | `index`, `store`, `update`, `destroy` |
| `App\Http\Controllers\Technicians\TeamController` | 9 | `index`, `store`, `create`, `update`, `destroy`, `show`, `edit`, `adicionarMembro`, `liberarMembro` |
| `App\Http\Controllers\Technicians\TechnicianAddressController` | 3 | `store`, `update`, `destroy` |
| `App\Http\Controllers\Technicians\TechnicianController` | 8 | `index`, `store`, `create`, `update`, `destroy`, `show`, `edit`, `restore` |
| `App\Http\Controllers\Tickets\TicketCommentController` | 1 | `store` |
| `App\Http\Controllers\Tickets\TicketController` | 8 | `index`, `store`, `export`, `create`, `update`, `show`, `edit`, `mudarStatus` |
| `App\Http\Controllers\Users\RoleController` | 7 | `index`, `store`, `create`, `update`, `destroy`, `show`, `edit` |
| `App\Http\Controllers\Users\UserController` | 7 | `index`, `store`, `create`, `update`, `destroy`, `show`, `edit` |
| `App\Http\Controllers\WelcomeController` | 1 | `index` |
| `Closure` (vendor Laravel Boost / Filesystem) | 3 | `_boost/browser-logs`, `storage.local`, `storage.local.upload` |
| `Illuminate\Foundation\Http\HealthCheck` | 1 | `/up` |

## 5. Tabela completa de rotas (159)

Legenda: `permission:a,permission:b` significa que o middleware foi aplicado mais de uma vez na mesma rota (exige ambas na implementação atual de `hasAnyPermission`, que usa **qualquer uma** — ponto a revisar na Fase 4).

| # | Método | URI | Nome | Ação | Middleware | Arquivo |
|---:|---|---|---|---|---|---|
| 1 | GET|HEAD | `/` | welcome | App\Http\Controllers\WelcomeController@index | web | N/A |
| 2 | POST | `_boost/browser-logs` | boost.browser-logs | Closure | N/A | vendor/laravel/boost/src/BoostServiceProvider.php:107 |
| 3 | GET|HEAD | `agenda` | agenda.index | App\Http\Controllers\Agenda\AgendaController@index | web<br>auth<br>company<br>permission:agenda.view | N/A |
| 4 | POST | `agenda` | agenda.store | App\Http\Controllers\Agenda\AgendaController@store | web<br>auth<br>company<br>permission:agenda.create | N/A |
| 5 | GET|HEAD | `agenda/eventos` | agenda.feed | App\Http\Controllers\Agenda\AgendaController@feed | web<br>auth<br>company<br>permission:agenda.view | N/A |
| 6 | GET|HEAD | `agenda/nova` | agenda.create | App\Http\Controllers\Agenda\AgendaController@create | web<br>auth<br>company<br>permission:agenda.create | N/A |
| 7 | PUT | `agenda/{compromisso}` | agenda.update | App\Http\Controllers\Agenda\AgendaController@update | web<br>auth<br>company<br>permission:agenda.update | N/A |
| 8 | DELETE | `agenda/{compromisso}` | agenda.destroy | App\Http\Controllers\Agenda\AgendaController@destroy | web<br>auth<br>company<br>permission:agenda.delete | N/A |
| 9 | GET|HEAD | `agenda/{compromisso}` | agenda.show | App\Http\Controllers\Agenda\AgendaController@show | web<br>auth<br>company<br>permission:agenda.view | N/A |
| 10 | GET|HEAD | `agenda/{compromisso}/editar` | agenda.edit | App\Http\Controllers\Agenda\AgendaController@edit | web<br>auth<br>company<br>permission:agenda.update | N/A |
| 11 | PUT | `agenda/{compromisso}/estado` | agenda.status | App\Http\Controllers\Agenda\AgendaController@mudarStatus | web<br>auth<br>company<br>permission:agenda.update | N/A |
| 12 | PATCH | `agenda/{compromisso}/janela` | agenda.reschedule | App\Http\Controllers\Agenda\AgendaController@reagendar | web<br>auth<br>company<br>permission:agenda.update | N/A |
| 13 | GET|HEAD | `auditoria` | audit.index | App\Http\Controllers\Audit\AuditController@index | web<br>auth<br>company<br>permission:audit.view | N/A |
| 14 | GET|HEAD | `auditoria/exportar` | audit.export | App\Http\Controllers\Audit\AuditController@export | web<br>auth<br>company<br>permission:audit.export | N/A |
| 15 | GET|HEAD | `auditoria/{ato}` | audit.show | App\Http\Controllers\Audit\AuditController@show | web<br>auth<br>company<br>permission:audit.view | N/A |
| 16 | GET|HEAD | `categorias-de-servico` | categories.index | App\Http\Controllers\Catalog\ServiceCategoryController@index | web<br>auth<br>company<br>permission:services.view | N/A |
| 17 | POST | `categorias-de-servico` | categories.store | App\Http\Controllers\Catalog\ServiceCategoryController@store | web<br>auth<br>company<br>permission:services.create | N/A |
| 18 | PUT | `categorias-de-servico/{categoria}` | categories.update | App\Http\Controllers\Catalog\ServiceCategoryController@update | web<br>auth<br>company<br>permission:services.update | N/A |
| 19 | DELETE | `categorias-de-servico/{categoria}` | categories.destroy | App\Http\Controllers\Catalog\ServiceCategoryController@destroy | web<br>auth<br>company<br>permission:services.delete | N/A |
| 20 | GET|HEAD | `chamados` | tickets.index | App\Http\Controllers\Tickets\TicketController@index | web<br>auth<br>company<br>permission:tickets.view | N/A |
| 21 | POST | `chamados` | tickets.store | App\Http\Controllers\Tickets\TicketController@store | web<br>auth<br>company<br>permission:tickets.create | N/A |
| 22 | GET|HEAD | `chamados/exportar` | tickets.export | App\Http\Controllers\Tickets\TicketController@export | web<br>auth<br>company<br>permission:tickets.view<br>permission:tickets.export | N/A |
| 23 | GET|HEAD | `chamados/novo` | tickets.create | App\Http\Controllers\Tickets\TicketController@create | web<br>auth<br>company<br>permission:tickets.create | N/A |
| 24 | PUT | `chamados/{chamado}` | tickets.update | App\Http\Controllers\Tickets\TicketController@update | web<br>auth<br>company<br>permission:tickets.update | N/A |
| 25 | GET|HEAD | `chamados/{chamado}` | tickets.show | App\Http\Controllers\Tickets\TicketController@show | web<br>auth<br>company<br>permission:tickets.view | N/A |
| 26 | GET|HEAD | `chamados/{chamado}/editar` | tickets.edit | App\Http\Controllers\Tickets\TicketController@edit | web<br>auth<br>company<br>permission:tickets.update | N/A |
| 27 | PUT | `chamados/{chamado}/estado` | tickets.status | App\Http\Controllers\Tickets\TicketController@mudarStatus | web<br>auth<br>company<br>permission:tickets.execute | N/A |
| 28 | POST | `chamados/{chamado}/notas` | tickets.comments.store | App\Http\Controllers\Tickets\TicketCommentController@store | web<br>auth<br>company<br>permission:tickets.view | N/A |
| 29 | GET|HEAD | `clientes` | clients.index | App\Http\Controllers\Clients\ClientController@index | web<br>auth<br>company<br>permission:clients.view | N/A |
| 30 | POST | `clientes` | clients.store | App\Http\Controllers\Clients\ClientController@store | web<br>auth<br>company<br>permission:clients.create | N/A |
| 31 | GET|HEAD | `clientes/exportar` | clients.export | App\Http\Controllers\Clients\ClientController@export | web<br>auth<br>company<br>permission:clients.view<br>permission:clients.export | N/A |
| 32 | GET|HEAD | `clientes/novo` | clients.create | App\Http\Controllers\Clients\ClientController@create | web<br>auth<br>company<br>permission:clients.create | N/A |
| 33 | PUT | `clientes/{cliente}` | clients.update | App\Http\Controllers\Clients\ClientController@update | web<br>auth<br>company<br>permission:clients.update | N/A |
| 34 | DELETE | `clientes/{cliente}` | clients.destroy | App\Http\Controllers\Clients\ClientController@destroy | web<br>auth<br>company<br>permission:clients.delete | N/A |
| 35 | GET|HEAD | `clientes/{cliente}` | clients.show | App\Http\Controllers\Clients\ClientController@show | web<br>auth<br>company<br>permission:clients.view | N/A |
| 36 | POST | `clientes/{cliente}/contatos` | clients.contacts.store | App\Http\Controllers\Clients\ClientContactController@store | web<br>auth<br>company<br>permission:clients.update | N/A |
| 37 | PUT | `clientes/{cliente}/contatos/{contato}` | clients.contacts.update | App\Http\Controllers\Clients\ClientContactController@update | web<br>auth<br>company<br>permission:clients.update | N/A |
| 38 | DELETE | `clientes/{cliente}/contatos/{contato}` | clients.contacts.destroy | App\Http\Controllers\Clients\ClientContactController@destroy | web<br>auth<br>company<br>permission:clients.delete | N/A |
| 39 | GET|HEAD | `clientes/{cliente}/editar` | clients.edit | App\Http\Controllers\Clients\ClientController@edit | web<br>auth<br>company<br>permission:clients.update | N/A |
| 40 | POST | `clientes/{cliente}/enderecos` | clients.addresses.store | App\Http\Controllers\Clients\AddressController@store | web<br>auth<br>company<br>permission:clients.update | N/A |
| 41 | PUT | `clientes/{cliente}/enderecos/{endereco}` | clients.addresses.update | App\Http\Controllers\Clients\AddressController@update | web<br>auth<br>company<br>permission:clients.update | N/A |
| 42 | DELETE | `clientes/{cliente}/enderecos/{endereco}` | clients.addresses.destroy | App\Http\Controllers\Clients\AddressController@destroy | web<br>auth<br>company<br>permission:clients.delete | N/A |
| 43 | PATCH | `clientes/{cliente}/restaurar` | clients.restore | App\Http\Controllers\Clients\ClientController@restore | web<br>auth<br>company<br>permission:clients.delete | N/A |
| 44 | GET|HEAD | `configuracoes` | settings.index | App\Http\Controllers\Settings\SettingController@index | web<br>auth<br>company<br>permission:settings.view | N/A |
| 45 | PUT | `configuracoes` | settings.update | App\Http\Controllers\Settings\SettingController@update | web<br>auth<br>company<br>permission:settings.manage | N/A |
| 46 | GET|HEAD | `dashboard` | dashboard | App\Http\Controllers\DashboardController | web<br>auth<br>company<br>permission:dashboard.view | N/A |
| 47 | GET|HEAD | `equipes` | teams.index | App\Http\Controllers\Technicians\TeamController@index | web<br>auth<br>company<br>permission:teams.view | N/A |
| 48 | POST | `equipes` | teams.store | App\Http\Controllers\Technicians\TeamController@store | web<br>auth<br>company<br>permission:teams.create | N/A |
| 49 | GET|HEAD | `equipes/nova` | teams.create | App\Http\Controllers\Technicians\TeamController@create | web<br>auth<br>company<br>permission:teams.create | N/A |
| 50 | PUT | `equipes/{equipe}` | teams.update | App\Http\Controllers\Technicians\TeamController@update | web<br>auth<br>company<br>permission:teams.update | N/A |
| 51 | DELETE | `equipes/{equipe}` | teams.destroy | App\Http\Controllers\Technicians\TeamController@destroy | web<br>auth<br>company<br>permission:teams.delete | N/A |
| 52 | GET|HEAD | `equipes/{equipe}` | teams.show | App\Http\Controllers\Technicians\TeamController@show | web<br>auth<br>company<br>permission:teams.view | N/A |
| 53 | GET|HEAD | `equipes/{equipe}/editar` | teams.edit | App\Http\Controllers\Technicians\TeamController@edit | web<br>auth<br>company<br>permission:teams.update | N/A |
| 54 | POST | `equipes/{equipe}/membros` | teams.members.store | App\Http\Controllers\Technicians\TeamController@adicionarMembro | web<br>auth<br>company<br>permission:teams.update | N/A |
| 55 | DELETE | `equipes/{equipe}/membros/{tecnico}` | teams.members.destroy | App\Http\Controllers\Technicians\TeamController@liberarMembro | web<br>auth<br>company<br>permission:teams.update | N/A |
| 56 | GET|HEAD | `especialidades` | specialties.index | App\Http\Controllers\Technicians\SpecialtyController@index | web<br>auth<br>company<br>permission:technicians.view | N/A |
| 57 | POST | `especialidades` | specialties.store | App\Http\Controllers\Technicians\SpecialtyController@store | web<br>auth<br>company<br>permission:technicians.create | N/A |
| 58 | PUT | `especialidades/{especialidade}` | specialties.update | App\Http\Controllers\Technicians\SpecialtyController@update | web<br>auth<br>company<br>permission:technicians.update | N/A |
| 59 | DELETE | `especialidades/{especialidade}` | specialties.destroy | App\Http\Controllers\Technicians\SpecialtyController@destroy | web<br>auth<br>company<br>permission:technicians.delete | N/A |
| 60 | GET|HEAD | `estoque` | movements.index | App\Http\Controllers\Stock\MovementController@index | web<br>auth<br>company<br>permission:stock.view | N/A |
| 61 | POST | `estoque` | movements.store | App\Http\Controllers\Stock\MovementController@store | web<br>auth<br>company<br>permission:stock.move,stock.adjust | N/A |
| 62 | GET|HEAD | `estoque/exportar` | movements.export | App\Http\Controllers\Stock\MovementController@export | web<br>auth<br>company<br>permission:stock.export | N/A |
| 63 | GET|HEAD | `estoque/registrar` | movements.create | App\Http\Controllers\Stock\MovementController@create | web<br>auth<br>company<br>permission:stock.move,stock.adjust | N/A |
| 64 | GET|HEAD | `financeiro` | financial.index | App\Http\Controllers\Finance\FinancialRecordController@index | web<br>auth<br>company<br>permission:financial.view | N/A |
| 65 | POST | `financeiro` | financial.store | App\Http\Controllers\Finance\FinancialRecordController@store | web<br>auth<br>company<br>permission:financial.create | N/A |
| 66 | GET|HEAD | `financeiro/exportar` | financial.export | App\Http\Controllers\Finance\FinancialRecordController@export | web<br>auth<br>company<br>permission:financial.view<br>permission:financial.export | N/A |
| 67 | GET|HEAD | `financeiro/nova` | financial.create | App\Http\Controllers\Finance\FinancialRecordController@create | web<br>auth<br>company<br>permission:financial.create | N/A |
| 68 | PUT | `financeiro/{registro}` | financial.update | App\Http\Controllers\Finance\FinancialRecordController@update | web<br>auth<br>company<br>permission:financial.update | N/A |
| 69 | DELETE | `financeiro/{registro}` | financial.destroy | App\Http\Controllers\Finance\FinancialRecordController@destroy | web<br>auth<br>company<br>permission:financial.delete | N/A |
| 70 | GET|HEAD | `financeiro/{registro}` | financial.show | App\Http\Controllers\Finance\FinancialRecordController@show | web<br>auth<br>company<br>permission:financial.view | N/A |
| 71 | PATCH | `financeiro/{registro}/cancelar` | financial.cancel | App\Http\Controllers\Finance\FinancialRecordController@cancel | web<br>auth<br>company<br>permission:financial.approve | N/A |
| 72 | GET|HEAD | `financeiro/{registro}/editar` | financial.edit | App\Http\Controllers\Finance\FinancialRecordController@edit | web<br>auth<br>company<br>permission:financial.update | N/A |
| 73 | POST | `financeiro/{registro}/pagamentos` | financial.payments.store | App\Http\Controllers\Finance\PaymentController@store | web<br>auth<br>company<br>permission:financial.create | N/A |
| 74 | DELETE | `financeiro/{registro}/pagamentos/{pagamento}` | financial.payments.destroy | App\Http\Controllers\Finance\PaymentController@destroy | web<br>auth<br>company<br>permission:financial.approve | N/A |
| 75 | PATCH | `financeiro/{registro}/reabrir` | financial.reopen | App\Http\Controllers\Finance\FinancialRecordController@reopen | web<br>auth<br>company<br>permission:financial.approve | N/A |
| 76 | PATCH | `financeiro/{registro}/restaurar` | financial.restore | App\Http\Controllers\Finance\FinancialRecordController@restore | web<br>auth<br>company<br>permission:financial.delete | N/A |
| 77 | GET|HEAD | `forgot-password` | password.request | App\Http\Controllers\Auth\PasswordResetController@request | web<br>guest | N/A |
| 78 | POST | `forgot-password` | password.email | App\Http\Controllers\Auth\PasswordResetController@email | web<br>guest<br>throttle:5,1 | N/A |
| 79 | GET|HEAD | `login` | login | App\Http\Controllers\Auth\LoginController@create | web<br>guest | N/A |
| 80 | POST | `login` | N/A | App\Http\Controllers\Auth\LoginController@store | web<br>guest<br>throttle:10,1 | N/A |
| 81 | POST | `logout` | logout | App\Http\Controllers\Auth\LoginController@destroy | web<br>auth | N/A |
| 82 | GET|HEAD | `notificacoes` | notifications.index | App\Http\Controllers\Notifications\NotificationController@index | web<br>auth<br>company<br>permission:notifications.view | N/A |
| 83 | PATCH | `notificacoes/todas-lidas` | notifications.mark-all | App\Http\Controllers\Notifications\NotificationController@marcarTodos | web<br>auth<br>company<br>permission:notifications.view | N/A |
| 84 | PATCH | `notificacoes/{aviso}/lida` | notifications.mark | App\Http\Controllers\Notifications\NotificationController@marcar | web<br>auth<br>company<br>permission:notifications.view | N/A |
| 85 | GET|HEAD | `ordens` | orders.index | App\Http\Controllers\Orders\OrderController@index | web<br>auth<br>company<br>permission:orders.view | N/A |
| 86 | POST | `ordens` | orders.store | App\Http\Controllers\Orders\OrderController@store | web<br>auth<br>company<br>permission:orders.create | N/A |
| 87 | GET|HEAD | `ordens/exportar` | orders.export | App\Http\Controllers\Orders\OrderController@export | web<br>auth<br>company<br>permission:orders.view<br>permission:orders.export | N/A |
| 88 | GET|HEAD | `ordens/nova` | orders.create | App\Http\Controllers\Orders\OrderController@create | web<br>auth<br>company<br>permission:orders.create | N/A |
| 89 | PUT | `ordens/{ordem}` | orders.update | App\Http\Controllers\Orders\OrderController@update | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 90 | DELETE | `ordens/{ordem}` | orders.destroy | App\Http\Controllers\Orders\OrderController@destroy | web<br>auth<br>company<br>permission:orders.delete | N/A |
| 91 | GET|HEAD | `ordens/{ordem}` | orders.show | App\Http\Controllers\Orders\OrderController@show | web<br>auth<br>company<br>permission:orders.view | N/A |
| 92 | POST | `ordens/{ordem}/chegada` | orders.checkin | App\Http\Controllers\Orders\CheckinController@store | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 93 | POST | `ordens/{ordem}/cobranca` | orders.charge | App\Http\Controllers\Finance\FinancialRecordController@cobrar | web<br>auth<br>company<br>permission:financial.create | N/A |
| 94 | GET|HEAD | `ordens/{ordem}/editar` | orders.edit | App\Http\Controllers\Orders\OrderController@edit | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 95 | PUT | `ordens/{ordem}/estado` | orders.status | App\Http\Controllers\Orders\OrderController@mudarStatus | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 96 | POST | `ordens/{ordem}/itens` | orders.items.store | App\Http\Controllers\Orders\OrderItemController@store | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 97 | PUT | `ordens/{ordem}/itens/{item}` | orders.items.update | App\Http\Controllers\Orders\OrderItemController@update | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 98 | DELETE | `ordens/{ordem}/itens/{item}` | orders.items.destroy | App\Http\Controllers\Orders\OrderItemController@destroy | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 99 | POST | `ordens/{ordem}/tecnicos` | orders.assignments.store | App\Http\Controllers\Orders\OrderAssignmentController@store | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 100 | DELETE | `ordens/{ordem}/tecnicos/{tecnico}` | orders.assignments.destroy | App\Http\Controllers\Orders\OrderAssignmentController@destroy | web<br>auth<br>company<br>permission:orders.update,orders.execute | N/A |
| 101 | GET|HEAD | `papeis` | roles.index | App\Http\Controllers\Users\RoleController@index | web<br>auth<br>company<br>permission:roles.view | N/A |
| 102 | POST | `papeis` | roles.store | App\Http\Controllers\Users\RoleController@store | web<br>auth<br>company<br>permission:roles.create | N/A |
| 103 | GET|HEAD | `papeis/novo` | roles.create | App\Http\Controllers\Users\RoleController@create | web<br>auth<br>company<br>permission:roles.create | N/A |
| 104 | PUT | `papeis/{papel}` | roles.update | App\Http\Controllers\Users\RoleController@update | web<br>auth<br>company<br>permission:roles.update | N/A |
| 105 | DELETE | `papeis/{papel}` | roles.destroy | App\Http\Controllers\Users\RoleController@destroy | web<br>auth<br>company<br>permission:roles.delete | N/A |
| 106 | GET|HEAD | `papeis/{papel}` | roles.show | App\Http\Controllers\Users\RoleController@show | web<br>auth<br>company<br>permission:roles.view | N/A |
| 107 | GET|HEAD | `papeis/{papel}/editar` | roles.edit | App\Http\Controllers\Users\RoleController@edit | web<br>auth<br>company<br>permission:roles.update | N/A |
| 108 | GET|HEAD | `perfil` | profile.show | App\Http\Controllers\Auth\ProfileController@show | web<br>auth<br>company | N/A |
| 109 | PUT | `perfil` | profile.update | App\Http\Controllers\Auth\ProfileController@update | web<br>auth<br>company | N/A |
| 110 | PUT | `perfil/chave` | profile.email | App\Http\Controllers\Auth\ProfileController@email | web<br>auth<br>company | N/A |
| 111 | PUT | `perfil/senha` | profile.password | App\Http\Controllers\Auth\ProfileController@password | web<br>auth<br>company | N/A |
| 112 | GET|HEAD | `produtos` | products.index | App\Http\Controllers\Catalog\ProductController@index | web<br>auth<br>company<br>permission:products.view | N/A |
| 113 | POST | `produtos` | products.store | App\Http\Controllers\Catalog\ProductController@store | web<br>auth<br>company<br>permission:products.create | N/A |
| 114 | GET|HEAD | `produtos/novo` | products.create | App\Http\Controllers\Catalog\ProductController@create | web<br>auth<br>company<br>permission:products.create | N/A |
| 115 | PUT | `produtos/{produto}` | products.update | App\Http\Controllers\Catalog\ProductController@update | web<br>auth<br>company<br>permission:products.update | N/A |
| 116 | DELETE | `produtos/{produto}` | products.destroy | App\Http\Controllers\Catalog\ProductController@destroy | web<br>auth<br>company<br>permission:products.delete | N/A |
| 117 | GET|HEAD | `produtos/{produto}` | products.show | App\Http\Controllers\Catalog\ProductController@show | web<br>auth<br>company<br>permission:products.view | N/A |
| 118 | GET|HEAD | `produtos/{produto}/editar` | products.edit | App\Http\Controllers\Catalog\ProductController@edit | web<br>auth<br>company<br>permission:products.update | N/A |
| 119 | PATCH | `produtos/{produto}/restaurar` | products.restore | App\Http\Controllers\Catalog\ProductController@restore | web<br>auth<br>company<br>permission:products.delete | N/A |
| 120 | GET|HEAD | `relatorios` | reports.index | App\Http\Controllers\Reports\ReportController@index | web<br>auth<br>company<br>permission:reports.view | N/A |
| 121 | GET|HEAD | `relatorios/chamados` | reports.chamados | App\Http\Controllers\Reports\ReportController@chamados | web<br>auth<br>company<br>permission:reports.view | N/A |
| 122 | GET|HEAD | `relatorios/estoque` | reports.estoque | App\Http\Controllers\Reports\ReportController@estoque | web<br>auth<br>company<br>permission:reports.view | N/A |
| 123 | GET|HEAD | `relatorios/financeiro` | reports.financeiro | App\Http\Controllers\Reports\ReportController@financeiro | web<br>auth<br>company<br>permission:reports.view | N/A |
| 124 | GET|HEAD | `relatorios/operacao` | reports.operacao | App\Http\Controllers\Reports\ReportController@operacao | web<br>auth<br>company<br>permission:reports.view | N/A |
| 125 | GET|HEAD | `relatorios/{relatorio}/exportar` | reports.export | App\Http\Controllers\Reports\ReportController@export | web<br>auth<br>company<br>permission:reports.view<br>permission:reports.export | N/A |
| 126 | POST | `reset-password` | password.update | App\Http\Controllers\Auth\PasswordResetController@update | web<br>guest<br>throttle:10,1 | N/A |
| 127 | GET|HEAD | `reset-password/{token}` | password.reset | App\Http\Controllers\Auth\PasswordResetController@reset | web<br>guest | N/A |
| 128 | GET|HEAD | `servicos` | services.index | App\Http\Controllers\Catalog\ServiceController@index | web<br>auth<br>company<br>permission:services.view | N/A |
| 129 | POST | `servicos` | services.store | App\Http\Controllers\Catalog\ServiceController@store | web<br>auth<br>company<br>permission:services.create | N/A |
| 130 | GET|HEAD | `servicos/novo` | services.create | App\Http\Controllers\Catalog\ServiceController@create | web<br>auth<br>company<br>permission:services.create | N/A |
| 131 | PUT | `servicos/{servico}` | services.update | App\Http\Controllers\Catalog\ServiceController@update | web<br>auth<br>company<br>permission:services.update | N/A |
| 132 | DELETE | `servicos/{servico}` | services.destroy | App\Http\Controllers\Catalog\ServiceController@destroy | web<br>auth<br>company<br>permission:services.delete | N/A |
| 133 | GET|HEAD | `servicos/{servico}` | services.show | App\Http\Controllers\Catalog\ServiceController@show | web<br>auth<br>company<br>permission:services.view | N/A |
| 134 | GET|HEAD | `servicos/{servico}/editar` | services.edit | App\Http\Controllers\Catalog\ServiceController@edit | web<br>auth<br>company<br>permission:services.update | N/A |
| 135 | PATCH | `servicos/{servico}/restaurar` | services.restore | App\Http\Controllers\Catalog\ServiceController@restore | web<br>auth<br>company<br>permission:services.delete | N/A |
| 136 | GET|HEAD | `storage/{path}` | storage.local | Closure | N/A | vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemServiceProvider.php:111 |
| 137 | PUT | `storage/{path}` | storage.local.upload | Closure | N/A | vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemServiceProvider.php:119 |
| 138 | GET|HEAD | `tecnicos` | technicians.index | App\Http\Controllers\Technicians\TechnicianController@index | web<br>auth<br>company<br>permission:technicians.view | N/A |
| 139 | POST | `tecnicos` | technicians.store | App\Http\Controllers\Technicians\TechnicianController@store | web<br>auth<br>company<br>permission:technicians.create | N/A |
| 140 | GET|HEAD | `tecnicos/novo` | technicians.create | App\Http\Controllers\Technicians\TechnicianController@create | web<br>auth<br>company<br>permission:technicians.create | N/A |
| 141 | PUT | `tecnicos/{tecnico}` | technicians.update | App\Http\Controllers\Technicians\TechnicianController@update | web<br>auth<br>company<br>permission:technicians.update | N/A |
| 142 | DELETE | `tecnicos/{tecnico}` | technicians.destroy | App\Http\Controllers\Technicians\TechnicianController@destroy | web<br>auth<br>company<br>permission:technicians.delete | N/A |
| 143 | GET|HEAD | `tecnicos/{tecnico}` | technicians.show | App\Http\Controllers\Technicians\TechnicianController@show | web<br>auth<br>company<br>permission:technicians.view | N/A |
| 144 | GET|HEAD | `tecnicos/{tecnico}/editar` | technicians.edit | App\Http\Controllers\Technicians\TechnicianController@edit | web<br>auth<br>company<br>permission:technicians.update | N/A |
| 145 | POST | `tecnicos/{tecnico}/enderecos` | technicians.addresses.store | App\Http\Controllers\Technicians\TechnicianAddressController@store | web<br>auth<br>company<br>permission:technicians.update | N/A |
| 146 | PUT | `tecnicos/{tecnico}/enderecos/{endereco}` | technicians.addresses.update | App\Http\Controllers\Technicians\TechnicianAddressController@update | web<br>auth<br>company<br>permission:technicians.update | N/A |
| 147 | DELETE | `tecnicos/{tecnico}/enderecos/{endereco}` | technicians.addresses.destroy | App\Http\Controllers\Technicians\TechnicianAddressController@destroy | web<br>auth<br>company<br>permission:technicians.delete | N/A |
| 148 | PATCH | `tecnicos/{tecnico}/restaurar` | technicians.restore | App\Http\Controllers\Technicians\TechnicianController@restore | web<br>auth<br>company<br>permission:technicians.delete | N/A |
| 149 | GET|HEAD | `up` | N/A | Illuminate\Foundation\Http\HealthCheck | N/A | N/A |
| 150 | GET|HEAD | `usuarios` | users.index | App\Http\Controllers\Users\UserController@index | web<br>auth<br>company<br>permission:users.view | N/A |
| 151 | POST | `usuarios` | users.store | App\Http\Controllers\Users\UserController@store | web<br>auth<br>company<br>permission:users.create | N/A |
| 152 | GET|HEAD | `usuarios/novo` | users.create | App\Http\Controllers\Users\UserController@create | web<br>auth<br>company<br>permission:users.create | N/A |
| 153 | PUT | `usuarios/{conta}` | users.update | App\Http\Controllers\Users\UserController@update | web<br>auth<br>company<br>permission:users.update | N/A |
| 154 | DELETE | `usuarios/{conta}` | users.destroy | App\Http\Controllers\Users\UserController@destroy | web<br>auth<br>company<br>permission:users.delete | N/A |
| 155 | GET|HEAD | `usuarios/{conta}` | users.show | App\Http\Controllers\Users\UserController@show | web<br>auth<br>company<br>permission:users.view | N/A |
| 156 | GET|HEAD | `usuarios/{conta}/editar` | users.edit | App\Http\Controllers\Users\UserController@edit | web<br>auth<br>company<br>permission:users.update | N/A |
| 157 | GET|HEAD | `visitas` | checkins.index | App\Http\Controllers\Orders\CheckinController@index | web<br>auth<br>company<br>permission:orders.view | N/A |
| 158 | GET|HEAD | `visitas/exportar` | checkins.export | App\Http\Controllers\Orders\CheckinController@export | web<br>auth<br>company<br>permission:orders.export | N/A |
| 159 | PATCH | `visitas/{visita}/saida` | checkins.checkout | App\Http\Controllers\Orders\CheckinController@checkout | web<br>auth<br>company<br>permission:orders.execute,orders.approve | N/A |

## 6. Pontos sensíveis exigidos pela Fase 0

### 6.1 Isolamento e escopo

| Padrão | Local | Situação observada |
|---|---|---|
| `anyCompany()` | `app/Models/CompanySetting.php:36-39` | Único uso. A consulta sai do escopo, mas aplica `where('company_id', TenantContext::id())` imediatamente e o tenant é obrigatório (`if ($companyId === null) return $default`). |
| `withoutGlobalScopes()` | `app/Http/Controllers/Settings/SettingController.php:136` | Atualização do perfil da empresa com `whereKey($empresa->id)`, onde `$empresa` vem de `empresa()` → `Company::query()->find(TenantContext::id())`. |
| `withoutGlobalScopes()` | `app/Http/Controllers/Settings/SettingController.php:194` | Gravação do `logo_path` com o mesmo `whereKey($empresa->id)`. |
| `BelongsToCompany` | `app/Models/Concerns/BelongsToCompany.php:11-29` | Aplica `CompanyScope` e força `company_id = TenantContext::id()` em `creating` quando há tenant. |
| `CompanyScope` | `app/Models/Scopes/CompanyScope.php:12-20` | Sem tenant, não filtra. Por isso a ordem `ResolveCompany` antes de `SubstituteBindings` é crítica. |

**Não verificado nesta fase:** cobertura exaustiva de todos os modelos com `company_id`, discriminadores de papel (`visiveisPara`) e corps aninhados. Isso entra na Fase 1 (SAST) e na Fase 4 (prova dinâmica).

### 6.2 SQL bruto

| Arquivo | Ocorrências | Origem do valor interpolado | Avaliação preliminar |
|---|---|---|---|
| `app/Support/DashboardMetrics.php` | 129, 266, 284, 644, 712 | Expressões fixas; `serieDiaria()` recebe `'completed_at'` (linha 123) e `'recorded_at'` (linha 375), ambos literais de código | Sem entrada do usuário na coluna. Manter lista fechada na Fase 1. |
| `app/Support/Relatorio.php` | 241, 261, 271, 275, 316, 329, 416, 524, 612, 626, 632, 644 | `colunaDoAngulo()` usa `match` fechado por `ANGULOS` (`prioridade`, `categoria`, `tecnico`); ordenação usa `ORDENAVEIS` | Sem entrada livre detectada. |
| `app/Models/Ticket.php` | 135 | `prazoSql()` fixo | Sem entrada livre detectada. |
| `app/Models/ServiceOrder.php` | 321 | `totalPorOrdemSql()` fixo | Sem entrada livre detectada. |
| `app/Models/Product.php` | 87, 96, 110, 126 | Subconsultas montadas em código com bindings | Revisar bindings na Fase 1. |
| `app/Models/FinancialRecord.php` | 285, 345, 362, 373 | Expressões fixas | Sem entrada livre detectada. |
| `app/Http/Controllers/Clients/ClientController.php` | 292 | Select fixo para a exportação | Sem entrada livre detectada. |

Não há `whereRaw`, `havingRaw` ou `fromRaw` com valor direto de request no inventário inicial. Ainda assim, toda ordenação/filtro passa por `ListFilters` (lista branca em `POR_PAGINA`, `ordenacaoAtual`, `busca`, `igual`, `periodo`).

### 6.3 HTML e saída não escapada

| Local | Fluxo | Situação |
|---|---|---|
| `resources/views/tickets/show.blade.php:143` | `{!! $relato !!}` | Alimentado por `TicketController@show`/relato do chamado; sanitização via `TextoSeguro` a confirmar na Fase 1. |
| `resources/views/tickets/show.blade.php:279` | `{!! $texto !!}` | Mesmo padrão para comentários/notas; sanitização via `TextoSeguro` a confirmar. |
| `app/Support/TextoSeguro.php:25-191` | Lista fechada de tags/atributos/esquemas | Tags proibidas com conteúdo incluem `script`, `style`, `iframe`, `object`, `embed`, `svg`, `math`, `img` e outras; esquemas aceitos: `http`, `https`, `mailto`, `tel`. Bypasses com entidades, mXSS e esquemas ofuscados devem ser testados na Fase 1. |
| `app/Support/Export.php:287-288` (TicketController) | `textoPlano()` antes do CSV | Texto rico é convertido para texto plano antes da exportação de chamados. |

### 6.4 Uploads,Downloads e armazenamento

| Ponto | Método/rota | Validação atual | Destino | Lacunas Fase 0 |
|---|---|---|---|---|
| Marca da empresa | `SettingController@guardarLogo` (`app/Http/Controllers/Settings/SettingController.php:93-107,186-199`) | `nullable, image, mimes:png,jpg,jpeg,webp, max:2042 KB, dimensions:64..1200 px` com extensão forçada pelo nome gerado | `storage/app/public/empresas/{id}/logo-{random}.{ext}`, exposto por `asset('storage/...')` em `sidebar`, `navbar` e `settings/index` | Sem `finfo`/MIME real, sem re-encode, sem remoção de EXIF, sem dimensionamento no servidor, sem rota autenticada; arquivo público. |
| Rota framework de upload | `PUT /storage/{path}` (`storage.local.upload`) | Apenas assinatura relativa válida com `?upload=1` (`ReceiveFile.php:45-48`) | `storage/app/private` (`config/filesystems.php:33-39`) | Sem auth, tenant ou permissão de aplicação; nenhuma geração de assinatura encontrada no código da aplicação. |
| Rota framework de leitura | `GET /storage/{path}` (`storage.local`) | Assinatura relativa ou disco público (`ServeFile.php:55-60`) | Idem | Em produção, assinatura inválida retorna 404 (`ServeFile.php:27-30`); ainda assim não há verificação de empresa. |

### 6.5 Exportações

Todas as exportações da aplicação usam `App\Support\Export::csv` (streaming, BOM, `;`):

| Rota | Controller | Permissão | Observação |
|---|---|---|---|
| `audit.export` | `AuditController@export` | `audit.export` | CSV da auditoria. |
| `clients.export` | `ClientController@export` | `clients.view` + `clients.export` | Mesma consulta da listagem. |
| `financial.export` | `FinancialRecordController@export` | `financial.view` + `financial.export` | Mesma consulta da listagem. |
| `movements.export` | `MovementController@export` | `stock.export` | Estoque. |
| `orders.export` | `OrderController@export` | `orders.view` + `orders.export` | Mesma consulta da listagem. |
| `checkins.export` | `CheckinController@export` | `orders.export` | Visitas. |
| `reports.export` | `ReportController@export` | `reports.view` + `reports.export` | Quatro relatórios. |
| `tickets.export` | `TicketController@export` | `tickets.view` + `tickets.export` | Texto rico convertido em texto plano. |

Neutralização atual em `Export::celula()` (`app/Support/Export.php:37-66`): aplica `trim()`, normaliza CRLF/CR e prefixa `'` quando o texto começa com `=`, `+`, `-` ou `@`. **Lacunas a provar na Fase 5:** aspas/controles iniciais, variantes Unicode, distinção entre número negativo legítimo e texto, e consistência com valores já formatados pela tela. Não há exportação XLSX/PDF ativa (Dompdf e Excel estão instalados, mas sem uso em `app/`).

### 6.6 Endpoints JSON/AJAX

| Rota | Método | Entrada validada | Saída |
|---|---|---|---|
| `agenda.feed` | GET | `inicio` e `fim` obrigatórios com regra `date`; `fim` limitado a `JANELA_MAXIMA_DIAS` após parse (`AgendaController.php:86-102`) | `JsonResponse` com ordens + compromissos. |
| `agenda.reschedule` | PATCH | Modelo `Appointment`, solicitação via `Request`; erros de `Recusa` viram JSON 422 (`AgendaController.php:232-246`) | `JsonResponse`. |
| `bootstrap/app.php:37-41` | — | Renderização JSON automática para `api/*` ou `expectsJson()` | Não existe `routes/api.php`; nenhuma rota `api/*` no baseline. |

As ações de notificação (`notificacoes.mark`, `notificacoes.mark-all`) retornam `RedirectResponse` e checam posse por `user_id` (`NotificationController.php:54-71`).

### 6.7 Operações perigosas e redirecionamento

| Padrão | Resultado da varredura |
|---|---|
| `unserialize(`, `eval(`, `exec(`, `shell_exec(`, `system(`, `passthru(`, `proc_open(`, `popen(`, `extract(`, `$$` | Nenhuma ocorrência em `app/`. |
| `Http::` | Nenhuma ocorrência em `app/`. |
| `file_get_contents` | Apenas `SettingController.php:193`, com `getRealPath()` do upload — não é URL. |
| `Storage::` | Apenas `SettingController.php:193` e `:198`, no disco `public`. |
| `redirect()->to($request->...)` / `redirect()->away` | Nenhuma ocorrência. `LoginController.php:57` usa `redirect()->intended(route('dashboard'))`; há vários `back()` — Fase 1 verifica host/intended. |
| Cabeçalhos de configuração | Não há `SecurityHeaders`, `TrustProxies` ou `TrustHosts` explícitos. `APP_URL` é usado para links e para a URL do disco `public`. |

## 7. Configuração e ambiente — sinais para Fase 2

| Item | Valor atual | Risco em produção / ação planejada |
|---|---|---|
| `APP_DEBUG` | `true` no `.env.example`; `config/app.php:42` usa default `false` | Exigir `false` no comando `nf:verificar-producao`. |
| `APP_KEY` | Vazia no exemplo; `config/app.php:105` | Obrigatória em produção; never commit real key. |
| `DB_USERNAME` / `DB_PASSWORD` | `root` / vazio no exemplo | Substituir por usuário de privilégio mínimo e Documentar separação migrate/runtime. |
| `SESSION_SECURE_COOKIE` | Não definida no `.env.example`; `config/session.php:172` usa `env()` sem default | Definir `true` em produção. |
| `SESSION_HTTP_ONLY` / `SESSION_SAME_SITE` | Defaults `true` / `lax` no config, mas ausentes no exemplo | Explicitar no exemplo e no verificador. |
| `SESSION_LIFETIME` | 120 min | Definir política com inatividade e expiração absoluta na Fase 3. |
| `BCRYPT_ROUNDS` | 12 no exemplo; 4 em `phpunit.xml` | Manter ≥ 12 em produção; testes continuam rápidos. |
| `MAIL_MAILER` | `log`; `MAIL_SCHEME` sem default | Exigir TLS e host/porta configurados em produção. |
| `TrustHosts` / `TrustProxies` | Não configurados | Configurar domínio e proxy real; never `*`. |
| Cabeçalhos/CSP | Nenhum middleware | Fase 2 com nonce, primeiro em Report-Only. |
| Ferramentas de debug | `laravel/boost` registra `/_boost/browser-logs` quando `boost.browser_logs_watcher` não é `false` | Desligar em produção. Verificar Telescope/Debugbar/Ignition/Horizon. |
| Dados de exemplo | `SEED_ADMIN_EMAIL` com valor realista no exemplo e referência ao e-mail aposentado em `DatabaseSeeder::RETIRED_ROOT_EMAIL` (endereço não reproduzido neste documento) | Fase 9 troca por referência configurável e decide rotação. |

## 8. Achados preliminares (não são ainda “correções”)

| ID | Sinal | Severidade preliminar | Status |
|---|---|---|---|
| F0-01 | `PUT /storage/{path}` é público e assinado, sem auth/tenant/permissão de aplicação | A investigar (potencial P0/P1) | Não verificado; Fase 5 decide. |
| F0-02 | `POST /_boost/browser-logs` sem middleware | Baixa em local / a confirmar em produção | Fase 2 desliga. |
| F0-03 | Ausência de `SecurityHeaders`, CSP, TrustHosts e TrustProxies | P1/P2 | Fase 2. |
| F0-04 | Upload de marca sem verificação de MIME real/re-encode e com servir público | P1 | Fase 5. |
| F0-05 | `Export::celula()` cobre apenas parte dos payloads de CSV injection | P1 | Fase 5. |
| F0-06 | `.env.example` com e-mail administrativo realista e referência a e-mail antigo | P1 (reputação/segredo) | Fase 9. |
| F0-07 | 86 rotas com parâmetro de modelo ainda sem teste dinâmico de isolamento | P0 para produção | Fase 4. |
| F0-08 | Perfil e logout são escrita autenticada sem `permission:` | Aceitável por design, mas sem 2FA/sessões/rate limit próprios | Fase 3. |
| F0-09 | Nenhuma suíte SAST/DAST instalada; scanners externos ausentes | P1 de processo | Fases 1, 7 e 8. |
| F0-10 | Badge do README desatualizado | Baixa | Fase 14, apenas com número real. |

> Nada acima foi marcado como “corrigido”. Correção só será afirmada depois de teste/scan que prove, conforme a regra do usuário.

## 9. O que NÃO foi verificado nesta fase

- SAST (Larastan/Pint/Enlightn/Semgrep/gitleaks) e DAST (ZAP/Nuclei) — Fases 1, 7 e 9.
- Mass assignment modelo a modelo e enumeração de `fillable` — Fase 1.
- Isolamento dinâmico entre duas empresas e matriz papel × rota — Fase 4.
- Concorrência em pagamentos/estoque/check-in — Fase 6.
- Comportamento TLS, DNS, proxy reverso e configuração real de servidor — depende do usuário; documentado como ação externa.
- Comparação com o link externo enviado pelo usuário será feita de forma **passiva e somente leitura**, sem scanner ativo contra domínio de terceiro, e integrada à Fase 11.
- Nenhuma credencial recebida foi ou será gravada em código, teste, documentação ou log.

## 10. Decisões a registrar em `docs/DECISOES.md` (Fase 1 em diante)

1. Manter rotas de perfil e logout sem `permission:` por serem autoatendimento, reforçando posse e auditoria.
2. CSP: começar em Report-Only; se Summernote/FullCalendar/AdminLTE exigirem `unsafe-inline` para estilos, justificar antes de ativar enforcing.
3. `PUT /storage/{path}`: desligar, ou manter apenas com assinatura de curta duração e validação explícita de tenant/permissão na geração da URL.
4. Marca da empresa: armazenamento privado + rota autenticada, com `Content-Disposition` e `nosniff`.
5. `.env.example`: trocar e-mail realista por `SEED_ADMIN_EMAIL` configurável sem valor real; nunca reescrever histórico git sem autorização.
6. Expor `/up` apenas ao balanceador; não revelar versão nem detalhes.

## 11. Plano detalhado de commits

Todos os commits serão na branch `seguranca/producao`, atômicos, sem push. Cada fase termina com testes verdes, relatório em `docs/seguranca/` e entrada no `CHANGELOG.md`. O plano abaixo é o contrato de trabalho a partir deste ponto; itens das Fases 11/12 serão confirmados pelas pesquisas antes de virar código.

### Fase 0 — baseline e inventário

1. `docs(seguranca): inventaria rotas, controles e baseline de seguranca`

### Fase 1 — SAST

2. `chore(dev): adiciona Larastan, Pint, Enlightn e local-php-security-checker`
3. `chore(dev): configura PHPStan nivel alto com baseline minima e Pint`
4. `test(seguranca): adiciona regressoes para achados reais do SAST`
5. `fix(seguranca): corrige achados reais de IDOR, mass assignment, validacao e XSS`
6. `docs(seguranca): registra Fase 1, falsos positivos e decisoes`

### Fase 2 — configuração e cabeçalhos

7. `feat(seguranca): middleware SecurityHeaders com nonce e CSP em report-only`
8. `feat(views): aplica nonce ao script de tema sem alterar o visual`
9. `test(seguranca): cobre cabecalhos em rotas publicas, autenticadas, JSON e erro`
10. `chore(config): endurece cookies, debug, proxies, hosts e APP_KEY`
11. `feat(console): comando nf:verificar-producao com exit code`
12. `chore(seguranca): desliga rotas de debug do Boost em producao`
13. `docs(operacao): documenta Nginx/Apache, dotfiles, storage e limites de body`
14. `docs(seguranca): ativa CSP enforcing e registra Fase 2`

### Fase 3 — autenticação, sessão e conta

15. `feat(auth): equaliza login e recuperacao sem enumeracao`
16. `feat(auth): politica de senha, minimo 12 e senhas vazadas opcionais`
17. `feat(auth): TOTP opcional e obrigatorio por papel/empresa com recuperacao`
18. `feat(auth): lista de sessoes, encerramento remoto e invalidacao sensivel`
19. `feat(auth): convite assinado de uso unico e confirmacao de novo email`
20. `test(auth): cobre 2FA, sessoes, reset, bloqueio e auditoria`
21. `docs(seguranca): registra Fase 3`

### Fase 4 — autorização e isolamento

22. `test(seguranca): isolamento dinamico guiado por rotas com parametro`
23. `test(seguranca): valida campos de corpo e matriz papel x rota`
24. `test(seguranca): escalada vertical e regra de arquitetura de tenant`
25. `fix(seguranca): corrige vazamentos de isolamento encontrados`
26. `test(seguranca): isolamento em filas, jobs e comandos agendados`
27. `docs(seguranca): registra Fase 4 com prova dinamica`

### Fase 5 — uploads, exportações e arquivos

28. `fix(export): neutralizacao completa de CSV injection com payloads OWASP`
29. `feat(upload): MIME real, re-encode e storage privado de marca/anexos`
30. `feat(upload): download autenticado com Content-Disposition e nosniff`
31. `test(seguranca): polyglots, dupla extensao, traversal e CSV injection`
32. `docs(seguranca): registra Fase 5`

### Fase 6 — injeções e lógica de negócio

33. `test(seguranca): payloads polyglot em texto, HTML, JSON, CSV, PDF e email`
34. `fix(seguranca): corrige XSS, CRLF, template injection e limites de entrada`
35. `feat(seguranca): idempotencia e lock em pagamento, estoque e check-in`
36. `test(seguranca): concorrencia, limites, paginacao e wildcards`
37. `docs(seguranca): registra Fase 6`

### Fase 7 — teste ativo em instância local

38. `chore(seguranca): docker-compose.security.yml e DemoSeeder isolado`
39. `chore(seguranca): contexto ZAP autenticado por papel e Automation Framework`
40. `docs(seguranca): executa ZAP baseline e full scan por papel`
41. `docs(seguranca): executa Nuclei e scanners complementares locais`
42. `fix(seguranca): corrige achados ativos e adiciona regressoes`
43. `docs(seguranca): relatorio de pentest com CWE, CVSS e status`

### Fase 8 — dependências e cadeia de suprimentos

44. `chore(deps): atualiza patch/minor e adiciona .npmrc ignore-scripts`
45. `ci(seguranca): Dependabot, CodeQL e secret scanning`
46. `ci(test): MySQL 8, Pint, Larastan, auditorias, build e scanners`
47. `chore(assets): remove CDN ou aplica SRI`
48. `docs(seguranca): registra Fase 8`

### Fase 9 — segredos e histórico git

49. `chore(segredos): substitui emails reais por configuracao sem valor`
50. `docs(seguranca): varredura de historico e rotacao de segredos`
51. `chore(git): reforca .gitignore para .env, dumps e relatorios`
52. `docs(seguranca): registra Fase 9`

### Fase 10 — LGPD e auditoria

53. `docs(lgpd): inventario de dados pessoais e bases legais`
54. `feat(lgpd): exportacao e anonimizacao do titular com auditoria`
55. `feat(lgpd): retencao configuravel e comando de limpeza`
56. `feat(lgpd): auditoria imutavel com hash encadeado`
57. `feat(legal): privacidade, termos, cookies e /status no visual da casa`
58. `test(lgpd): cobre direitos do titular e retencao`
59. `docs(seguranca): registra Fase 10`

### Fase 11 — pesquisa de mercado

60. `docs(mercado): analise competitiva com fontes e datas de acesso`
61. `docs(mercado): matriz de funcionalidades e priorizacao P0/P1/P2`
62. `docs(mercado): incorpora comparacao passiva do link externo enviado`

### Fase 12 — implementação das lacunas

63. `feat(os): anexos e fotos antes/depois com storage privado`
64. `feat(os): assinatura digital e PDF do relatorio de servico`
65. `feat(os): checklists dinamicos por tipo de servico`
66. `feat(orcamento): proposta com aprovacao por link assinado`
67. `feat(portal): acompanhamento de OS e chamados pela conta Cliente`
68. `feat(pwa): manifest, service worker e fila offline com idempotencia`
69. `feat(notificacoes): provedor configuravel de email, SMS e WhatsApp`
70. `feat(api): Sanctum, escopos, OpenAPI e webhooks HMAC`
71. `feat(importacao): CSV em massa com previa e relatorio de erros`
72. `feat(contratos): manutencao preventiva recorrente e idempotente`
73. `feat(nps): pesquisa de satisfacao por link assinado`
74. `feat(backup): verificacao, retencao e restore testado`
75. `feat(observabilidade): request-id, Sentry com scrubbing e alertas`
76. `feat(sso): Socialite por empresa sem criacao automatica de conta`
77. `feat(ativos): equipamentos do cliente com historico`
78. `docs(roadmap): registra fatias adiadas e feature flags`
79. `docs(seguranca): registra Fase 12`

> Cada item da Fase 12 só entra se a Fase 11 confirmar valor; fatias grandes serão quebradas com feature flag desligada, sem tela inacabada.

### Fase 13 — desempenho e operação

80. `perf(indices): migrations de indice e prova de consulta`
81. `perf(queries): elimina N+1 e testes de contagem de queries`
82. `perf(cache): cache por tenant com invalidacao`
83. `chore(filas): jobs, tentativas, backoff e supervisor documentado`
84. `chore(agendador): withoutOverlapping, onOneServer e alerta de falha`
85. `test(e2e): Playwright Chrome, Firefox, WebKit e 390px nos dois temas`
86. `test(a11y): axe/pa11y WCAG 2.2 AA nas telas principais`
87. `docs(operacao): 429 com marca, limites, erro sem vazamento e runbooks`
88. `docs(seguranca): registra Fase 13`

### Fase 14 — documentação e entrega

89. `docs(entrega): roadmap, decisoes e documentacao operacional`
90. `docs(api): openapi.yaml`
91. `docs(seguranca): relatorio final com evidencias e riscos residuais`
92. `docs(readme): secao Seguranca e Producao com numeros reais`
93. `changelog: consolida versao e notas de entrega`

## 12. Gate atual

Este documento e o plano de commits são entregues **antes de qualquer alteração de código**. O próximo passo é a Fase 1 (SAST), somente após esta Fase 0 ser apresentada. Nenhum push será feito sem pedido explícito.
