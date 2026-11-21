# Análise competitiva e estratégia de diferenciação

**Projeto:** NEXUS-FIELD  
**Data de acesso das fontes:** 21/11/2026  
**Branch:** `seguranca/producao`  
**Base interna:** `README.md` e código da aplicação; suíte verde de **345 testes / 3.330 asserções**.

---

## 1. Metodologia

- Priorizei fontes oficiais (site do produto, documentação, página de preços). Comparativos de terceiros ficam marcados como **apoio não oficial**.
- O painel `https://goldenrastreamento.com.br` foi tratado como **fonte primária observada de forma passiva e somente leitura**, com navegação autenticada pelo próprio navegador. **Nenhum scanner ativo**, nenhuma requisição forçada e nenhuma credencial registrada em arquivo, teste, documento ou log.
- Legenda da matriz: **T** = tem (confirmado) · **P** = parcial · **N** = não tem (a fonte explicita) · **NC** = não confirmado nas fontes consultadas.
- **NC não significa ausência.** Significa apenas que a fonte consultada não trouxe evidência suficiente nesta data.
- Nenhuma conclusão foi tirada além do que a página afirma textualmente ou do que foi visto na navegação passiva.

---

## 2. Fontes consultadas

### 2.1 Fontes oficiais e documentação

| Concorrente | URL | Afirmação aproveitada | Acesso |
|---|---|---|---|
| ServiceTitan | `https://www.servicetitan.com/features/field-mobile-app` | App de campo integrado para celular/tablet | 21/11/2026 |
| ServiceTitan | `https://help.servicetitan.com/docs/servicetitan-field-mobile-app-offline-capabilities` | Offline para status, assinaturas, tempo e fotos; **não** oferece offline de inventário, financiamento, contratos ou orçamentos | 21/11/2026 |
| Jobber | `https://www.getjobber.com/features/` | Funcionalidades gerais | 21/11/2026 |
| Jobber | `https://www.getjobber.com/pricing/` | Planos Core/Connect/Grow/Plus; US$ 29 a US$ 529/mo (anual); usuário adicional US$ 29/mo; cartão 2,9%+30¢; Tap to Pay 2,7%+30¢; ACH 1%; payouts instantâneos +1%; checklists, fotos, agendamento online, client hub, API aberta | 21/11/2026 |
| Housecall Pro | `https://www.housecallpro.com/features/`, `/pricing/` | Online booking, despacho, orçamentos/faturas, pagamentos, price book, reviews | 21/11/2026 |
| Workiz | `https://www.workiz.com/features/`, `/pricing-plans/` | Checklists, service plans, propostas, rastreamento de equipamentos, Open API | 21/11/2026 |
| ServiceM8 | `https://www.servicem8.com/tour`, `/pricing` | Free US$ 0, 1 usuário, 30 jobs/mês; document scanning em PDF | 21/11/2026 |
| FieldPulse | `https://www.fieldpulse.com/features/`, `/articles/best-hvac-dispatch-software/` | Custom Forms, Pricebook, field-only seats, add-ons | 21/11/2026 |
| Zuper | `https://www.zuper.co/mobile-app-and-offline-mode` | Offline com fila e sincronização, checklists por voz, fotos antes/depois, pagamento no local, SOS com GPS | 21/11/2026 |
| Salesforce | `https://www.salesforce.com/service/field-service-management/pricing/` | Licenças Dispatcher/Technician/Service Cloud; técnicos precisam de Dispatcher para agendar | 21/11/2026 |
| Microsoft | `https://www.microsoft.com/en-us/dynamics-365/products/field-service` | Página do produto | 21/11/2026 |
| Skedulo | `https://www.skedulo.com/platform/`, `/pricing/` | Pulse Platform, public pages, AI Forge/Tempo/Mastermind; sem preço público | 21/11/2026 |
| simPRO | `https://www.simpro.com.au/`, `https://developer.simprogroup.com/` | Field Mobile App, Multi-Company Management, Developer Center | 21/11/2026 |
| FieldEdge | `https://www.fieldedge.com/` | Payments, Proposals, Scheduling, Marketing | 21/11/2026 |
| Auvo | `https://www.auvo.com/auvo-service/` | Do chamado à cobrança; app do técnico com checklist/foto/assinatura off-line; contrato recorrente, PMOC, OS digital por link, API e webhook | 21/11/2026 |
| Field Control | `https://fieldcontrol.com.br/`, `https://developers.fieldcontrol.com.br/` | Apps móveis e API pública | 21/11/2026 |
| Produttivo | `https://www.produttivo.com.br/` | Manutenção, OS, PMOC | 21/11/2026 |
| Online OS | `https://onlineos.com.br/` | OS, orçamento, checklist, agenda, estoque, financeiro, PDV, permissões | 21/11/2026 |
| Nomus | `https://www.nomus.com.br/` | ERP industrial com NFS-e, PCP, custos, painéis | 21/11/2026 |

