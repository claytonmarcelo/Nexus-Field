# Decisões do produto

Registro de mudanças de direção que afetam regra de negócio, com data e motivo.

## 22/11/2026 — Ícone de Visitas de campo alinhado ao menu

**Pedido do usuário:** "O \"Visitas em campo\" no menu sidebar esta sem icone implementar icone e alinhar com as outras funções do menu."

**Decisão:** o item passou de `fa-location-crossing` — glifo ausente no FontAwesome 6.7.2 deste projeto — para `fa-route`. A troca usa um ícone já presente na biblioteca instalada e conversa com o traçado do mapa GPS e com a natureza de percurso das visitas. `Mapa GPS` usa `fa-map-location-dot`, sem conflito com a rota.

**Garantia:** `AuthenticatedLayoutTest` agora abre o CSS do FontAwesome instalado e exige que todas as classes `fa-*` declaradas em `Navigation::sections()` existam de fato, além de fixar `fa-route` para `Visitas de campo`. A validação cobre o menu inteiro, não só o item trocado, e evita que a casca exiba quadrado vazio ou glifo de Pac-Man. Suíte: 419 testes / 3950 asserções.

## 22/11/2026 — Mapa GPS com rotas lidas do campo

**Pedido do usuário:** "A função GPS deve ter o mapa mostrando os funcionarios de campo trabalhando em suas rotas."

**Nova decisão:** a operação ganha `Mapa GPS` (`/gps`), com Leaflet sobre OpenStreetMap carregado só nessa tela. Cada técnico ativo aparece com a posição registrada na própria ficha (`technicians.latitude`, `longitude` e `last_location_at`) e com a rota desenhada pela sequência real de chegadas e saídas (`service_order_checkins`). O servidor monta os pontos e o navegador apenas desenha: não há interpolação, ponto fictício nem traçado inventado entre duas visitas.

**Consentimento e isolamento:** o mapa lê o banco; a escrita de posição só acontece depois que o navegador obtém permissão de geolocalização. O endpoint `gps.localizacao` ignora qualquer `technician_id` do request e grava somente na ficha ligada à conta autenticada — um técnico não consegue alterar a posição do colega, e uma conta de escritório sem ficha recebe 403. As coordenadas passam por validação de intervalo e precisão, e a rota de escrita tem `throttle:30,1`.

**Alcance:** a leitura segue a mesma política das visitas de campo. Quem tem ficha de técnico vê a própria rota; a conta de cliente vê apenas técnicos que passaram pelas ordens da carteira dela; o escritório vê a equipe da empresa. Tudo passa pelo global scope de empresa e pela middleware `company`, então o mapa nunca cruza tenants. A atribuição do OSM aparece no rodapé do mapa e o projeto continua sem chave de mapa paga.

**Motivo:** havia coordenadas no check-in e na ficha do técnico, mas nenhuma leitura que mostrasse a trajetória. O mapa fecha esse ciclo sem criar tabela, sem exigir workflow novo e sem transformar presença em vigilância: a posição é voluntária, é do próprio técnico e pode ser interrompida no botão. O bundle do mapa sai do pacote comum — `app.js` importa `gps.js` sob demanda — para que login, painel e listagens não paguem Leaflet.

**Verificação:** `GpsTest` cobre a posição e a rota na empresa, o recorte do técnico, o recorte da conta de cliente, a recusa de escrita em nome de outro técnico, a validação de coordenada e as middleware da rota de escrita. `npm run build` gera o chunk separado do GPS. Suíte: 419 testes / 3950 asserções.

## 22/11/2026 — Limpeza global de cache restrita à conta raiz

**Decisão anterior:** não existia botão nem rota para limpar cache em execução; a limpeza só acontecia
por comando Artisan no deploy.

**Nova decisão:** o navbar autenticado ganha um botão `Limpar cache` montado com `x-ui.button`
(`ghost`, `sm`) dentro de um formulário `POST` com CSRF, confirmação do design system e rota com
`throttle:5,1`. A ação executa `optimize:clear` — cache de dados, configuração, eventos, rotas e views —
e grava auditoria com autor, IP e navegador. O botão só é montado para `is_root`, e a rota repete a
checagem com o middleware `root`.

