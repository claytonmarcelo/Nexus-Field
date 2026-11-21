# Etapa 1 — Baseline, inventário e desenho do módulo de organização

Data: 21/11/2026. Branch: `feature/organizacao-empresas`.

## Baseline executado antes de qualquer alteração de código

- Suíte completa: `353 testes / 3365 asserções`, 0 falhas, MySQL de teste.
- `composer audit`: nenhuma vulnerabilidade. `npm audit --omit=dev`: 0.
- `git diff --check` e varredura de BOM/CJK/invisíveis: limpos.
- Pesquisa de mercado registrada em `docs/mercado/ESTUDO-ORGANIZACAO-2026-11-21.md`.
- Nenhuma credencial ou dado pessoal real foi reproduzido neste documento.

## 15 linhas do que entendi

1. A empresa (`companies`) é o tenant raiz e hoje não tem hierarquia própria.
2. O tenant da requisição é resolvido por `TenantContext::resolveFromUser()` no middleware `ResolveCompany`.
3. `CompanyScope` filtra por `company_id` e só atua quando há tenant resolvido.
4. `BelongsToCompany` aplica o scope e preenche `company_id` no evento `creating`.
5. `User` não usa scope global porque o login precisa encontrar a conta antes de existir tenant.
6. O RBAC é por empresa: `roles`, `permissions`, `role_user` e `permission_role`.
7. `PermissionCatalog` é a fonte única de 17 módulos, ações, rótulos e matriz por papel.
8. `Roles::SLUGS` provê cinco papéis de sistema, sincronizados de forma idempotente.
9. A conta raiz é marcada por `is_root` no schema, fora do `$fillable`, com guardas no model.
10. A raiz possui todas as permissões do catálogo mesmo sem papéis.
11. `EnsurePermission` recusa com 403 no servidor antes de qualquer tela.
12. Usuários, papéis, configurações e auditoria são as quatro telas atuais de governo.
13. Preferências da empresa vivem em `company_settings` como JSON com chaves do `SettingsCatalog`.
14. `Auditor` escreve a trilha; `Auditable` automatiza o CRUD; `AuditController` só lê.
15. Os seeders separam a empresa real/RBAC/raiz da empresa de demonstração, que recusa produção.

## Inventário dos pontos de extensão

- `plans` já tem cotas (`max_users`, `max_technicians`, `max_clients`, ordens/mês) e `features` JSON.
- `companies` já tem `document`, `subscription_status`, `trial_ends_at`, `status` e `softDeletes`.
- `users` já tem `client_id` (carteira de cliente) e `is_root`; a filtragem por empresa é manual no `UserController`.
- `technicians.user_id` e `team_members.joined_at/left_at` são o padrão provado de vínculo com histórico.
- `roles` já suporta papéis personalizados por empresa; `RoleController` aplica "ninguém concede o que não tem".
- `company_settings`, `audit_logs`, `notifications` e `attachments` dão a base para política por empresa,
  auditoria, notificação e documentos privados.
- `ResolveCompany` roda antes de `SubstituteBindings`: essa ordem é contrato de isolamento.
- `DemoSeeder` recria uma empresa isolada e idempotente; a raiz pertence à empresa `nexusfield`.

## Modelo de dados proposto

Todas as tabelas novas usam `company_id` com `BelongsToCompany` + `CompanyScope`, `SoftDeletes`
quando histórico importa, `Auditable` nas escritas e índice composto começando por `company_id`.

### Cadastro da plataforma (Parte A)

| Tabela | Colunas principais | Índices / integridade |
| --- | --- | --- |
| `company_registrations` | `protocol` único (`NF-CAD-ANO-0001`), `state`, `dados` (JSON), `cnpj_hash`, `email_hash`, tokens hasheados com expiração, `ip`, `user_agent`, `submitted_at` | `unique(protocol)`, `unique(cnpj_hash)`, `index(state, created_at)`, `index(email_hash)` |
| `company_documents` | `registration_id`, `company_id`, `type`, `disk`, `path`, `sha256`, `mime`, `size`, `review_status`, `reviewed_by` | `index(company_id, type)`, `index(registration_id, review_status)` |
| `company_review_events` | `registration_id`, `from_state`, `to_state`, `actor_id`, `reason`, `ip`, `user_agent` | `index(registration_id, created_at)` |
| `legal_acceptances` | `registration_id`, `user_id`, `kind`, `version`, `accepted_at`, `ip`, `user_agent` | `index(user_id, kind)` |

Ampliação de `companies` sem remover colunas atuais: `legal_name`, `trade_name`, `cnpj` (único),
`ie`, `im`, `cnae_main`, `cnae_secondary` (JSON), `legal_nature`, `size`, `opened_at`, `tax_regime`,
`segment`, `employee_count`, `field_technician_count`, `contacts` (JSON), `legal_responsible` (JSON),
`lifecycle_status` e `registration_id`. Backfill: `legal_name = name`, `cnpj = document` quando válido,
`lifecycle_status = active` para toda empresa existente.