### 2.2 Painel externo observado (passivo)

| URL | O que foi visto | Acesso |
|---|---|---|
| `https://goldenrastreamento.com.br/admin/dashboard` | Indicadores comerciais, funil de leads, pedidos da loja, chamados, conversas, estoque abaixo do mínimo, estado do site/loja/WAF, export CSV | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/notificacoes` | Busca, filtros por situação/tipo, marcar todas como lidas, preferências, contadores, tipos como "tarefa agendada falhou" e "tentativa de acesso bloqueada" | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/seguranca` | TOTP de 6 dígitos, 8 códigos de recuperação, 2FA obrigatório por empresa, sessões com dispositivo/IP e encerramento remoto, limite de 3 tentativas/15 min, bloqueio de 20 min, sessões simultâneas, inatividade, auditoria de login | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/permissoes` | Perfis Admin/Vendedor/Contador/Marketing/Suporte/Visualizador e SUPERADMIN protegido; matriz ver/criar-alterar/deletar com atalhos | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/auditoria` | Trilha filtrável por ação e usuário, descrição e IP | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/contratos` | Termo de adesão/aditivo/renovação, mensalidade, situação e PDF assinado no portal | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/templates-email` | 9 templates transacionais, layout global responsivo, variáveis e preview ao vivo | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/faturas-modelo` | PDF A4 com blocos reordenáveis, PIX QR Code e linha digitável | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/crons` | Crontab única, heartbeat do agendador, última execução, falhas e "rodar agora" | 21/11/2026 |
| `https://goldenrastreamento.com.br/admin/manutencao` | Modo 503 com prévia, escopo site/portal, contador e bypass por IP | 21/11/2026 |

### 2.3 Ressalvas de fonte

- **MobiPrime:** nenhuma fonte acessível; classificação integralmente não confirmada.
- **Tecnospeed ↔ Online OS:** vínculo **não confirmado**; a URL oficial retornou 404. Não afirmar relação.
- **Microsoft D365 / Salesforce Field Service:** páginas de documentação falharam (404/erro); conclusões sobre offline ficaram NC.
- **Housecall Pro / ServiceTitan:** valores de terceiros são **apoio não oficial** e não devem ser tratados como preço confirmado.
- **Praxio e Nomus:** perfil de ERP; classificação como FSM puro não confirmada.

---

## 3. Matriz de cobertura

### 3.1 Concorrentes globais

| Eixo | ST | Jobber | HCP | Workiz | S8 | FieldPulse | Zuper | SF | D365 | Skedulo | simPRO | FieldEdge |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| App móvel de campo | T | T | NC | NC | T | P | T | NC | NC | NC | T | NC |
| Modo offline | P | NC | NC | NC | NC | NC | T | NC | NC | NC | NC | NC |
| Agenda e despacho | NC | T | T | T | T | P | T | P | NC | P | NC | T |
| OS / chamados | P | T | P | T | T | P | T | NC | NC | P | NC | NC |
| Checklists / inspeções | NC | T | NC | T | T | P | T | NC | NC | NC | NC | NC |
| Assinatura / fotos no job | P | T | NC | NC | T | NC | T | NC | NC | NC | NC | NC |
| Orçamentos / propostas | P | T | T | T | T | NC | T | NC | NC | NC | T | T |
| Faturamento e pagamentos | NC | T | T | T | T | NC | T | NC | NC | NC | NC | T |
| Estoque / ativos | P | T | P | T | NC | P | NC | NC | NC | NC | NC | NC |
| Relatórios | NC | T | NC | T | NC | NC | NC | NC | NC | NC | NC | NC |
| API aberta | NC | T | NC | T | NC | NC | NC | NC | NC | T | T | NC |
| Multiempresa real | NC | P | NC | NC | NC | NC | NC | NC | NC | NC | T | NC |
| Auditoria detalhada | NC | NC | NC | NC | NC | NC | NC | NC | NC | NC | NC | NC |
| Preço público | NC | T | P | NC | T | P | NC | P | NC | NC | NC | NC |

### 3.2 Concorrentes brasileiros

