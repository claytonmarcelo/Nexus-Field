# Decisões do produto

Registro de mudanças de direção que afetam regra de negócio, com data e motivo.

## 21/11/2026 — Cadastro público de acesso e ambiente inicial

**Decisão anterior:** não havia `/register`; as contas vinham do seeder ou da criação manual pelo
administrador, e o README registrava que não existia cadastro aberto para a plataforma.

**Nova decisão:** `/register` é o cadastro de site comum, com nome, e-mail, senha e confirmação. A
conta é autenticada na hora e ganha um ambiente inicial ativo; a empresa definitiva é cadastrada
depois, em **Cadastros › Empresas**, dentro do painel.

**Motivo:** o usuário pediu acesso comum primeiro e empresa depois, sem expor o cadastro de empresas
na página inicial nem nas telas de acesso. `users.company_id` é obrigatório com `restrictOnDelete` e
`LoginController` recusa login sem empresa ativa, então uma conta sem ambiente não poderia entrar nem
ser gravada com integridade. O questionamento foi feito antes da implementação; a resposta foi
"somente dados cadastrais, nome, e-mail, senha, confirmar senha".

**Implementação:** `RegistradorDeAcesso` cria a conta, o ambiente trial, os papéis e a estrutura
mínima numa transação; `RegisterController` autentica, regenera a sessão, resolve o tenant e registra
auditoria; `RegistroRequest` valida os quatro campos; `CadastroAprovadoContaExistente` cobre a
aprovação sem link de reset. `GestorDeCadastro::iniciar` passou a receber o ator para permitir que a
própria pessoa cadastre a empresa da conta dela, e `aprovar` reaproveita essa conta em vez de criar
e-mail duplicado.

**Restrições mantidas:** senha nunca enviada por e-mail, conta raiz intocável, segredos fora do Git,
dados fictícios, MySQL compatível e suíte verde (405 testes / 3760 asserções).

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
