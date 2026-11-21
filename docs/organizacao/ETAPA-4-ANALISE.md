# Etapa 4 — Análise antes da implementação: filiais, setores, cargos, vínculos e organograma

Data: 21/11/2026. Branch: `feature/organizacao-empresas`. HEAD de partida: `2ca9802`.

Baseline verificado antes desta etapa: `374 testes / 3506 asserções`, verdes; `git diff --check`
limpo; varredura de BOM/CJK/invisíveis com `FILES=372 BAD=0`.

## Fontes consultadas nesta etapa

- Laravel 13.x — Authorization: https://laravel.com/docs/13.x/authorization
  (acesso 21/11/2026). Confirma que `FormRequest::authorize()` é a porta explícita
  de autorização da requisição e que Gates/Policies devem concentrar a decisão.
  Adotado: `authorize()` nos Form Requests e o `Gate::before` já existente, que
  liga `@can` ao catálogo. Não adotado: criar policies paralelas ao catálogo —
  o projeto já declarou que o catálogo é a fonte única e uma segunda árvore de
  regras divergiria na primeira sincronização de papéis.
- Laravel 13.x — Validation: https://laravel.com/docs/13.x/validation
  (acesso 21/11/2026). Confirma `prepareForValidation`, `Rule::unique()->where()`
  e regras closure para validação dependente do registro. Adotado: slug derivado
  do nome antes da validação, unicidade sempre escopada por `company_id` e
  validação de ciclo/profundidade do pai dentro do Form Request, para a recusa
  chegar como 422 e não como exceção de domínio virando 500.
- Wikipedia — Organizational chart: https://en.wikipedia.org/wiki/Organizational_chart
  (acesso 21/11/2026). Referência conceitual para hierarquia, unidade de
  comando e amplitude de controle. Adotado com adaptação: árvore de setores com
  `parent_id` + `path` materializado, limite de profundidade e um gestor por
  vínculo; não adotado o desenho de "organograma como imagem", porque a tela
  precisa derivar dos vínculos gravados e respeitar o alcance.
- Estudo já aprovado: `docs/mercado/ESTUDO-ORGANIZACAO-2026-11-21.md`, itens 2,
  8 e 10 (autorização no servidor, leitura de árvore sem recursão em PHP e
  índice composto começando por `company_id`).
- Laravel 13.x — Routing: https://laravel.com/docs/13.x/routing
  (acesso 21/11/2026). Confirma que grupos aninham `prefix`, `name` e
  `middleware`, que o middleware roda na ordem declarada e que rotas literais
  devem vir antes das parametrizadas para não serem capturadas por elas.
  Adotado: um único grupo `prefix('organizacao')->name('organization.')`
  dentro de `auth + company`, com `permission:` por grupo de escrita e rotas
  `nova`/`filiais`/`setores`/`cargos`/`vinculos` antes de `{parametro}`.
- W3C WAI — Tables Tutorial: https://www.w3.org/WAI/tutorials/tables/
  (acesso 21/11/2026). Exige `<th>` para cabeçalhos, `<td>` para dados,
  `scope="col"`/`scope="row"` quando a direção não é óbvia e `<caption>` para
  identificar a tabela (WCAG 1.3.1, nível A). Adotado em todas as listagens
  desta etapa; nenhuma tabela é usada para layout.
- Tentativas sem conteúdo utilizável nesta data: SHRM e Lucidchart devolveram
  404; Mindtools devolveu apenas casca JavaScript. **Não verificado** o texto
  dessas três fontes; nenhuma decisão foi baseada nelas.

## Inventário do que já existe

- Tabelas `branches`, `departments`, `department_service_categories`,
  `positions`, `employments` e `organization_scopes` já estão migrated.
- Modelos com `BelongsToCompany`, `Auditable` e `SoftDeletes` já existem;
  `Department::naArvore` e `Employment::encerrar` já foram provados na Etapa 3.
- `Alcance::idsUsuarios()` devolve `null` para empresa inteira e uma lista de
  ids quando há recorte de filial/setor/equipe/subordinados.
- `CompanyScope` + `ResolveCompany` garantem o tenant antes de
  `SubstituteBindings`; um id de outra empresa vira 404 sem mensagem que
  confirme a existência alheia.
- `Auditor::gravar`, `Auditor::diferenca`, `Notifier::para` e `Recusa` são as
  portas já usadas pelo resto do sistema.
