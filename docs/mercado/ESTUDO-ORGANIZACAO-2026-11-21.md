# Estudo de mercado e práticas — módulo de empresas e organização

Data de acesso às fontes: 21/11/2026. Este estudo antecede qualquer alteração de
código do módulo, conforme a regra de pesquisar melhorias coerentes e válidas
antes de implementar.

## Recomendações aceitas

1. **Escopos globais com cuidado com `orWhere`**: qualquer filtro com `OR` deve
   ser agrupado em closure para não anular `CompanyScope`. Fonte:
   https://laravel.com/docs/13.x/eloquent
2. **Autorização em Policies/Gates e validação em FormRequest**: a decisão fica
   no servidor e o RBAC existente continua sendo a fonte única. Fonte:
   https://laravel.com/docs/13.x/authorization
3. **Signed URLs não são uso único por padrão**: convites e confirmações precisam
   de registro explícito de consumo (`used_at`) e expiração. Fonte:
   https://laravel.com/docs/13.x/urls
4. **Password broker + `Password::uncompromised()`** na troca de senha, sem
   reimplementar o broker. Fonte: https://laravel.com/docs/13.x/authentication
5. **Throttle por IP e por identificador + honeypot** nos formulários públicos.
   Fonte: https://laravel.com/docs/13.x/rate-limiting
6. **Cache de configuração e rotas em produção** com `APP_DEBUG=false`. Fonte:
   https://laravel.com/docs/13.x/releases
7. **Casts `encrypted` para dado sensível**, com coluna de hash separada quando
   for necessário localizar o registro. Fonte:
   https://laravel.com/docs/13.x/eloquent-mutators
8. **Recursive CTE no MySQL 8 para leitura da árvore**; closure table apenas se
   a leitura de ancestrais/descendentes se tornar dominante. Fonte:
   https://dev.mysql.com/blog-archive/mysql-8-0-labs-recursive-common-table-expressions-in-mysql-ctes-part-three-hierarchies
9. **UNIQUE no CNPJ + transação** para criar empresa, estrutura e administrador
   como uma unidade, evitando corrida de dois pedidos simultâneos. Fonte:
   https://dev.mysql.com/doc/refman/8.4/en/insert-on-duplicate.html
10. **Índice composto começando por `company_id`** em todas as tabelas
    organizacionais. Fonte: https://dev.mysql.com/doc/refman/8.4/en/indexes.html
11. **Turnstile/hCaptcha com verificação no servidor** e token de uso único.
    Fontes: https://developers.cloudflare.com/turnstile/get-started/server-side-validation
    e https://docs.hcaptcha.com/
12. **Integrações CNPJ/CEP com host fixo, timeout curto e sem redirect para IP
    interno**; ViaCEP e BrasilAPI como fontes, com fallback manual. Fontes:
    https://brasilapi.com.br/docs , https://viacep.com.br/ e
    https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet
13. **Retenção mínima, anonimização e inventário de dados** alcançando também
    logs e exports. Fonte: https://laravel.com/docs/13.x/eloquent-mutators
14. **Impersonação auditada**, com ator, empresa de origem/destino, motivo e
    expiração. Fonte:
    https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html
15. **Upload privado fora da raiz pública**, MIME real, nome opaco e download por
    rota autorizada. Fonte:
    https://raw.githubusercontent.com/OWASP/ASVS/v5.0.0/5.0/en/0x14-V5-File-Handling.md
16. **Sanitização na saída e prefixo de célula no CSV** contra XSS estocado e
    CSV Injection. Fonte: https://owasp.org/www-community/attacks/CSV_Injection
17. **Mensagens genéricas de autenticação e testes de IDOR entre empresas**.
    Fontes:
    https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
    e
    https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html
18. **CSP com nonce** no AdminLTE 4, desde que os scripts do tema recebam nonce
    por requisição. Fonte: https://github.com/spatie/laravel-csp

## Rejeitado neste projeto

| Ideia | Motivo |
| --- | --- |
| Migrar para PostgreSQL | Contrato do projeto é MySQL 8 compatível; CTE, `UNIQUE` e transações atendem ao caso. |
| Versionar `.env`, chaves ou segredos de teste | Regra absoluta do projeto. |
| CAPTCHA somente no navegador | A verificação precisa acontecer no servidor. |
| Diferenciar "conta inexistente" de "senha errada" | Enumeração de contas. |
| Upload em `public/` com nome original | Exposição direta e risco de arquivo executável. |
| `Export` CSV sem neutralizar fórmulas | CSV Injection. |
| Confiar só no `CompanyScope` sem teste entre empresas | IDOR; `orWhere` mal agrupado anula o escopo. |
| Impersonação sem auditoria | Perda de rastreabilidade. |
| Assumir que signed URL é uso único | O Laravel assina e expira, mas não registra consumo. |
| Usar cast `encrypted` em coluna pesquisável | Valor cifrado não suporta `where`/`like` direto. |
| Adotar `LaravelLegends/pt-br-validator` sem checar compatibilidade | Não confirmado para Laravel 13/PHP 8.4 na pesquisa; manter regra própria até validação. |
| CRM, e-commerce e rastreamento veicular | Fora do escopo FSM conforme análise competitiva já aprovada. |

## Conclusão

Entram no desenho: transação atômica de aprovação, `used_at` em links
assinados, escopo `company_id` à frente dos índices, CTE recursiva para árvore,
verificação server-side de anti-bot, upload privado e sanitização de saídas.
Não entram: troca de SGBD, segredos versionados e validação de CPF/CNPJ por
dependência não confirmada.

## Adendo da Etapa 4 — estrutura interna e organograma

Consulta adicional em 21/11/2026, antes de qualquer código desta etapa:

| Fonte | Resultado | Uso |
| --- | --- | --- |
| https://laravel.com/docs/13.x/authorization | `FormRequest::authorize()` como porta explícita; Gates/Policies centralizam a decisão | Autorização em duas camadas: `permission:` na rota e `authorize()` no Form Request. Não criar policies paralelas ao catálogo. |
| https://laravel.com/docs/13.x/validation | `prepareForValidation`, `Rule::unique()->where()` e regras closure | Slug derivado do nome; unicidade por `company_id`; ciclo e profundidade barrados como 422. |
| https://en.wikipedia.org/wiki/Organizational_chart | Hierarquia, unidade de comando e amplitude de controle | Árvore com `parent_id`, `path` materializado, um gestor por vínculo e teto de profundidade. |
| SHRM, Lucidchart, Mindtools (mesma data) | 404 ou casca JavaScript; **não verificado** | Nenhuma decisão baseada nestas fontes. |

Rejeitado neste adendo: organograma desenhado à mão ou importado como imagem
(o dado precisa derivar dos vínculos), policy paralela ao catálogo e exclusão
física de vínculo (o histórico é a finalidade da tabela). O detalhamento das
decisões está em `docs/organizacao/ETAPA-4-ANALISE.md`.
