# Decisões do produto

Registro de mudanças de direção que afetam regra de negócio, com data e motivo.

## 21/11/2026 — Cadastro de empresas sai da área pública

**Decisão anterior:** o fluxo de quatro etapas vivia em `/cadastro`, no mesmo grupo de rotas de login,
cadastro e recuperação de senha.

**Nova decisão:** o cadastro de empresas passa a ser uma ação do painel autenticado, no item exato
**Cadastros › Empresas** do menu lateral (`/cadastros/empresas`), protegida por `organization.create`.

**Motivo:** a empresa é uma entidade da organização, não um passo do acesso ao site. Manter o formulário
junto de login/recuperação confundia "criar conta" com "cadastrar empresa" e expunha um fluxo que exige
documentos e aprovação. O cadastro de acesso será tratado separadamente, no padrão de site comum.

**Implementação:** controller, requests e views mudaram para o domínio `Organization`; a casca passou a
ser o layout autenticado. O link de confirmação por e-mail continua sendo um endpoint com token, fora
dos grupos `guest`/`auth`, para não depender da sessão que iniciou o pedido.

**Restrições mantidas:** nenhuma empresa ganha acesso antes da validação, nenhuma senha é enviada por
e-mail, MySQL compatível, segredos fora do Git e identidade visual preservada.

## 21/11/2026 — Auto-cadastro de empresas passa a existir

**Decisão anterior:** não havia cadastro por conta própria. As contas nasciam do
seeder e o README registrava que não existia `/register`.

**Nova decisão:** o NEXUS-FIELD passa a oferecer cadastro público de empresas em
etapas, com confirmação de e-mail, validação documental pela plataforma e
ativação do administrador inicial após aprovação.

**Motivo:** a plataforma precisa onboarding global sem depender de criação manual
pela operação, mantendo a regra de que nenhuma empresa entra com acesso antes da
validação. A criação de usuários internos continua sob responsabilidade do
administrador da empresa; o auto-cadastro é de empresa, não de qualquer conta
avulsa.

**Implementação:** Etapa 6 do plano em `docs/organizacao/ETAPA-1-DESENHO.md`, entregue em
`/cadastro`: quatro etapas, confirmação de e-mail, documentos privados e aprovação pela plataforma.
A seção "Contas e acesso" do README foi atualizada junto com a entrega.

**Restrições mantidas:** MySQL compatível, nenhum segredo versionado, dados de
exemplo fictícios, isolamento por `company_id`, identidade visual preservada e
suíte verde a cada etapa.