**Motivo:** o usuário pediu um botão global de limpeza de cache. "Global" aqui é o alcance do efeito:
o arquivo compilado é compartilhado por todas as empresas da instalação. O `*` do administrador de uma
empresa vale dentro do tenant e não pode se estender a um artefato comum — o mesmo princípio que
impede o administrador recém-criado de virar raiz do projeto. Por isso a interface esconde o controle
e o servidor recusa a requisição mesmo que alguém monte o POST à mão.

**Garantia de integridade:** o usuário deixou claro que limpar cache não pode apagar nada do que foi
escrito ou enviado ao projeto. `optimize:clear` só remove artefatos derivados — cache de dados,
configuração, eventos, rotas e views compiladas; ele não executa `DELETE` em tabela de negócio nem
toca arquivos enviados. As preferências da empresa continuam sendo lidas direto de `company_settings`
em `Settings`, sem camada de cache. O modal e o aviso de retorno dizem isso com todas as letras, e
`CacheClearTest` cria empresa, cliente e ordem com texto escrito, dispara a limpeza e exige que os três
registros e o texto continuem iguais. Exclusão de informação é outro assunto: só acontece pelos
papéis de administração, com modal e texto próprios.

**Implementação:** `EnsureRoot` virou middleware com alias `root`; `CacheController::clear` chama
`optimize:clear` e registra a auditoria; `routes/web.php` abre a rota fora do grupo `company`, mas
dentro de `auth`, com CSRF implícito do grupo web e rate limit. O botão usa o componente de botão do
design system e só recebe CSS para recolher o rótulo na barra estreita. `CacheClearTest` prova que a
raiz limpa e audita, que o administrador com `*` recebe 403 sem apagar nada, que a rota não aceita GET
nem visita anônima e que o botão sai do `x-ui.button` com confirmação e token. Suíte: 413 testes /
3848 asserções.

**Restrições mantidas:** nenhuma credencial real da conta raiz em código, teste, documento, log ou
commit; o teste usa usuário fictício marcado como raiz apenas no banco de testes. Paleta, logotipo,
permissões de tenant, integrações e consultas intactos; nenhum push.

## 22/11/2026 — Cabeçalho e rodapé da referência, com sino na barra

**Decisão anterior:** o cabeçalho público usava grid de três colunas com a chave de tema no centro e
`Entrar` à direita; o rodapé mostrava marca, `Gestão de operações em campo` e assinatura. A decisão de
22/11 que centralizou a chave fica superada por esta.

**Nova decisão:** a marca continua à esquerda, mas tema e porta formam o grupo da direita, nessa ordem,
e a porta passa a se chamar `Acessar`. O rodapé passa a ter marca e `© {{ now()->format('Y') }}
NEXUS-FIELD` à esquerda e `Desenvolvido por Clayton Marcelo` à direita. O sino da barra autenticada
continua lendo apenas os avisos da conta logada e abrindo a central em `/notificacoes`.

**Motivo:** o usuário pediu cabeçalho e rodapé iguais aos de
`https://nexus-business-manager.vercel.app/`, com os botões na mesma posição, e um sino funcional.
A referência foi medida no navegador: nela o controle de tema vem antes de `Acessar`, no canto
direito, e o pé traz direito autoral à esquerda e crédito à direita. A implementação foi conferida no
navegador com usuário fictício e aviso de teste: contador `1`, `aria-label`
`Notificações: 1 pendentes de leitura`, dropdown visível, item com o título do aviso e link `Centro
de notificações` abrindo a central com o mesmo texto.

**Implementação:** `public-header.blade.php` move a chave para dentro de `.nf-public-header-actions`
antes do botão e troca o rótulo; `public-footer.blade.php` ganha `.nf-public-footer-left` com o direito
autoral e o crédito fixo; `public.css` troca o grid por flex e estende o rodapé com as duas colunas.
`CoerenciaVisualTest` e `AccessScreenTest` passam a exigir a ordem tema → `Acessar`, o texto do pé e
a ausência da descrição antiga. Nenhuma consulta, permissão, rota, integração, cor ou token foi
alterado; o sino já era filtrado por `user_id` e já usava `notifications.view`. Suíte: 413 testes /
3848 asserções.

**Restrições mantidas:** credencial da conta raiz fora de código, teste, doc, log e commit; usuário e
aviso fictícios removidos ao final da validação; prosa hifenizada com U+2011; paleta e logotipo
preservados; nenhum push.

