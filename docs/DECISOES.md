# Decisões do produto

Registro de mudanças de direção que afetam regra de negócio, com data e motivo.

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