| Eixo | Auvo | Field Control | Produttivo | Online OS | Nomus | Golden |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| App do técnico | T | T | NC | NC | NC | NC |
| Offline | T | NC | NC | NC | NC | NC |
| Agenda e OS | T | P | T | T | NC | P |
| Checklist / evidência / assinatura | T | NC | NC | T | NC | NC |
| Contrato recorrente / PMOC | T | NC | T | NC | NC | P |
| API / webhook | T | T | NC | NC | NC | NC |
| Multiempresa | T | NC | NC | NC | NC | NC |
| Notificações com preferências | NC | NC | NC | NC | NC | T |
| 2FA + sessões + IP | NC | NC | NC | NC | NC | T |
| Auditoria com IP | NC | NC | NC | NC | NC | T |
| Saúde do agendador | NC | NC | NC | NC | NC | T |
| Modo manutenção | NC | NC | NC | NC | NC | T |

---

## 4. O NEXUS-FIELD hoje

### 4.1 Já entrega, e não deve copiar de ninguém

| Eixo | Situação no NEXUS |
|---|---|
| Multiempresa com isolamento real | `TenantContext` + `CompanyScope` + `BelongsToCompany`, aplicados antes do model binding. Equivale ao Multi-Company da simPRO; na maioria dos concorrentes ficou NC. |
| Permissões granulares | `PermissionCatalog` com 17 módulos, ações por módulo e cinco papéis; matriz por módulo já existe no `RoleController`. |
| Auditoria | Antes/depois, filtros por ação e usuário, IP, user agent e export CSV. Nenhum concorrente da amostra confirmou esse nível pelas fontes consultadas. |
| Indicadores sem número inventado | Cada KPI do painel sai da mesma consulta da micro-visualização e da listagem. |
| Rastreabilidade de estado | Ordens e chamados com histórico, autor e data; SLA por prioridade em `PRAZO_HORAS`. |
| Check-in em campo | Registro geolocalizado de chegada e saída na visita. |
| Temas claro/escuro | Identidade "Premium Gourmet + Technology" persistida no navegador e no servidor, sem mudança estética não solicitada. |
| Base de testes | 345 testes / 3.330 asserções verdes; auditorias de dependências limpas. |

### 4.2 Lacunas reais frente ao mercado

| Lacuna | Evidência | Prioridade |
|---|---|:--:|
| App de campo offline com fila e sincronização | Zuper, Auvo, ServiceTitan (parcial) | P0 |
| Checklists dinâmicos por tipo de serviço com foto e assinatura | Auvo, Zuper, Jobber, Workiz, ServiceM8, Online OS | P0 |
| Segurança de conta: TOTP, recuperação, sessões ativas, IP, encerramento remoto | Golden (observado); ausente no NEXUS | P0 |
| Preferências de notificação por tipo/canal | Golden (observado); NEXUS tem filtros, mas não preferências | P1 |
| Saúde do agendador com heartbeat e falhas | Golden (observado); NEXUS tem `withoutOverlapping` e nome, mas sem painel de saúde | P1 |
| Templates de e-mail com variáveis e preview | Golden (observado); NEXUS usa `Notifier` interno, sem editor | P1 |
| Contratos de manutenção recorrente com PDF no portal | Auvo, Produttivo, Golden | P1 |
| API aberta com escopos e webhooks | Workiz, simPRO, Field Control, Auvo | P1 |
| Relatório de serviço com assinatura do cliente | Auvo, Zuper, ServiceM8 | P1 |
| Modo manutenção com bypass por IP | Golden (observado) | P2 |

---

## 5. O que NÃO adotar do painel observado

Estes itens existem no Golden, mas são de e-commerce, CRM, Google Ads ou rastreamento de veículos. **Copiá-los diluiria a identidade do NEXUS-FIELD**:

- Funil de leads, campanhas, Google Ads, copiloto de Ads, atribuição e páginas do site.
- Loja virtual, pedidos de loja, carrinho, frete e rastreio de entregas.
- Monitoramento de site/loja/WAF e "escudo militar" (o NEXUS terá saúde própria, observabilidade e modo manutenção, não status page de e-commerce).
- Contratos de rastreamento veicular, mensalidade de rastreador e "terra de rastreio".
- Perfis Vendedor/Contador/Marketing como papéis centrais: o NEXUS usa Administrador, Supervisor, Funcionário, Técnico e Cliente, coerentes com operação de campo.
- Fatura com linha digitável de rastreamento: o financeiro do NEXUS é de OS, peças, horas e deslocamento.

**Critério de decisão:** só entra o que melhora a cadeia cliente → OS → chamado → agenda → execução → financeiro → relatório do NEXUS, ou a segurança/observabilidade que a sustenta.

