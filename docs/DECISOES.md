# Decisões do produto

Registro de mudanças de direção que afetam regra de negócio, com data e motivo.

## 22/11/2026 — Casca pública no padrão da referência

**Decisão anterior:** a chave de tema havia ido para o canto superior direito do cabeçalho, ao lado
de `Criar conta` e `Entrar`; as telas de acesso vestiam o mesmo cabeçalho e o mesmo rodapé da boas-vindas,
com cópia longa e informal.

**Nova decisão:** a chave volta ao centro do cabeçalho público, entre a marca e a única ação do topo,
`Entrar`. Login, cadastro, recuperação e redefinição trocam o cabeçalho e o rodapé grandes por uma barra
enxuta — voltar à esquerda, tema à direita — e um cartão único com título curto e subtítulo objetivo.
O cadastro sai do cabeçalho e continua como link no rodapé da tela de entrada e no fecho da boas-vindas.

**Motivo:** o usuário pediu explicitamente o tema centralizado, a remoção de `Criar conta` do topo,
textos/cards profissionais e a conformidade com `https://nexus-business-manager.vercel.app/`. A referência
foi aberta no navegador: no landing só existe `Acessar`; `/login` traz `Seja bem‑vindo`, `Insira os seus
dados para continuar`, `ENTRAR` e `Não tem uma conta? Criar conta`; `/cadastro` traz `Criar conta` e
`Inicie a sua jornada conosco`; `/recuperar-senha` traz `Recuperar senha`, `Informe o e‑mail da sua conta`
e `ENVIAR`. A pesquisa de mercado em landing B2B aponta headline curta, sublinha objetiva e cartões
escaneáveis — foi o que substituiu os textos longos. As perguntas sobre o destino da chave e sobre o que
sobraria no cabeçalho foram respondidas antes da implementação: centro do cabeçalho público e somente
`Entrar`.

**Causa raiz da marca e do rodapé:** `<x-ui.brand-mark />` gravava `style="width: 4rem; height: 4rem"`
no `<img>`, e esse estilo inline vencia o CSS de 1,875rem do sidebar/cabeçalho e de 1,375rem do rodapé.
No sidebar a logo de 51px era cortada pela faixa de 35px; no rodapé público o medalhão inchado empurrava
a linha para 74px contra os 38px do rodapé autenticado. Agora cada contexto passa o próprio `lado` (30
no sidebar e no cabeçalho, 22 no rodapé) e o forro do rodapé foi calibrado no navegador para os mesmos
38px. A marca da empresa no sidebar já usava 1,875rem e segue intacta.

**Implementação:** `public-header.blade.php` virou grade de três colunas com a chave isolada no meio;
`auth-topbar.blade.php` é o novo componente das telas abertas, usado por `auth-shell.blade.php`; os quatro
Blade de acesso receberam a cópia da referência; `welcome.blade.php` foi reescrito com título, sublinha e
cards curtos, mantendo os seis passos do ciclo e o CTA final. A animação da marca que ficava acima do título
de entrada saiu junto com a casca antiga, e o CSS morto foi removido. Nenhuma cor, token, permissão,
integração ou consulta foi alterada; o formulário de cadastro continua com apenas nome, e‑mail, senha e
confirmação — os checkboxes de Termos/Privacidade da referência não foram adicionados. Suíte: 407 testes /
3801 asserções.

**Restrições mantidas:** credencial da conta raiz fora de código, teste, doc, log e commit; prosa hifenizada
com U+2011 (`e‑mail`, `check‑in`, `bem‑vindo`); paleta e logotipo preservados; nenhum push.

## 22/11/2026 — Chave de tema no canto superior direito

**Decisão anterior:** a chave de tema morava na coluna central do cabeçalho público
(`.nf-public-header-tema`), com marca à esquerda e ações à direita.

**Nova decisão:** a chave passa a ser o primeiro item de `.nf-public-header-actions`, antes de
`Criar conta` e `Entrar`, no canto superior direito — o mesmo grupo de ações usado no painel.

**Motivo:** o usuário pediu o controle global no topo direito, fora do meio do cabeçalho. A
pesquisa de mercado (Bootstrap 5, Chrome Modern Web Guidance para dark-mode toggle, MDN sobre
`aria-pressed` e padrões de colocação em header) confirma o canto superior direito como posição
convencional e o grupo de ações da conta como vizinho natural. O componente já tem `aria-pressed`
e `aria-label`, então nenhum contrato de acessibilidade mudou.

**Implementação:** `public-header.blade.php` move `<x-ui.theme-toggle />` para dentro de
`.nf-public-header-actions`; `public.css` troca o grid de três colunas por flex com
`space-between` e remove a classe central órfã. Comentários em `navbar.blade.php` e
`components.css` acompanham a nova regra. `CoerenciaVisualTest` passa a exigir a chave antes das
portas de acesso, a ausência da coluna central e o flex com `space-between`. Nenhuma cor, token,
permissão ou consulta foi alterada; suíte: 405 testes / 3769 asserções.

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
dados fictícios, MySQL compatível e suíte verde (405 testes / 3769 asserções).

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