Fluxo fechado do pedido (somente Services movem o estado):
`rascunho -> email_pendente -> em_analise -> [pendencias <-> em_analise] -> aprovado | reprovado | cancelado | expirado`.
Cada transição grava `company_review_events` com autor, motivo, IP e data/hora. A aprovação cria
empresa ativa, estrutura padrão, papéis sincronizados e administrador inicial na **mesma transação**.

### Estrutura interna (Parte B)

| Tabela | Colunas principais | Índices / integridade |
| --- | --- | --- |
| `branches` | `company_id`, `name`, `slug`, `type` (matriz/filial), `manager_user_id`, `active` + `TemEnderecos` | `unique(company_id, slug)` |
| `departments` | `company_id`, `branch_id`, `parent_id`, `name`, `slug`, `description`, `manager_user_id`, `type`, `depth`, `path`, `active` | `unique(company_id, slug)`, `index(company_id, parent_id)`, `index(company_id, path)` |
| `department_service_categories` | `department_id`, `service_category_id`, `escopo` (interno/externo) | chave primária composta |
| `positions` | `company_id`, `department_id`, `name`, `level`, `description`, `suggested_role_id`, `active` | `index(company_id, department_id)`, `unique(company_id, department_id, name)` |
| `employments` | `company_id`, `user_id`, `branch_id`, `department_id`, `position_id`, `manager_id`, `employee_code`, `employment_type`, `status`, `started_at`, `ended_at` | `unique(company_id, employee_code)`, `index(company_id, user_id)`, `index(company_id, manager_id)`, `index(company_id, department_id)`, `index(company_id, position_id)` |
| `organization_scopes` | `company_id`, `user_id`, `scope`, `scope_id`, `active` | `index(company_id, user_id, scope)` |

Árvore de setores: `parent_id` + `path` materializado (`/1/4/9/`) para consultas de subárvore sem
recursão no PHP; validação de profundidade máxima e de ciclo no Service. `employments` nunca reescreve
histórico: mudança de setor/cargo/gestor fecha a linha anterior (`ended_at`) e abre outra.

## Política de alcance única

`App\Support\Alcance` será a única porta de entrada para "o que este usuário vê/faz". Ela combina
permissão (papel) e escopo organizacional e devolve um `Builder` já filtrado; a mesma chamada alimenta
tela, CSV, indicadores, notificações e feeds JSON.

| Perfil | Escopo padrão | Regra |
| --- | --- | --- |
| Raiz | plataforma | Não lê dados operacionais por padrão; só com acesso assistido temporário e auditado |
| Administrador da empresa | `company` | Tudo da própria empresa |
| Administrador de filial/setor | `branch` / `department` (subárvore) | Usuários e cargos apenas dentro do próprio escopo |
| Supervisor/gestor | `department` + `subordinates` + `teams` | Subárvore do setor e equipe/subordinados |
| Funcionário/técnico | `self` | Somente o próprio e o que já era atribuído hoje |
| Cliente | `client` | Carteira atual de `users.client_id` |
| Usuário sem estrutura (backfill) | `company` (ou `client` para conta de cliente) | Equivalência exata ao comportamento atual |

Regras de ouro: "ninguém concede o que não tem"; papel concede permissão, cargo apenas sugere papel;
todo `orWhere` que participe do alcance será agrupado em closure para não anular o tenant; nenhuma
tela poderá enviar `company_id`, `is_root`, `status`, `role_id` ou `manager_id` via mass assignment.

## Plano de commits atômicos

1. **E1** — baseline, inventário, desenho e decisão de auto-cadastro documentada (documentação, sem código).
2. **E2** — migrations, models, backfill idempotente e testes de equivalência para usuários atuais.
3. **E3** — `Alcance` + integração nos módulos existentes (D3) e testes de isolamento/equivalência.
4. **E4** — CRUD de filiais, setores, cargos, vínculos e organograma (B1-B4, B7).
5. **E5** — convites, delegação, importação em massa, desligamento e política de segurança por empresa.
6. **E6** — cadastro público em etapas, e-mails, anti-abuso e documentos privados (Parte A).
7. **E7** — painel da plataforma: validação, gestão de empresas e acesso assistido (Parte C).
8. **E8** — integração restante (D1, D2, D4-D7) e `DemoSeeder` ampliado.
9. **E9** — segurança do módulo: matriz de rotas, ZAP/Nuclei locais e correções com regressão.
10. **E10** — desempenho, comandos, acessibilidade, documentação final e relatório.

Cada etapa termina com suíte verde, diff limpo e sem BOM/CJK antes do commit.

## Decisões

Auto-cadastro, MySQL compatível, isolamento por `company_id`, presença da matriz por empresa e
equivalência para usuários sem estrutura já foram decididos no pedido. **Não há decisão nova de regra
de negócio pendente**, portanto o trabalho segue para a Etapa 2 sem esperar aprovação. Se aparecer
alguma escolha que mude regra de negócio, ela será destacada antes da edição.