---

## 6. Estratégia de diferenciação

O objetivo não é "ter as mesmas telas". É **ser melhor no núcleo FSM e diferente na origem**:

1. **FSM nativo, não CRM adaptado.** O Golden nasceu de tráfego/loja/rastreio; o NEXUS nasce da ordem de serviço. Toda melhoria deve fortalecer a OS, nunca criar um funil comercial.
2. **Multiempresa por arquitetura.** Enquanto o painel observado organiza pessoas por perfil dentro de uma operação, o NEXUS isola empresas de verdade. Essa é a vantagem estrutural e deve aparecer em cada feature nova (tenant primeiro, posse depois, permissão por último).
3. **Prova em vez de promessa.** Onde o Golden mostra indicadores comerciais, o NEXUS mostra o que foi executado: check-in geolocalizado, histórico de estado, auditoria com antes/depois e, no roadmap, assinatura do cliente no relatório de serviço.
4. **Segurança como produto, não como painel.** Adotar 2FA/sessões do Golden, mas com o modelo de permissões e isolamento do NEXUS: política por empresa, sessão ligada ao tenant e auditoria de cada evento sensível.
5. **Qualidade de dado.** KPI do NEXUS não é número solto: é a mesma consulta da listagem. Diferenciação silenciosa, mas verificável.
6. **LGPD desde a raiz.** Exportação/anonimização do titular, retenção configurável e trilha imutável entram antes de qualquer módulo comercial novo.
7. **Offline-first no técnico.** Onde o Golden é web, o NEXUS pode chegar com PWA e fila idempotente — sem depender de app nativo para começar.

---

## 7. Melhorias decididas para implementação

Aplicando o critério acima, entram agora as fatias abaixo. Cada uma com teste e registro em `CHANGELOG.md`.

### Fase M1 — Segurança de conta (adotar do Golden, adaptado ao tenant)

- TOTP de 6 dígitos com 8 códigos de recuperação de uso único.
- Política de 2FA por empresa, sem liberar papel inferior a administrador.
- Lista de sessões ativas com dispositivo, IP e última atividade; encerramento remoto.
- Auditoria de login, 2FA falhou, recuperação usada e sessão encerrada.

### Fase M2 — Notificações com preferências (adotar, adaptado ao `Notifier`)

- Preferência por tipo de aviso no nível da conta, com padrão "todos ligados".
- `Notifier` respeita a preferência antes de gravar.
- Filtros e contadores existentes permanecem; a preferência é nova, não substitui a bandeja.

### Fase M3 — Observabilidade operacional (adotar a ideia, não o visual)

- Heartbeat do agendador com última execução, resultado e falhas.
- Comando de verificação com exit code para monitoramento externo.
- Modo manutenção 503 com bypass por IP documentado, sem tela nova de marketing.

### Fase M4 — Documentos do serviço (adotar com identidade própria)

- Templates de e-mail com variáveis e preview, usando o layout da casa.
- Relatório de serviço em PDF a partir da OS, com dados reais da visita.
- Contratos de manutenção recorrente com PDF no portal do cliente, sem mensalidade de rastreamento.

Fatias grandes (app offline, assinatura digital, API/webhooks) ficam no roadmap das Fases 12–13 do inventário, com feature flag desligada até virarem produto completo.

---

## 8. Risco de "ficar idêntico"

| Risco | Como evitar |
|---|---|
| Copiar layout do painel externo | Manter tokens, tipografia, tema claro/escuro e components do NEXUS. Nenhuma captura do Golden serve de base visual. |
| Copiar vocabulário de e-commerce | Usar "ordem", "chamado", "visita", "técnico", "peça", "SLA", nunca "lead quente", "carrinho", "rastreio". |
| Copiar papéis de CRM | Manter os cinco papéis FSM do `PermissionCatalog`. |
| Virar "mais um painel de indicadores" | Cada indicador novo precisa rastrear até uma consulta da empresa e até um registro de auditoria. |
| Perder o isolamento ao adicionar features | Toda migration nova com `company_id` entra com `BelongsToCompany`; todo endpoint novo entra no teste de isolamento. |

---

## 9. Ressalvas finais

- A comparação com o NEXUS usa código e suíte reais, não apenas o `README`; os números do badge serão corrigidos na Fase 14.
- "Não confirmado" não foi tratado como "não existe".
- Nenhuma credencial do painel externo foi registrada. Nenhum scan ativo foi executado contra o domínio de terceiro.
- A implementação das fases M1–M4 continua sujeita ao gate de testes verdes e a um commit atômico por fatia.