- Não há `app/Http/Requests` nem Policies de arquivo: a validação hoje é
  inline e a autorização é `permission:` na rota + `Gate::before` para `@can`.
  A Etapa 4 introduz Form Requests **somente para o módulo novo**, sem
  refatorar as telas legadas dentro deste commit.

## Decisões técnicas desta etapa

1. **Permissões**: novo módulo `organization` com `view`, `create`, `update` e
   `delete` no `PermissionCatalog`. Administrador herda `*`; supervisor recebe
   apenas `organization.view` (leitura do organograma e das listagens);
   funcionário, técnico e cliente não recebem permissão nenhuma. Nenhum papel
   novo é criado — a matriz papel×rota fica registrada abaixo e testada.
2. **Autorização em duas camadas**: middleware `permission:` na rota recusa
   antes do controller; `FormRequest::authorize()` repete a checagem por
   classe de escrita. Esconder botão no Blade continua sendo só higiene.
3. **Controllers enxutos**: index/create/edit/show apenas montam dados; todo
   write passa por `App\Services\Organizacao\GestorDeEstrutura`, que usa
   `DB::transaction`, lança `Recusa` para regra de negócio e chama `Auditor` e
   `Notifier`. Nenhuma regra de domínio fica no controller.
4. **Árvore de setores**: `parent_id` + `path`. O Form Request barra ciclo,
   pai de outra empresa e profundidade acima de `PROFUNDIDADE_MAXIMA`; o modelo
   mantém a guarda de último recurso; o serviço recalcula `path` e `depth` de
   toda a subárvore quando um setor é movido. A leitura do organograma usa o
   prefixo de `path`, sem recursão em PHP e sem CTE: volume é pequeno e MySQL 8
   continua sendo o único alvo.
5. **Vínculos são histórico**: mudança de pessoa, filial, setor, cargo, gestor,
   tipo ou código encerra a linha atual com `encerrado` e abre outra com
   `ativo`. `ended_at` nunca é reescrito. O botão de desligamento encerra com
   `desligado`. Como `employee_code` é único por empresa, a nova linha recebe
   sufixo sequencial quando o código original já está ocupado pela linha
   encerrada. Não existe exclusão física de vínculo.
6. **Exclusão de cadastros**: filial, setor e cargo só saem quando não há
   vínculos ou filhos; a recusa vem como flash `erro`, no mesmo padrão das
   telas de cliente e equipe. `SoftDeletes` preserva o que já foi escrito.
7. **Isolamento**: nenhum Form Request aceita `company_id` do formulário; o
   `BelongsToCompany` sobrescreve com `TenantContext`. Toda consulta de
   detalhe usa o binding já filtrado pelo `CompanyScope`. Um id de outra
   empresa retorna 404 na tela e 422 na validação de relacionamento.
8. **Organograma e alcance**: a estrutura (filial/setor/cargo) é lida por
   empresa; as pessoas exibidas passam por `Alcance::idsUsuarios()`. Com escopo
   gravado, gestor vê só a própria subárvore; sem estrutura nova, administrador
   e supervisor mantêm o comportamento legado da empresa inteira.
9. **Auditoria e notificação**: cada criação, atualização, movimento e
   desligamento grava `Auditor::gravar` com a empresa correta. A criação ou
   movimentação de vínculo avisa a pessoa envolvida pelo `Notifier`, com o tipo
   `organizacao.vinculo` incluído no vocabulário do sino.
10. **Nomes e identidade**: rotas em `/organizacao`, rótulos em português,
    componentes `x-ui.*` existentes e nenhuma rotulação em inglês na interface.

## Matriz papel × rota (resumo)

| Rota | Administrador | Supervisor | Funcionário / técnico / cliente |
| --- | --- | --- | --- |
| `GET /organizacao` e listagens | sim | sim | 403 |
| `GET` de ficha/edição | sim | sim | 403 |
| `POST` de criação | sim | 403 | 403 |
| `PUT/PATCH` de atualização | sim | 403 | 403 |
| `DELETE` de cadastro/vínculo | sim | 403 | 403 |

O supervisor lê a estrutura, mas não a altera; delegação de gestão de filial
ou setor pertence à Etapa 5, que traz convites e política de segurança.

## Verificação planejada antes do commit

- Testes feature para CRUD dos quatro cadastros, 403 por papel, 404 entre
  tenants, ciclo e profundidade de setor, histórico de vínculo, organograma
  recortado, auditoria e notificação.
- Suíte completa verde, `git diff --check`, varredura de BOM/CJK/invisíveis,
  `CHANGELOG.md` atualizado e commit atômico da Etapa 4, sem push.