## 22/11/2026 — Hambúrguer com rótulo e estado desenhado

**Decisão anterior:** o controle era uma caixa de 2,35×2,15rem com três barras iguais, sem rótulo e
sem tratamento de foco próprio — ele já obedecia ao AdminLTE, mas tinha cara de botão genérico.

**Nova decisão:** o botão vira pílula com ícone em casa própria e rótulo `Menu` no desktop; no celular
o rótulo some e o ícone fica sozinho. As três barras continuam desenhadas em CSS, agora com larguras
em degrau, dobra de 45°, elevação no hover, contorno de foco com `--nf-primary` e recuo no clique.
O estado continua lido de `sidebar-collapse`/`sidebar-open`, sem segunda fonte de verdade.

**Motivo:** o usuário pediu um menu hambúrguer atualizado e bonito. A atualização foi feita sem trocar
o contrato do AdminLTE 4 nem o espelho de acessibilidade do `menu.js`: a peça ganhou presença visual,
mas quem abre e fecha o painel continua sendo o template.

**Implementação:** `navbar.blade.php` passa a embrulhar as barras em `.nf-nav-toggle-icone` e acrescenta
`.nf-nav-toggle-texto`; `components.css` redesilha a pílula com tokens dos dois temas, transições e
foco visível; `CoerenciaVisualTest` passa a exigir o invólucro, o rótulo e o contorno de foco. Suíte:
419 testes / 3950 asserções.

**Restrições mantidas:** paleta e logotipo preservados, `data-lte-toggle="sidebar"` intacto, nenhum
glifo de biblioteca no lugar das barras, nenhum push.

## 22/11/2026 — Fecho da boas-vindas e credenciais no login

**Decisão anterior:** a apresentação terminava com `Comece pela operação que já existe`, explicava
como criar conta e repetia `Criar conta` em um bloco final; o formulário de entrada usava
`autocomplete="username"` e `autocomplete="current-password"`.

**Nova decisão:** o bloco final saiu da boas-vindas, que agora tem uma única porta — `Entrar` no
cabeçalho — e textos que só descrevem a entrega. O cadastro continua no rodapé da tela de entrada.
No login, o formulário e os dois campos passam a usar `autocomplete="off"`: o navegador pode guardar
a credencial, mas a aplicação não a reapresenta preenchida ao abrir a tela ou depois de um logout.

**Motivo:** o usuário pediu a remoção exata daquele bloco, uma página coerente com o que o produto
entrega e que as informações de e‑mail e senha não apareçam no formulário após o desloge. A solução
escolhida foi bloquear o autopreenchimento sem proibir o gerenciador de senhas, preservando os
placeholders genéricos e a regra de que senha nunca volta em `old()`.

**Implementação:** a seção `.nf-cta` e seu CSS morto foram removidos; os testes de coerência visual
passaram a exigir um único botão na página e a ausência do texto e do destino de cadastro. Um teste
novo em `AccessScreenTest` verifica `autocomplete="off"` no formulário, e‑mail e senha, valor vazio no
e‑mail e nenhum `value` na senha. Nenhuma cor, token, permissão, integração ou consulta foi alterada.
Suíte: 419 testes / 3950 asserções.

**Restrições mantidas:** credencial da conta raiz fora de código, teste, doc, log e commit; prosa
hifenizada com U+2011; paleta e logotipo preservados; nenhum push.

## 22/11/2026 — Casca pública no padrão da referência

**Decisão anterior:** a chave de tema havia ido para o canto superior direito do cabeçalho, ao lado
de `Criar conta` e `Entrar`; as telas de acesso vestiam o mesmo cabeçalho e o mesmo rodapé da boas-vindas,
com cópia longa e informal.

**Nova decisão:** a chave volta ao centro do cabeçalho público, entre a marca e a única ação do topo,
`Entrar`. Login, cadastro, recuperação e redefinição trocam o cabeçalho e o rodapé grandes por uma barra
enxuta — voltar à esquerda, tema à direita — e um cartão único com título curto e subtítulo objetivo.
O cadastro sai do cabeçalho e passa a viver no rodapé da tela de entrada; o link no fecho da boas-vindas foi retirado na decisão seguinte.

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
confirmação — os checkboxes de Termos/Privacidade da referência não foram adicionados. Suíte: 413 testes /
3848 asserções.

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
