<p align="center">
  <img src="docs/branding/marca-nexus.png" alt="NEXUS-FIELD" width="220">
</p>

<h1 align="center">NEXUS-FIELD</h1>

<p align="center">
  <strong>Field Service Management — a operação externa inteira em um só lugar</strong><br>
  Plataforma multiempresa para gerenciar clientes, ordens de serviço, chamados, agenda, check-in em
  campo com geolocalização, estoque, financeiro, relatórios e auditoria no mesmo painel.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/vers%C3%A3o-0.1.0-blue" alt="Versão 0.1.0">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
  <img src="https://img.shields.io/badge/AdminLTE-4.10-343a40?logo=laravel&logoColor=white" alt="AdminLTE 4.10">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white" alt="Vite 8">
  <img src="https://img.shields.io/badge/testes-318%20testes%20%2F%203050%20asser%C3%A7%C3%B5es-brightgreen" alt="318 testes, 3050 asserções">
</p>

<p align="center">
  <sub>Estágio atual: o plano de reconstrução fechou. Da fundação (fases 1 a 8) aos cadastros e
  ao catálogo (9 a 11), pela operação (12 a 18 — ordens, chamados, agenda, visita de campo, estoque,
  financeiro e relatórios), pelo controle (19 a 22 — notificações, contas e papéis, configurações da
  empresa e auditoria) e pela prontidão de deploy. A tabela
  <a href="#módulos">Módulos</a> diz, um por um, o que está no ar.</sub>
</p>

---

## Telas

Capturas do aplicativo rodando (Laravel + AdminLTE 4 sobre MySQL), nos dois temas e em celular, todas
no mesmo quadro de **1440×900**, duas por linha e na mesma escala de exibição. Cada imagem é um link:
o clique abre o arquivo no tamanho capturado. O painel de celular é fotografado no viewport real dele
(390×844) e montado sobre o mesmo quadro de 1440×900 — é o único quadro diferente da série, e a
legenda diz isso. Os painéis mostram a empresa de demonstração criada pelo `DemoSeeder` — é ela que
tem ordens, chamados, financeiro e estoque para os indicadores calcularem; na empresa real sem dados,
os mesmos blocos aparecem nos estados vazios. A série foi refeita depois que a fase 27 pousou, então ela
mostra a casa como ela está hoje: a boas-vindas sem botão no meio do hero e com o ciclo de seis passos em
faixa própria, e os catorze indicadores do painel com a micro-visualização que sai da mesma consulta do
cartão — o "Movimentações de hoje" do estoque, e os quatro do financeiro ("A receber", "A pagar",
"Recebido no mês" e "Despesa do mês").

<div align="center">

| Apresentação pública · tema claro | Entrada · tema escuro |
| :--: | :--: |
| <a href="docs/screenshots/01-boas-vindas.png"><img src="docs/screenshots/01-boas-vindas.png" alt="Página de apresentação pública do NEXUS-FIELD em tema claro: título e parágrafos à esquerda, a logo animada no palco à direita e o ciclo de um serviço em faixa própria abaixo" width="360"></a> | <a href="docs/screenshots/02-entrada.png"><img src="docs/screenshots/02-entrada.png" alt="Tela de entrada com e-mail, senha com botão de revelar, manter conectado e recuperação de acesso em tema escuro" width="360"></a> |

| Recuperação de acesso · tema claro | Painel no celular · tema escuro |
| :--: | :--: |
| <a href="docs/screenshots/03-recuperar-acesso.png"><img src="docs/screenshots/03-recuperar-acesso.png" alt="Tela de recuperação de acesso pedindo o e-mail da conta em tema claro" width="360"></a> | <a href="docs/screenshots/06-painel-celular.png"><img src="docs/screenshots/06-painel-celular.png" alt="Painel com os indicadores empilhados em uma coluna, capturado num celular de 390px e montado sobre quadro 1440×900 em tema escuro" width="360"></a> |

| Painel operacional · tema claro | Painel operacional · tema escuro |
| :--: | :--: |
| <a href="docs/screenshots/04-painel-claro.png"><img src="docs/screenshots/04-painel-claro.png" alt="Painel em tema claro com catorze indicadores, a fila de ordens da semana e a distribuição por estado" width="360"></a> | <a href="docs/screenshots/05-painel-escuro.png"><img src="docs/screenshots/05-painel-escuro.png" alt="Painel em tema escuro com os mesmos indicadores, tabelas e distribuição por estado" width="360"></a> |

</div>

---

## Sobre o projeto

O **NEXUS-FIELD** é uma plataforma FSM (Field Service Management): ela acompanha o serviço que
acontece fora da empresa, do chamado aberto até a assinatura do cliente no encerramento.

### Problema que resolve

Operação de campo costuma viver espalhada: a agenda em uma planilha, o cliente em outra, o técnico
no grupo de mensagens e a ordem de serviço em um documento anexado. O resultado é retrabalho,
histórico que não fecha e nenhum indicador confiável no fim do mês.

O NEXUS-FIELD centraliza essa cadeia em um fluxo só — cliente → ordem de serviço → chamado →
agenda → execução em campo com check-in e check-out geolocalizado → financeiro → relatório — com
cada etapa autorizada por permissão verificada no servidor e registrada em auditoria.

### Para quem

- Assistências técnicas e empresas de instalação e manutenção
- Field service de telecom, energia, refrigeração e TI
- Equipes de campo com ordens de serviço, SLA e visita agendada
- Operadoras que precisam medir custo, tempo e produtividade por técnico

### O que a plataforma entrega

- **Rastreabilidade**: cada estado de ordem de serviço e de chamado fica registrado com autor e data
- **Autorização real**: o servidor recusa antes de qualquer botão existir na tela
- **Multiempresa de verdade**: isolamento por empresa em toda consulta, não por convenção de tela
- **Indicador sem número inventado**: todo KPI do painel sai de uma contagem no MySQL daquela empresa
- **Interface nos dois temas**: claro e escuro, com a escolha persistida no navegador e no servidor
- **Fechamento conferível**: o relatório agrega a mesma consulta que a listagem mostra, e o CSV é essa coleção inteira

---

## Funcionalidades no ar

São as telas e regras que existem hoje no repositório. O que ainda não está aqui está listado em
[Módulos](#módulos) e no [Roadmap](#roadmap), com a fase em que chega.

### Autenticação

- Login com e-mail e senha, hash bcrypt e custo definido em `BCRYPT_ROUNDS`
- Sessão persistida em banco, com troca do identificador após entrar (contra session fixation)
- Bloqueio de usuário inativo e de empresa com assinatura vencida
- Limite de 5 tentativas por e-mail e IP, e throttle de 10 requests/min na rota de entrada
- Recuperação de acesso pelo password broker do framework: token de uso único, expiração própria e
  senha nova com no mínimo 10 caracteres, letras e números
- Botão de revelar senha em todo campo de senha, inclusive na redefinição
- Senha própria trocada por dentro da conta, com a senha atual como prova (ver "Perfil da conta")

### Autorização e papéis

- Catálogo único de permissões (`PermissionCatalog`) com 17 módulos e ações por módulo
- Cinco papéis de sistema sincronizados pelo seeder: Administrador, Supervisor, Funcionário,
  Técnico e Cliente
- `User::hasPermission` com cache por request e middleware `permission`, que responde 403 no servidor
- Menu montado a partir da permissão: item sem rota ou sem permissão não é desenhado

### Multiempresa

- `TenantContext` por request, middleware `ResolveCompany`, escopo global `CompanyScope` e o trait
  `BelongsToCompany`
- `anyCompany()` fura o escopo só quando o próprio código restringe pela empresa do request: o único uso
  é `CompanySetting::valueFor()`, e a linha seguinte filtra pelo `company_id` de quem está logado. Tela
  nenhuma enxerga fora da própria empresa

### Painel

- Catorze indicadores contados no banco da empresa logada, mais a fila de ordens da semana, a
  distribuição por estado, as contas que vencem nos próximos quinze dias e os cinco últimos pagamentos
  registrados
- Micro-visualização por cartão, desenhada pela casa em Blade + SVG, sem biblioteca de gráfico: o traço da
  série dia a dia embaixo de "Concluídas em 7 dias", "Agenda de hoje" e "Movimentações de hoje", e recebido
  contra pago em seis meses no cartão de caixa. Cada ponto é a contagem que o banco devolveu naquele dia ou
  naquele mês, no alcance de quem olha — o técnico vê a própria fila no traço, como vê nos números
- A mesma fonte, aberta por período: número e traço respondem à mesma pergunta sobre o mesmo recorte (a
  série diária somada em sete pontos é o total do cartão de sete dias; o ponto que fecha a série é o número
  dos cartões de hoje e do mês), e a prova do painel cobra essa régua em vez de deixar cada janela seguir
  um caminho próprio
- Anel de pontualidade no rodapé da distribuição de estados: dos serviços concluídos em trinta dias com fim
  previsto, quantos terminaram dentro do prazo que a própria ficha declarou. Ficha sem prazo fica fora da
  conta, e sem conclusão na janela o anel não existe — proporção de quê?
- Série sem dado não desenha: menos de dois pontos, ou tudo zero, deixa o cartão só com o número dele,
  porque linha rasteira na base é a figura dizendo que houve movimento constante onde o banco respondeu
  "nada aconteceu"
- Estados de interface reais: carregando, vazio, sem permissão e erro
- `DemoSeeder` local, que grava a demonstração numa empresa separada (`nexusfield-demo`) e recusa produção

### Cadastros

- **Clientes**: lista com busca, situação e cidade, paginação própria e exportação CSV; ficha com
  contatos e endereços cadastrados em linha, as ordens e os chamados mais recentes dele, as janelas
  de agenda que ainda vêm, os serviços somados das linhas das ordens, a receita lançada no nome dele
  e a trilha da própria linha — cada número no cartão do domínio que o possui, nenhum repetido;
  exclusão lógica com restauração, e o servidor recusa excluir quem já gerou ordem, chamado ou
  lançamento
- **Técnicos**: escala com busca, situação, região e especialidade; ficha com especialidades, equipes
  (com data de entrada e de saída), base de trabalho com endereços, as ordens e os chamados no nome
  dele, as janelas que a agenda ainda reserva, a carga medida na mala, os últimos check-ins contados
  na tabela de check-in e a trilha da própria linha
- **Equipes**: quadro com líder, entrada e saída de membro gravadas na tabela intermediária — a
  passagem anterior continua histórica —, as ordens que a equipe tem em aberto e exclusão só permitida
  com quadro vazio
- **Especialidades**: catálogo com o tanto de técnico que cada uma cobre; o slug nasce do nome e a
  exclusão é recusada enquanto houver alguém usando-a
- Endereço é relação polimórfica compartilhada (`TemEnderecos`): cliente e técnico têm o mesmo
  formulário, o mesmo ciclo de vida e a mesma regra de endereço principal único
- As três telas de lista passam pelos mesmos `ListFilters`, pela mesma paginação própria e pelos mesmos
  estados desenhados de vazio e de nenhum resultado com estes filtros

### Catálogo

- **Serviços**: preço e duração estimada por linha do catálogo; a ficha mostra onde o serviço já foi
  aberto como ordem e já foi cobrado como item, contado no banco, e é essa soma que segura a exclusão
- **Produtos**: SKU único por empresa, unidade vinda do catálogo do projeto e saldo que ninguém digita —
  ele é a subquery que soma as movimentações com o sinal de cada tipo (`StockMovement::CENTRAL_SIGN`),
  então consumo não mexe no estoque central e ajuste de inventário pode baixar
- **Categorias de serviço**: agrupamento que a listagem de serviços usa como filtro; o slug nasce do
  nome e a exclusão é recusada enquanto houver serviço no grupo
- Nome de serviço, SKU e slug são únicos **dentro da empresa**, não no banco inteiro — a mesma regra de
  tenant que vale para cliente e técnico
- Serviço e produto com histórico não somem: inativar tira da escolha e preserva o preço praticado;
  excluir só é permitido quando a contagem do banco dá zero

### Operação

- **Ordens de serviço**: numeração anual sequencial por empresa (`OS-2026-0007`), gerada no banco — ninguém
  digita número, e a empresa ao lado tem a sequência dela
- Criar ordem copia o endereço do cliente para a ficha e o fim previsto vem da duração estimada do serviço;
  depois disso a ordem é o documento daquele dia, e mudar o cadastro do cliente não reescreve ordem antiga
- Estado só muda pelo botão da ficha, que grava origem, destino, quem fez e quando em
  `service_order_status_history`; o fluxo (`FLUXO`) é o que vale — pular de rascunho para concluída não passa,
  e cancelar sem motivo registrado é recusado
- Abrir ordem do rascunho e cancelar pedem `orders.approve`; executar é de quem está em campo; exportar é do
  escritório. O servidor responde 403 antes de qualquer botão escondido
- Linhas cobradas são congeladas: descrição, quantidade, valor unitário e desconto ficam na ordem, então o
  catálogo pode mudar de preço depois sem reescrever a conta. A linha é serviço **ou** produto, nunca os dois
- Total, bruto e descontos saem de subconsultas do MySQL (`withTotals`), não de soma em PHP — a listagem, a
  ficha e o CSV mostram o mesmo número porque é a mesma consulta
- Quadro de comissão registra quem apoiou a ordem com data e nota; a saída libera o vínculo preservando a
  passagem, e assumir a responsabilidade troca o técnico da ordem sem perder o histórico
- O painel e a listagem respeitam o alcance: o técnico conta a própria fila, a conta de cliente conta a
  carteira dela (por `users.client_id`), e só o escritório responde pela empresa inteira
- Ordem encerrada é conta fechada: linha, quadro e estado não aceitam mudança, e a única exclusão permitida é
  a do rascunho que nunca virou trabalho
- **Chamados**: protocolo `CH-2026-0007` gerado no banco pela sequência do ano da empresa — a mesma regra
  da ordem, com o `max()` dentro de transação para dois chamados simultâneos não colidirem
- A prioridade não é enfeite: ela define o prazo em horas (`PRAZO_HORAS`), o painel calcula
  `prazo_em` na gravação, e "atrasados" é o prazo vencido sobre um estado que ainda não encerrou —
  nada de coluna de SLA digitada
- O relato e a resposta entram por Summernote e viram HTML no banco. `TextoSeguro` poda por lista fechada:
  tag e atributo que não estão na lista somem, `javascript:`/`#`/relativo não são link aceito, `<script>`,
  `<img>`, `<iframe>` e companhia caem com o que têm dentro, e link que sobrevive ganha `noopener nofollow`
- Estado só anda pelo botão da ficha, que escreve origem, destino, autor, hora e nota em
  `ticket_status_history`; o fluxo (`FLUXO`) é o que vale, resolver sem dizer o que foi feito é recusado,
  e fechar um resolvido dispensa a frase porque a resolução já está na ficha
- Conduzir o estado é `tickets.execute`; resolver e fechar pedem ainda `tickets.close`. A conta de cliente
  lê a própria carteira e responde na conversa, mas não move estado nenhum — o servidor recusa antes da tela
- Nota interna existe para o escritório pensar sem vazar: `is_internal` só é aceito de quem tem
  `tickets.update`, e a consulta da ficha de quem não tem simplesmente deixa essas notas fora do resultado
- Reabrir limpa o carimbo de resolução, o prazo volta a correr, e o que estava congelado (pendência do
  chamado, contagem do painel) volta a contar
- exportar chamado é `tickets.export` e entrega o mesmo CSV da listagem, com a descrição reduzida ao texto
  que se lê — o rótulo do editor não viaja para a planilha
- **Agenda**: FullCalendar 6 lendo o MySQL da empresa por janela de tempo, não a tabela inteira — o feed
  exige `inicio` e `fim` e fecha a varredura em 120 dias, porque um `?fim=` digitado na URL não pode virar
  varredura de nove anos
- A janela que ainda não chegou tem cara: cinco linhas de esqueleto com o shimmer da casa, o quadro
  esmaecido e `aria-busy` no calendário. Espera sem aviso é a mesma cara de uma agenda vazia, e as duas
  coisas não significam a mesma coisa
- Duas naturezas no mesmo quadro: o compromisso, que se cria, edita, move e apaga, e a janela marcada na
  ordem de serviço, desenhada tracejada e somente-leitura — mover a ordem é na ficha dela, onde o motivo
  do remanejamento fica registrado
- Arrastar é escrita no banco, e a caneta é de `AgendamentoDeCompromisso`: a rota confere o alcance de
  leitura, o serviço confere permissão, estado e janela antes de gravar hora e, se recusa, o evento volta ao
  lugar de onde saiu com o motivo no aviso. Calendário que aceita o que o banco não aceitou é a maneira mais
  rápida de mentir para quem lê a escala
- Compromisso concluído é fato passado: não se arrasta, não se edita, e o quadro nem oferece o gesto.
  O estado anda só pelo fluxo `agendado → concluído|cancelado` e `cancelado → agendado` — remarcar é
  exatamente o que a agenda serve para fazer
- Dia inteiro tratado nas duas pontas: o FullCalendar lê o fim como exclusivo, o banco guarda o último
  dia, e o arraste desloca a janela inteira pelos dias de diferença preservando a duração gravada
- O relógio é de parede, não de fuso: `APP_TIMEZONE` e o MySQL da instalação conversam no mesmo horário,
  então a janela viaja e volta sem offset — mandar ISO com `Z` faria a visita das 9h chegar às 6h
- Painel e calendário contam a mesma régua: `Appointment::visiveisPara()` é o mesmo alcance dos dois lados,
  o filtro `?tecnico=` da URL entra depois dele, e um compromisso sem dono nunca some da escala de ninguém
- O calendário é o único pacote carregado sob demanda: o chunk do FullCalendar só desce na tela que o
  desenha, e o resto da aplicação continua do tamanho de antes. Sem JavaScript a tela diz a verdade em vez
  de deixar um quadro vazio fingindo que carrega
- **Visitas de campo**: a chegada é registrada na ficha da ordem (`ordens/{ordem}/chegada`) e a saída na
  passagem (`visitas/{visita}/saida`), porque uma ordem pode ter dois técnicos em campo ao mesmo tempo e
  quem responde pelo endereço medido é a ordem
- Hora, autor, técnico e distância não chegam pelo request: `checkin_at` é o relógio do servidor na conta
  de quem loga, `technician_id` é deduzido da conta — ou da ordem, quando quem registra é do escritório —
  e a distância é a fórmula de haversine do servidor (`Distancia::metros`) entre a coordenada lida e o
  endereço congelado na ficha da ordem
- Coordenada é opcional e a falta dela tem nome: visita sem GPS aparece como **sem posição lida**, nunca
  como `0,00 m`. Medida que não existe não é medida no portão, e o filtro `?sem_posicao=1` existe para o
  escritório achar exatamente essas visitas
- O raio aceito é decisão guardada no banco da empresa (`company_settings`, chave `checkin_raio`, padrão
  250 m), lida por `ServiceOrderCheckin::raioAceito()`. Fora do raio a visita **é gravada e marcada**: o
  sistema registra o que foi medido, não recusa a presença de quem foi trabalhar — GPS falha em prédio e
  em subsolo, e quem está no local continua tendo estado lá
- Chegada em rascunho é recusada (a ordem precisa ser liberada para o campo antes), e abrir a ordem pelo
  check-in segue o fluxo `FLUXO`: escreve em `service_order_status_history` quem mudou, de onde para onde
  e quando. A saída encerra a passagem e **não** encerra a ordem — relatório de serviço é outra decisão
- O alcance manda na leitura: o escritório conta a empresa inteira na tela `/visitas`, o técnico só as
  próprias passagens (e não vê o seletor de técnico nem o de cliente), a conta de cliente só os técnicos
  da carteira dela, e uma visita da empresa ao lado é 404 antes de qualquer comparação de permissão.
  Encerrar passagem é `orders.execute` **ou** `orders.approve`, porque o supervisor responde pela ordem
  e precisa fechar a visita que ficou aberta num aparelho sem bateria
- A posição é lida no aparelho (`navigator.geolocation`) pelo botão "Ler posição do aparelho", com o raio
  e a coordenada da ordem entregues à página como dados, e o resultado é medido no servidor. Sem
  JavaScript o mesmo formulário continua registrando a chegada — só que sem medida, e dizendo isso
- `/visitas` usa os mesmos `ListFilters`, a mesma paginação própria e o mesmo CSV (`;`, BOM, `Export`) da
  listagem: período, técnico, cliente, situação, busca por número de ordem ou nome, e o estado desenhado
  de vazio quando nada bate com os filtros
- **Estoque**: não há campo de quantidade em lugar nenhum. `products` não tem coluna de saldo — o central
  é a subquery SQL que soma as movimentações com o sinal de cada tipo (`StockMovement::CENTRAL_SIGN`), e é
  a mesma consulta da lista de produtos, da ficha, do painel e do livro-caixa
- Cinco tipos com dois donos diferentes: compra e ajuste de inventário mexem no estoque central; carga,
  consumo e devolução mexem na mala do técnico (`TECHNICIAN_SIGN`). Consumo não baixa o central de novo
  porque a unidade já saiu dele na carga — e é isso que a prova de saldo verifica linha por linha
- Saldo negativo não nasce: a conta é feita dentro de uma transação com a linha do produto e a linha de
  carga travadas por `lockForUpdate`, e o índice único `(technician_id, product_id)` — migration desta fase
  — é o que dá sentido à trava, porque sem uma linha por par dois consumos simultâneos baixariam três
  unidades de um produto que tinha duas
- Livro-caixa não se edita nem se apaga: as rotas do módulo são `index`, `create`, `store` e `export`, e não
  há rota para mudar ou riscar uma linha. O que estava errado se responde com outra linha, e a auditoria
  mostra as duas
- Data, autor e empresa não vêm do request: `recorded_at` é o relógio do servidor, `user_id` é quem está
  logado e `company_id` é o contexto resolvido no middleware — os quatro campos que uma posting forjada
  tenta mandar são ignorados, testado linha por linha
- O recorte é responder pelo inventário: quem tem `stock.adjust` lê e move a empresa inteira; o técnico com
  ficha fala da própria carga, lê só as linhas em que o nome dele aparece, e o formulário nem oferece
  seletor de técnico nem os tipos compra/ajuste — a mesma lista que o select mostra é a que o servidor
  aceita, e ela morre na validação do campo. Exportar é `stock.export`, e a conta restrita recebe 403
- O aviso de reposição sai na resposta que baixou o saldo, não na semana em que alguém abrir o painel:
  cruzando o ponto de reposição, a mesma redirect leva o motivo com os dois números
- O painel conta movimentações de hoje e lista as seis últimas no mesmo alcance da listagem, e a ficha do
  técnico mostra o estado material da mala dele — carga zerada não aparece, e quem tem a ficha não abre a
  do colega pelo cartão
- Filtros de tipo, produto, técnico, ordem, busca e período, paginação própria e CSV (`;`, BOM, `Export`)
  saem da mesma `consulta()`, então o que a tela filtra é o que o arquivo entrega
- **Financeiro**: a conta não tem campo de estado. O formulário não oferece select de "Pago" e não existe
  rota para marcar conta como paga — `status` é derivado da soma dos pagamentos contra o valor, calculado
  pelo servidor dentro da transação que grava o dinheiro, com a linha travada por `lockForUpdate`. O teste
  confere a lista inteira de rotas do módulo, uma por uma, para provar que o atalho não existe
- Registrar dinheiro é o que move a conta: `payments` guarda valor, método, data, autor e referência, e o
  lançamento passa de em aberto para recebido em parte e depois recebido conforme a soma. Dois pagamentos no
  mesmo segundo não fecham a conta com metade do valor porque a soma volta do banco, não da tela
- `occurred_at` é o último pagamento, não uma data digitada: lançamento sem pagamento não tem ocorrência,
  porque o fato não aconteceu. O vencimento continua previsto, e é dele que sai o "vencido" — contado como
  relação entre o previsto e hoje, não como coluna
- Acima do saldo não entra, e a recusa diz quanto falta; conta quitada não recebe pagamento; pagamento em
  conta cancelada é recusado com o convite para reabrir. Data tem teto de hoje e piso de dois anos para
  trás, porque dinheiro que ainda não mudou de mão é previsão, e reabrir exercício não se faz por aqui
- Estornar é degrau de quem responde pelo caixa (`financial.approve`), e o pagamento é procurado dentro da
  conta: um id de outra conta responde 404 antes de qualquer escrita. Cancelar só existe sem pagamento
  registrado e exige motivo — desfazer dinheiro que entrou é estorno, tem outro verbo e outra permissão
- Reabrir devolve a conta à derivação: retira o cancelamento e deixa a soma dos pagamentos dizer o estado,
  então uma conta reaberta que ainda tem dinheiro registrado volta como parcial, não como em aberto
- Excluir é caso de cadastro errado: lançamento com pagamento registrado não se apaga, porque o pagamento
  ficaria sem dona na hora em que o relatório somar o mês. A exclusão é lógica, e `?estado=excluidos` acha e
  restaura a linha
- Categoria é vocabulário fechado por tipo, não campo de nota (`FinancialRecord::CATEGORIAS`: cinco receitas,
  seis despesas). O legado deixava digitar, e em dois anos "Peças", "pecas" e "pç" viraram três categorias
  que ninguém soma — o rótulo fora do catálogo se descreve sozinho em vez de sumir da tela
- Cliente é da receita: despesa com `client_id` no request tem o campo descartado, não aplicado, e receita sem
  cliente é recusada. Ordem e cliente têm de apontar para a mesma carteira — a validação de campo confere cada
  um isoladamente e é o controller que enxerga o par
- Cobrança de ordem (`ordens/{ordem}/cobranca`) recomputa o valor no banco a partir das linhas, descontos e
  total da OS: o `valor`, o `cliente_id` e a `empresa` que o request tenta mandar são ignorados. Só ordem
  concluída cobra, ordem sem linhas não cobra, e segunda cobrança ativa da mesma OS é recusada — duplicata de
  cobrança não é segunda via, é conflito
- O rodapé de totais, a coluna de pago e a ficha somam a mesma expressão SQL (`PAGADA_SQL`), escrita uma vez
  e sem binding: o saldo que a tabela mostra não é parecido com o da ficha, é o mesmo
- Painel: "A receber" e "A pagar" contam o que falta, não o previsto — numa conta meio paga o dinheiro que já
  entrou não pode ser contado de novo —, "Recebido no mês" traz o delta contra o mês anterior, e "Despesa do
  mês" fecha com o saldo do período. Os quatro saem do mesmo `totais()` da carteira
- A carteira (`/financeiro`) filtra por busca, tipo, estado (inclusive vencido e excluídos), categoria,
  cliente, ordem e período de vencimento, com ordenação própria, paginação própria e CSV da mesma consulta —
  vencimento no período, não data de cadastro, porque quem abre o mês quer as contas que vencem ali, pagas ou
  não
- Os degraus são diferentes de propósito: registrar o dinheiro é `financial.create` (o estado vem junto),
  conduzir a conta é `financial.update`, estornar e cancelar são `financial.approve`, e apagar a ficha é
  `financial.delete` — o técnico e a conta de cliente não chegam na carteira, e recebem 403 do servidor antes
  de ver qualquer botão

### Relatórios e exportações

- **Quatro fechamentos de período** (`/relatorios`): financeiro por categoria, operação por técnico, chamados por
  prioridade, por categoria do serviço ou por técnico, e estoque por produto. Cada um responde a pergunta que está
  escrita no próprio catálogo (`Relatorio::CATALOGO`), e o hub só mostra o cujo módulo a conta lê
- **A janela é resolvida no servidor**: sem datas no link ela é o mês corrente, com uma só a outra ponta entra a
  30 dias, início depois do fim é invertido, e acima de 366 dias o início é trazido para dentro com um aviso que
  diz quantos dias foram cortados — porque um `between` de 1900 a hoje não é relatório, é a consulta que derruba
  o banco da empresa pelo link mais fácil
- **O número é o que a listagem já mostra**: as quatro agregações partem dos mesmos `visiveisPara()` das telas de
  ordem, chamado, carteira e movimentação, então o fechado é conferível linha por linha por quem o lê
- **Tela e arquivo são a mesma coleção**: a tela fatia em paginação própria, o CSV devolve o inteiro com a ordem
  vigente, e o link de exportação carrega a janela resolvida, o tipo, o ângulo e a ordenação que estavam na frente
  da pessoa
- **Ordenação por lista fechada**: as colunas ordenáveis de cada relatório estão em `ORDENAVEIS` e a coluna pedida
  nunca chega ao SQL — desconhecida, vazia ou de injeção cai na ordem do vocabulário, e valor não medido fica no
  fim nos dois sentidos, porque técnico sem tempo medido não é zero minuto
- **CSV honesto com o Excel brasileiro**: separador `;`, BOM no início, dinheiro e duração já formatados, e nome
  de arquivo com o período que aquele arquivo resume
- **Dois degraus, mais o do módulo**: `reports.view` abre o hub e as telas, `reports.export` faz o arquivo — que
  atravessa a fronteira da empresa numa planilha anexada —, e cada fechado pede ainda a leitura do módulo que
  resume. Sem `financial.view`, por exemplo, o financeiro responde 403 nomeando o degrau faltante e o cartão não
  aparece no hub, em vez de levar a uma tela de zeros
- **Fora do catálogo é 404**: a rota de exportação é constrained às quatro chaves, então
  `relatorios/{qualquer-coisa}/exportar` não monta agregação inexistente

### Notificações

- **O sino é por conta, nunca por empresa**: a central (`/notificacoes`) filtra `user_id` de quem logou
  antes de qualquer filtro, o PATCH de marcar lida responde 404 para aviso alheio sem confirmar que a
  bandeja existe, e "marcar todos" varre só a caixa de quem clicou
- Seis gatilhos em atos de negócio reais — ordem criada, comissão no quadro, conclusão, cancelamento,
  chamado novo e resolução — mais o flash de falta no central, que agora também acorda quem repõe
- Deduplicador por fato e origem: sem leitura no meio, o segundo toque do mesmo fato não martela a
  mesma conta
- Varredura diária às 07:00 (`nf:notificacoes:diaria`, `withoutOverlapping`): ordem vencida no prazo
  previsto, conta a vencer em até dois dias e a agenda de amanhã — cada empresa varrida dentro do
  próprio contexto de tenant, e a conta de cliente fica fora do atraso de propósito
- Sino na barra com contagem teto 99+, dropdown dos seis últimos e fio de marca nos não lidos; o item
  da sidebar entra para todo papel com `notifications.view`, inclusive o cliente

### Usuários e papéis

- A regra do núcleo: **ninguém concede o que não tem**. O editor só vê, no formulário, os papéis cujo
  conjunto de permissões cabe dentro das dele, e mexer numa conta que enxerga mais do que você
  responde 403
- Folha de ponto com quota: o topo diz "X contas na folha · plano permite N", e criar conta acima do
  teto do plano é recusada antes de qualquer INSERT
- A conta raiz não se edita nem se exclui por tela; ninguém desativa a própria conta, nem tira de si
  o último papel de administrador, nem exclui a conta que ainda é o acesso de uma ficha de técnico
- Pareamento papel Cliente ↔ carteira cobrado no servidor: conta de papel Cliente aponta para uma
  carteira da empresa, e uma carteira não aceita duas contas de acesso
- Papéis de sistema são intocáveis por tela; personalizados têm CRUD completo, slug imutável de
  nascimento, exclusão recusada enquanto houver conta atrelada, e `nf:papeis:sincronizar` regrava os
  cinco de sistema sem encostar nos personalizados

### Perfil da conta

- `/perfil` fecha a cadeia que a plataforma promete: entrar, trabalhar e **saber quem se é nela**. Não tem
  permissão no caminho de propósito — perfil não é módulo da empresa, é a única coisa que pertence de fato
  a quem está logado, e negá-la a um técnico seria dizer que ele não pode encostar na própria conta
- A tela lê do banco a identidade inteira: empresa, plano, papéis concedidos, chave de acesso, telefone,
  último acesso, data de nascimento da conta e os vínculos de ficha (técnico e carteira), cada link só
  aparece para quem tem o degrau de abrir a tela de destino
- Quem conduz campo vê, no espelho, as **próximas janelas da própria agenda** — as mesmas linhas que o
  calendário desenha, ordenadas por horário, com o cliente e a ordem presos
- Trocar a chave de acesso (o e-mail, que é o que o login digita) e renovar a senha exigem
  `current_password`: sem essa prova, um terminal deixado aberto viraria a porta para assumir a
  identidade alheia. A senha nova segue o mesmo mínimo do reset — 10 caracteres, letras e números — e
  precisa ser diferente da atual
- Renovar a senha derruba as demais sessões da conta quando o driver de sessão é o banco; a sessão que
  renovou continua aberta. A senha em si nunca entra em log nem em auditoria: o que fica registrado é o ato
- A conta raiz mantém a guarda do modelo no espelho: o e-mail dela não se troca por tela nenhuma, e a
  recusa chega como 302 com o motivo na tela — não como 500
- "Meu perfil" entrou no dropdown do cabeçalho, um degrau acima de "Sair"

### Configurações da empresa

- Perfil editável com freios honestos: a chave de endereço e o plano não se mexem por esta tela nem
  para o administrador — o primeiro é único para sempre, o segundo é contrato com a plataforma
- Marca própria: o envio valida PNG/JPG/WEBP entre 64 e 1200 pixels por lado, até 2 MB, e o SVG armado
  de script fica do lado de fora; a marca antiga sai do arquivo junto com a troca, sem imagem órfã
- Cinco preferências com consumo vivo — raio do check-in, janela do alerta de vencimento e três chaves
  que ligam/desligam as famílias do sino — e número vazio é «sem opinião»: a linha sai e o padrão da
  casa volta a valer
- Supervisor vê, administrador gere: em modo leitura cada campo vem desabilitado com o cartão que diz
  por quê, e o PUT sem `settings.manage` é barrado com 403 antes de encostar em qualquer valor. Cada
  campo alterado sai carimbado na auditoria com o antes e o depois

### Auditoria

- A trilha virou tela (`/auditoria`): quando, quem, que ação, sobre o quê, de onde (IP e navegador) e
  o antes/depois campo a campo, com busca, filtro por entidade e por conta, período, ordenação e
  paginação das primitivas compartilhadas
- O nome do autor é congelado no ato — quem mudou de nome não reescreve a história — e a ficha avisa
  quando a conta que fez aquilo saiu do acesso
- Exportar CSV tem degrau próprio (`audit.export`) e sai exatamente pela mesma consulta filtrada da
  listagem
- Ver é degrau de gestão (`audit.view`): funcionário, técnico e cliente recebem 403 do servidor antes
  de qualquer link aparecer, e ato de outra empresa responde 404
- Rótulo de entidade mora no model (`StockMovement` → "Movimentação de estoque"), nunca código-fonte
  na tela; linhas antigas do tempo em que o gravador codificava o diff duas vezes continuam legíveis

### Interface

- AdminLTE 4 na estrutura oficial, Bootstrap 5.3 nos componentes e camada de tokens própria
- Paleta "Premium Gourmet + Technology": só os verdes do medalhão como marca, medidos da própria logo,
  fundo preto no escuro e branco neutro no claro, sem casta de cor e sem latão
- Matéria do painel: o cartão tem wash e filete de luz interna (`--nf-tint`/`--nf-sheen`), a divisão com
  cabeçalho e rodapé é fio gravado (`--nf-etch`), e o tom do indicador — não a cor de marca — corre no
  trilho esquerdo do KPI, no quadrado do ícone e na barra de distribuição. Etiqueta de KPI e cabeçalho de
  tabela saem em versalete mono (`--nf-font-mono`); o valor continua na display serifada da casa, com
  figura tabular. O ponto de estado pulsa só nos estados que ainda estão acontecendo, e nenhum desses
  tokens é utilitário de framework: são variáveis próprias, medidas nos dois temas
- Fechados com a mesma matéria: a faixa "Período fechado" traz o trilho de 2px, que passa para o tom de
  perigo quando o teto de dias corta a janela, e as abas entre os relatórios formam um seletor segmentado
  num trilho só. No celular esse trilho rola dentro da própria faixa
- Ficha e formulário com a mesma régua: título de seção e rótulo de `<dl>` em versalete mono, linha de
  item com luz na quina e o foco subindo do campo para a linha, valor monetário inteiro numa linha só
  (`.nf-valor` — frase explicativa continua quebrando), e no polegar o par "Total" empilha rótulo e
  número em vez de partir o número ao meio
- A casca pública veste a mesma matéria: a caixa "O ciclo de um serviço" traz o trilho de 2px no lugar
  da borda de marca, o fio entre os passos desvanece, e o cartão de login e a faixa de chamada têm a
  luz na quina dos cartões autenticados. O auto nível do medalhão continua intocado
- Topo e pé enxutos: 44px de altura no cabeçalho (a mesma nos dois lados da aplicação) e uma linha no
  rodapé das telas abertas
- A micro-visualização veste o cartão, não o contrário: traço, área e anel usam `--nf-tone` e
  `--nf-tone-soft` do registro único de tom (`.tone-*`), sem uma tinta fora do que os dois temas já
  definem, e o traço de espessura fixa (`vector-effect: non-scaling-stroke`) conta a mesma história no
  celular de 390px e na Smart TV
- Figura decorativa para quem vê, número lido para quem não vê: o `aria-label` do traço devolve "começa
  em, termina em, maior ponto" com os valores reais da série, e o anel fica `aria-hidden` porque repete o
  percentual escrito ao lado dele
- A espera tem cara, e ela não é o spinner do vendor: onde a tela pede dados ao servidor depois de pintada
  — hoje é só a janela da agenda — o quadro esmaece, o esqueleto de cinco linhas assume e `aria-busy` avisa
  quem usa leitor de tela que aquilo ainda vai mudar. Silêncio na tela é lido como "não há nada"
- A virada de tema desliza em 200ms, e só ela: o script veste `nf-tema-virando` no `<html>` durante o
  clique e solta 260ms depois. Transição permanente faria a página subir do branco em toda recarga, e o
  cookie com o script inline do `<head>` existem justamente para o tema chegar antes da primeira pintura
- A folha não guarda peça de vitrine: as 205 classes `nf-` dos seis arquivos de estilo são varridas em
  teste contra Blade, JavaScript, controller e seeder — o que ninguém veste sai da folha, porque duas
  maneiras de escrever a mesma coisa, uma delas nunca lida, é exatamente a repetição que este desenho vetou
- A folha também não se desmente: nenhum seletor de topo declara a mesma propriedade duas vezes. Vinte
  declarações estavam escritas por baixo de outra que as venciam, e nunca foram pintadas — o corte saiu
  provado no navegador, com o estilo computado idêntico byte a byte antes e depois
- Um caminho de rede por tela: na agenda, a leitura da janela e a escrita do arrasto passam pelo mesmo
  `pedido()`, que é quem conhece cabeçalho, corpo, CSRF e as duas caras do erro — `Recusa` quando o
  servidor diz não, `SemConexao` quando ele não responde. Dois blocos de `fetch` quase idênticos são o
  jeito mais rápido de uma mensagem de rede ficar certa em um botão e errada no outro
- A escrita por arrasto tem cara enquanto o banco não respondeu: o compromisso esmaece com o fio do
  próprio tom, e a classe sai no `finally` — gravado, recusado ou sem conexão, os três desfechos
- A palavra não se parte porque o layout cedeu antes dela: `overflow-wrap: anywhere` não existe em nenhuma das
  seis folhas — ele entra na conta do min-content, e a coluna encolhe até cortar o rótulo em duas metades que
  ninguém lê como uma palavra só. Ficou `break-word`, em que quem negocia largura é o layout: o rótulo da lista
  de fatos é `flex: 0 0 auto` e o valor tem folga (`flex: 0 1 auto; min-width: 0`) para descer inteiro. A outra
  ponta é a linha que sobra: `text-wrap: pretty` nos parágrafos e `balance` nos títulos, porque última linha
  com uma palavra órfã é acidente de largura, não decisão de leitura
- A tela aberta tem uma porta de entrada, não três: o cabeçalho chama para entrar e o bloco final fecha a
  chamada. O meio do hero não repete o endereço que já está no canto superior direito — duas portas para o
  mesmo lugar, na mesma tela, só disputam o clique entre si
- O botão do menu desenha o estado em vez de trocar de glifo: três barras em CSS, uma moldura e uma variável,
  `--nf-dobra`, preenchida pelos quebradores do template (`sidebar-collapse` no desktop largo, `sidebar-open`
  no celular). Aberto, as barras paralelas com a do meio mais curta são o menu recolhido em miniatura; fechado,
  elas se dobram num `>`. `menu.js` não manda no menu — espelha a classe do `<body>` em `aria-expanded`,
  `aria-label` e `title`, para o leitor de tela não continuar dizendo "Expandir" depois de o menu ter aberto
- A abertura fecha em faixa própria, não em vão: a esteira do ciclo é irmã do row e atravessa a página
  inteira — seis passos numerados, o fio que nasce de um disco e morre no seguinte, e um fecho que diz onde ela
  desemboca. O serviço assinado é o que abre a cobrança da ordem, no valor que as próprias linhas somam: a
  mesma régua que o financeiro aplica quando a ordem ainda não terminou. No meio-tablet a faixa vira duas
  colunas e no celular, uma lista vertical
- Hífen não é corte na casca pública: o quebra-linhas trata o traço comum como oportunidade de quebra, e "e-"
  numa linha com "mail" na seguinte é a mesma palavra que ninguém lê de uma vez. Nos termos que correm em
  parágrafo, título ou item das telas abertas a casa escreve o hífen que não quebra (U+2011) — `e‑mail`,
  `Check‑in`. Etiqueta de formulário e nome da marca seguem com o traço de sempre: palavra sozinha não parte
- Tema claro/escuro persistido em `localStorage` e em cookie, aplicado antes da primeira pintura
- O menu veste o tema da página: no claro ele é a superfície elevada com o verde de marca em quem está
  ativo, no escuro encosta no preto e só o fio de borda separa as duas áreas
- Marca nos navegadores: `favicon.ico` com 16, 32 e 48 embutidos, PNGs de 32 e 192 servidos por
  `<link rel="icon">` e `apple-touch-icon` achatado sobre preto, todos gerados do medalhão oficial
- Auto nível nas telas abertas: a logo do hero balança em amplitude decrescente e para nivelada, com o
  halo acendendo no assentamento; o mesmo gesto, menor, acima do título de entrada. É CSS, não GIF, para
  continuar legível nos dois temas e respeitar `prefers-reduced-motion`
- Diálogos e avisos só por SweetAlert2 e Toastr — `alert()`, `confirm()` e `prompt()` nativos são
  vetados no projeto e cobertos por teste
- Layout responsivo, com o painel em uma coluna no celular e a linha de listagem virando ficha: cada
  célula passa a mostrar o rótulo da própria coluna, lido do cabeçalho por `listas.js`. Sem
  JavaScript a ficha não veste nada — a tabela continua tabela, rolando onde já rolava

---

## Módulos

O banco já modela o domínio inteiro (fase 2). As telas vieram uma fase por vez — e todas estão no ar.

| Módulo | Schema | Permissões | Tela |
| --- | :---: | :---: | :---: |
| Autenticação e recuperação de acesso | ✅ | ✅ | ✅ |
| Painel e indicadores | ✅ | ✅ | ✅ |
| Página pública de apresentação | — | — | ✅ |
| Temas, diálogos e estados de interface | — | — | ✅ |
| Clientes e contatos | ✅ | ✅ | ✅ |
| Técnicos, equipes e especialidades | ✅ | ✅ | ✅ |
| Catálogo de serviços e produtos | ✅ | ✅ | ✅ |
| Ordens de serviço | ✅ | ✅ | ✅ |
| Chamados | ✅ | ✅ | ✅ |
| Agenda e compromissos | ✅ | ✅ | ✅ |
| Check-in / check-out com geolocalização | ✅ | ✅ | ✅ |
| Estoque e movimentações | ✅ | ✅ | ✅ |
| Financeiro (contas a receber e a pagar) | ✅ | ✅ | ✅ |
| Relatórios e exportações | ✅ | ✅ | ✅ |
| Notificações | ✅ | ✅ | ✅ |
| Usuários e papéis | ✅ | ✅ | ✅ |
| Perfil da conta, chave e senha próprias | ✅ | — (é a própria conta) | ✅ |
| Configurações da empresa | ✅ | ✅ | ✅ |
| Auditoria | ✅ | ✅ | ✅ |

Legenda: ✅ no ar · 🚧 planejado, com a fase em que entra.

---

## Tecnologias

### Backend

| Tecnologia | Versão | Para quê |
| --- | --- | --- |
| PHP | 8.3 | Runtime da aplicação |
| Laravel | 13 | Framework: rotas, Eloquent, autenticação, sessões, seeders |
| MySQL | 8 | Banco relacional, InnoDB obrigatório por chave estrangeira e transação |
| PHPUnit | 12 | Testes unitários e de feature contra o MySQL real |

### Frontend

| Tecnologia | Versão | Para quê |
| --- | --- | --- |
| AdminLTE | 4.10 | Estrutura do painel (sidebar, navbar, cards) |
| Bootstrap | 5.3 | Grade, formulários e componentes |
| FontAwesome | 6.7 | Ícones |
| jQuery | 3.7 | Base UMD que Toastr e Summernote esperam |
| SweetAlert2 | 11 | Confirmação, informação e entrada de texto no lugar dos diálogos nativos |
| Toastr | 2.1 | Avisos efêmeros de canto, com HTML escapado |
| Summernote | 0.9 | Editor de texto rico do relato do chamado e da resposta da conversa |
| FullCalendar | 6.1 | Calendário da agenda: mês, semana, dia e lista sobre o mesmo feed JSON, carregado só na tela da agenda |
| Vite | 8 | Build e dev server |
| Fontaine | 0.8 | Métricas de fonte para evitar troca de layout |

### Camada própria

| Nome | Para quê |
| --- | --- |
| `FluxoDeOrdem` | A escrita da ordem: número sequencial com a passagem de abertura, travessia com carimbo e sino, e o apagar que só vale para rascunho — a transação é daqui, não do controller |
| `RegistroDePresenca` | A escrita do check-in de campo: a visita nasce do relógio do servidor, a distância se mede deste lado e a chegada abre a execução pelo fluxo |
| `FluxoDeChamado` | A escrita do chamado: protocolo sequencial da empresa com a passagem de origem na mesma transação, travessia com carimbo e sino, e a carteira de quem escreve aplicada sobre o formulário |
| `ConversaDeChamado` | A escrita da nota: corpo sanitizado antes de virar byte, marca de interna só para quem responde pelo chamado, e resposta barrada depois que a conversa terminou |
| `LancamentoDeEstoque` | A escrita do estoque: os dois saldos travados na mesma transação, nenhum saldo negativo, e livro-caixa que não se edita nem se apaga |
| `LancamentoDeConta` | A escrita da conta: ela nasce em aberto porque ninguém digita o que se deriva, a cobrança da ordem vale o que as linhas somam no banco, e ordem e cliente têm de ser da mesma carteira |
| `RegistroDePagamento` | A escrita do dinheiro: a linha é travada antes de somar, o estado volta da soma do caixa, a data do fato é a do último pagamento, e acima do saldo não entra |
| `AgendamentoDeCompromisso` | A escrita da agenda: a janela nasce agendada porque estado não se digita, o cliente vem daquilo que o compromisso prende, o arraste preserva a duração gravada e o dia inteiro se desloca por dias inteiros |
| `Recusa` | A porta pela qual o domínio diz não: salto que o fluxo não tem, ou passo sem permissão, volta como mensagem na tela (`erro` ou `aviso`) em vez de 500 |
| `PermissionCatalog` | Módulos e ações de permissão em um único lugar, lidos por seeder, menu e middleware |
| `Roles` | Os cinco papéis de sistema e seus rótulos em português, para o seeder real e o de demonstração não divergirem |
| `TenantContext` / `CompanyScope` / `ResolveCompany` | Isolamento por empresa em toda query |
| `DashboardMetrics` | As contagens do painel, todas em SQL contra a empresa logada |
| `FichaHistorico` | A régua de leitura da ficha: as linhas mais recentes de um domínio, as janelas de agenda que ainda vêm com a última que passou, e a trilha gravada sobre aquela linha — cliente e técnico medidos pela mesma mão |
| `StatusCatalog` / `Formatters` | Estados e formatações (dinheiro, decimal, data, hora, duração e unidade) num único lugar |
| `ListFilters` | Busca, filtro por coluna, ordenação e por-página lidos do query string |
| `Export` | CSV com BOM e separador `;`, escrito a partir da mesma consulta da tela |
| `Distancia` | Haversine em metros, calculado no servidor: coordenada que falta devolve `null`, e `null` não é zero |
| `TextoSeguro` | Lista fechada de tags, atributos e esquemas de link: o HTML do editor sai seguro antes de virar byte no banco |
| `Auditor` / `Auditable` | Trilha de auditoria: criar, alterar e excluir são gravados pelo trait em `audit_logs` sem o controller lembrar, e a ação de negócio que não é CRUD (aprovar ordem, montar quadro, mover estado) entra escrita à mão, com o verbo certo |
| `Notifier` | O sino da barra e a bandeja da conta: o ato de negócio avisa quem tem de mexer, com tipo do vocabulário fechado e link para a ficha |
| `Settings` / `SettingsCatalog` | As escolhas da empresa declaradas uma vez: a chave é de quem escreve e o padrão é de quem declara — catálogo que nada lê é enfeite |
| `Relatorio` | Os fechamentos de período somados no MySQL com as mesmas expressões que mandam nos módulos: o dinheiro pago, o total da ordem e o prazo da prioridade |
| `MotivoErro` | O critério único das cinco páginas de erro: mostra o motivo que nós escrevemos e guarda o texto que o framework inventa sobre a casa por dentro |
| `TemEnderecos` | Endereços polimórficos e o endereço principal de um cadastro |
| `EmEdicao` / `CuidaDeEnderecos` / `TrataRegistrosAninhados` | Edição em linha na própria ficha, o ciclo de vida do endereço aninhado e a conferência de que a peça pertence mesmo ao cadastro aberto — id chutado em outra linha da mesma empresa responde 404 |
| `Navigation` | Menu montado por permissão e rota existente |
| `Nf` (`theme`, `toast`, `confirm`, `forms`, `flash`, `passwords`, `editor`) | Únicos caminhos permitidos para tema, aviso, diálogo e texto rico na tela |

---

## Estrutura de pastas

```text
nexusfield/
├── app/
│   ├── Http/
│   │   ├── Controllers/     → Welcome, Auth, Dashboard, Clients, Technicians, Catalog, Orders (com o
│   │   │                      CheckinController das visitas de campo), Tickets, Agenda, Stock, Finance
│   │   │                      (lançamento e pagamento) e os Concerns compartilhados
│   │   └── Middleware/      → ResolveCompany (tenancy) e EnsurePermission (autorização)
│   ├── Models/              → 30 modelos do domínio: empresa e plano, usuário e RBAC, cliente com
│   │                          contato e endereço, técnico, equipe e especialidade, catálogo, ordem
│   │                          com linha/quadro/check-in/histórico, chamado com conversa e histórico,
│   │                          compromisso de agenda, movimentação e carga de técnico, lançamento com
│   │                          pagamento, e as tabelas que ainda só têm schema — configuração,
│   │                          notificação, auditoria e anexo
│   ├── Services/            → FluxoDeOrdem, RegistroDePresenca, FluxoDeChamado,
│   │                          ConversaDeChamado, LancamentoDeEstoque, LancamentoDeConta,
│   │                          RegistroDePagamento, AgendamentoDeCompromisso e Recusa: a
│   │                          escrita de cada módulo, entre a tela e o banco
│   └── Support/             → PermissionCatalog, Roles, TenantContext, StatusCatalog, Formatters,
│                              ListFilters, DashboardMetrics, Export, Auditor, TextoSeguro,
│                              Distancia, Navigation, Notifier, Settings, SettingsCatalog,
│                              Relatorio, MotivoErro e FichaHistorico
├── bootstrap/               → inicialização e registro de rotas
├── config/                  → banco, sessão, filesystem, temas
├── database/
│   ├── migrations/          → 21 migrations do schema nexusfield
│   └── seeders/             → DatabaseSeeder (plano, empresa, RBAC, conta raiz) e DemoSeeder
├── docs/
│   ├── branding/            → o medalhão e a arte completa da marca oficial
│   └── screenshots/         → as seis capturas das telas
├── lang/pt_BR/              → validação e mensagens de senha em português
├── public/                  → index.php, favicon, img/ com a marca e assets compilados
├── resources/
│   ├── css/nexusfield/      → tokens.css, base.css, components.css, listings.css, agenda.css, public.css
│   ├── js/nexusfield/       → theme, notify, dialog, confirm, forms, flash, passwords, listas, editor,
│   │                          agenda, checkin, jquery
│   └── views/               → Blade: componentes ui/ e layouts, páginas públicas, de entrada,
│                              de clientes, de técnicos, de equipes, de especialidades, de serviços,
│                              de produtos, de categorias, de ordens de serviço, de chamados, de agenda,
│                              de visitas de campo, de movimentações de estoque e de contas do financeiro
├── routes/                  → web.php
├── storage/                 → logs, cache e uploads (fora da raiz pública)
├── tests/
│   ├── Feature/             → entrada e recuperação, gate de permissão por papel, tenancy, layout
│   │                          autenticado, as três telas abertas de acesso, campo de senha, painel,
│   │                          demonstração, conta raiz e a troca do e-mail dela, clientes, técnicos,
│   │                          catálogo, ordens de serviço, chamados, agenda, check-in de campo,
│   │                          estoque em livro-caixa, financeiro com estado derivado do dinheiro, o
│   │                          token CSRF em todo formulário de escrita e a proibição dos diálogos
│   │                          nativos
│   └── Unit/                → paleta dos dois temas, contrato das capturas, iniciais do usuário
└── CHANGELOG.md             → histórico por fase
```

---

## Arquitetura

```text
┌──────────────────────────────────────────────────────────┐
│                    1. CAMADA DE FRONT-END                │
├──────────────────────────────────────────────────────────┤
│  Navegador Web                                           │
│                                                          │
│  • AdminLTE 4                                            │
│  • Bootstrap 5.3                                         │
│  • Camada de personalização Nf                           │
│  • Tema claro e escuro                                   │
│  • SweetAlert2                                           │
│  • Toastr                                                │
└────────────────────────────┬─────────────────────────────┘
                             │
                             │ HTTPS
                             │ Sessão armazenada no banco
                             │ Proteção CSRF
                             ▼
┌──────────────────────────────────────────────────────────┐
│                  2. CAMADA DE APLICAÇÃO                  │
├──────────────────────────────────────────────────────────┤
│  Laravel 13                                              │
│                                                          │
│  Fluxo de requisição:                                    │
│                                                          │
│  Rotas Web                                               │
│      ↓                                                   │
│  Middlewares                                             │
│      ↓                                                   │
│  Autenticação (Auth)                                     │
│      ↓                                                   │
│  Identificação da empresa (Company)                      │
│      ↓                                                   │
│  Autorização por permissões (Permission)                 │
│      ↓                                                   │
│  Controllers enxutos                                     │
│      ↓                                                   │
│  Camada de Serviços                                      │
│      ↓                                                   │
│  Models Eloquent + camada Support                        │
│      ↓                                                   │
│  Regras de negócio e persistência                        │
└────────────────────────────┬─────────────────────────────┘
                             │
                             │ Eloquent ORM
                             │ Consultas isoladas por empresa
                             ▼
┌──────────────────────────────────────────────────────────┐
│                    3. CAMADA DE DADOS                    │
├──────────────────────────────────────────────────────────┤
│  MySQL 8                                                 │
│  • InnoDB                                                │
│  • utf8mb4                                               │
│                                                          │
│  ESTRUTURA FUNCIONAL                                     │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │ EMPRESAS E CONTROLE DE ACESSO                      │  │
│  │ companies ─ users ─ roles ─ permissions            │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │ CLIENTES                                           │  │
│  │ clients ─ contacts ─ addresses                     │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │ EQUIPE TÉCNICA                                     │  │
│  │ technicians ─ teams ─ specialties                  │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │ CATÁLOGO                                           │  │
│  │ services ─ categories ─ products                   │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │ OPERAÇÕES                                          │  │
│  │ orders ─ tickets ─ appointments                    │  │
│  │ check-in                                           │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │ GESTÃO INTERNA                                     │  │
│  │ estoque ─ financeiro                               │  │
│  │ settings ─ notifications ─ auditoria               │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

A regra de dependência é uma só: tela nenhuma decide autorização. O middleware `permission` responde
403 antes de a view ser renderizada, e `CompanyScope` limita toda leitura à empresa do request.

A camada de escrita é o degrau do meio: `app/Services` é dono da transação, do nascimento do
documento, do carimbo de estado e do sino que toca depois, e ao controller sobra quem pode e o que
mostrar. Onde o domínio diz não, a resposta volta como `Recusa` e a tela a devolve em 302 com o
motivo escrito para quem leu — nunca como a exceção na cara do visitante.

---

## Banco de dados

**MySQL 8+**, schema `nexusfield`, engine InnoDB obrigatória (chave estrangeira e transação), charset
`utf8mb4` / `utf8mb4_unicode_ci`.

O schema é versionado em 21 migrations (`database/migrations/`) e cobre o domínio inteiro: planos e
empresas, usuários e RBAC, clientes e endereços, técnicos e equipes, catálogo de serviços e produtos,
ordens de serviço, estoque, check-in, chamados, agenda, financeiro, configurações, notificações,
auditoria e anexos.

```bash
php artisan migrate        # aplica o schema
php artisan db:seed        # plano, empresa, catálogo de permissões, papéis e a conta raiz
php artisan db:seed --class=DemoSeeder   # dados de demonstração, em empresa à parte
```

`db:seed` não é opcional: sem ele não há permissão cadastrada nem papel atribuído, e o login cai em
uma conta sem autorização nenhuma.

---

## Instalação

### Pré-requisitos

- PHP 8.3 com `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `zip`, `gd` e `intl`
- Composer 2
- Node.js 20+ e npm
- MySQL 8.x escutando em `127.0.0.1:3306`

No WAMP, use o PHP que o Apache carrega (`C:\wamp64\bin\php\php8.3.28\php.exe`). O `php` do `PATH`
pode ser um build reduzido, sem `mbstring` nem `openssl`, e faz Composer e Artisan falharem com erros
desconectados do problema real.

### Passo a passo

```bash
# 1. Clone o repositório
git clone https://github.com/claytonmarcelo/Nexus-Field.git
cd Nexus-Field

# 2. Instale as dependências e prepare o .env
composer install
cp .env.example .env
php artisan key:generate

# 3. Crie o schema no MySQL
mysql -u root -p -e "CREATE DATABASE nexusfield CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Ajuste as credenciais do banco e as chaves de credencial no .env (veja Configuração abaixo)

# 5. Aplique o schema, semeie o catálogo e monte a interface
php artisan migrate
php artisan db:seed
npm install
npm run build

# 6. Suba o servidor
php artisan serve
```

A aplicação sobe em `http://localhost:8000`.

### Atalho

O comando abaixo executa, nesta ordem, `composer install`, cópia de `.env.example` para `.env`,
`key:generate`, `migrate`, `db:seed`, `npm install` e `npm run build`:

```bash
composer run setup
```

### Em desenvolvimento

```bash
composer run dev   # servidor, filas e Vite juntos
php artisan serve  # apenas o servidor HTTP
```

### Em produção

O mesmo passo a passo, com os cadeados de produção. Nada aqui depende de navegador: os caches, a
varredura e os selos de ambiente rodam por linha de comando e cron.

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env         # preencha com credenciais reais — nenhum valor de exemplo sobrevive
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force  # só a conta raiz; o DemoSeeder recusa produção e o teste morre nele
npm ci && npm run build
php artisan storage:link     # no Windows o Apache precisa poder criar a junção (ver nota abaixo)
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

- **`.env` de produção**: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`
  (exige HTTPS no proxy), `DB_CONNECTION=mysql` com credencial própria e `MAIL_*` de um SMTP de
  verdade — com `MAIL_MAILER=log` o link de recuperação de senha morre no arquivo de log
- **Cron**: uma linha, e a varredura diária das 07:00 (ordens vencidas, contas a vencer, agenda de
  amanhã) roda pelo relógio do servidor, nunca pelo navegador de alguém:
  `* * * * * cd /caminho/para/o/app && php artisan schedule:run >> /dev/null 2>&1`
- **Fila**: não há worker a subir. Nenhuma job implementa `ShouldQueue` — notificações, e-mails e
  varreduras são síncronos ou agendados —, então `QUEUE_CONNECTION=database` existe só para nada
  se perder se uma job entrar amanhã
- **Rotação de credencial**: `SEED_ADMIN_PASSWORD` é para a primeira semeadura; a senha da raiz se
  troca por dentro da aplicação depois disso, e o segredo nunca volta para o Git
- **Páginas de erro**: 403, 404, 419, 500 e 503 vestem a marca e não vazam stack — o 500 fala do
  lado de dentro da casa, e o 503 (manutenção) é desenhado para viver sem banco, sem sessão e sem
  assets, porque é justamente quando eles caem que ele é chamado
- **Windows/WAMP**: `php artisan storage:link` pode dizer "connected" sem criar nada se o usuário
  não tem privilégio de symlink; a alternativa que funciona é a junção de diretório
  (`New-Item -ItemType Junction -Path public\storage -Target storage\app\public`)

### Contas e acesso

A conta criada pelo `db:seed` é a **conta raiz** do sistema — identidade permanente
`nexusfield.admin@gmail.com`. Ela recebe o catálogo inteiro de permissões e não pode ser excluída,
desativada, remanejada de empresa, ter o e-mail trocado nem perder a condição de raiz: a regra está no
model (`User::booted()`), então vale mesmo para request de administrador. O endereço que a raiz usava
antes (`marcelolimadez@gmail.com`) foi aposentado por medida de segurança; a migration
`2026_10_08_000004_replace_root_account_email.php` renomeia a linha em vez de criar outra — o histórico
de papéis, auditoria e notificações continua na mesma conta — e o seeder recusa semear nele.

A senha é rotacionável: troque o valor no `.env` e rode `php artisan db:seed` de novo, ou altere a
senha por dentro da aplicação. Se a senha contiver `#`, escreva o valor entre aspas no `.env` — sem
aspas o dotenv lê o `#` como início de comentário e a senha chega truncada. Em produção o seeder
recusa senha gerada automaticamente; credencial nunca entra no código nem no Git.

**Não há cadastro por conta própria (`/register`).** As contas deste estágio vêm do seeder: o seeder
padrão cria a conta raiz, e `DemoSeeder` cria as quatro contas de demonstração. O motivo de não abrir
auto-cadastro é de escopo, não de pressa: cada usuário nasce vinculado a uma empresa e a um papel,
então uma tela de cadastro teria que criar a empresa, escolher o plano e nomear o administrador na
mesma operação — decisão comercial, não um campo de formulário. A recuperação de acesso existe para
quem já tem conta e esqueceu a senha.

---

## Configuração

As variáveis de ambiente vivem no `.env`, que **não** é versionado; só o `.env.example` entra no Git,
com as chaves nomeadas e nenhuma credencial preenchida.

| Variável | Para quê |
| --- | --- |
| `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG` | Identidade e modo de execução |
| `APP_LOCALE` | Idioma da aplicação (`pt_BR`) |
| `APP_TIMEZONE` | Fuso da operação (`America/Sao_Paulo`); precisa bater com o `time_zone` do MySQL para as leituras de data do painel e da agenda |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexão MySQL |
| `DB_ENGINE` | Engine das tabelas; deve ser `InnoDB` |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Persistência e expiração de sessão |
| `CACHE_STORE`, `QUEUE_CONNECTION` | Cache e filas |
| `FILESYSTEM_DISK` | Disco padrão de upload |
| `MAIL_*` | Envio de e-mail (recuperação de acesso, notificações) |
| `BCRYPT_ROUNDS` | Custo do hash de senha; em teste usa valor baixo de propósito |
| `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | Credenciais do administrador semeado pela instalação |
| `SEED_DEMO_PASSWORD` | Senha dos usuários da empresa de demonstração (`DemoSeeder`) |

---

## Segurança

| Camada | Como está implementado |
| --- | --- |
| Senha | Hash bcrypt com custo em `BCRYPT_ROUNDS`; nunca em texto claro e nunca ecoada em `old()` |
| Sessão | Guardada em banco, com regeneração do ID no login e `invalidate` + `regenerateToken` no logout |
| CSRF | Token em todo formulário de escrita — inclusive os que não usam o componente compartilhado — e no arrasto do calendário, que envia `X-CSRF-TOKEN`; `CsrfTokenTest` varre as views e recusa formulário POST sem `@csrf` |
| Força bruta | 5 tentativas por e-mail e IP, mais throttle de 10 requests/min na rota de entrada |
| Autorização | `EnsurePermission` no servidor, por permissão do catálogo; a tela não decide nada |
| Texto rico | HTML de editor passa por `TextoSeguro` (lista fechada de tags, atributos e esquemas de URL) antes do banco; sem isso seria XSS estocado |
| Livro-caixa | Movimentação de estoque não tem rota para editar nem apagar; `recorded_at`, `user_id` e `company_id` vêm do servidor e do contexto, nunca do request, e a conta de saldo roda em transação com o produto e a linha de carga travados por `lockForUpdate` sobre o índice único `(technician_id, product_id)` |
| Caixa | Estado de lançamento nunca é digitado: deriva da soma de `payments` lida do banco dentro da transação, com a linha travada por `lockForUpdate`. Pagamento acima do saldo, em conta quitada ou em conta cancelada é recusado; `occurred_at` vem do último pagamento e não do formulário; estornar e cancelar pedem `financial.approve`, cancelamento não apaga dinheiro registrado, e exclusão só alcança conta sem pagamento |
| Posição em campo | O aparelho lê a coordenada, mas quem mede a distância é o servidor, contra o endereço gravado na ordem; latitude/longitude fora de ±90/±180, com mais de sete decimais ou incompletas são recusadas antes de virar linha, e `technician_id`, `checkin_at` e a medida enviados pelo request são ignorados |
| Tenancy | `CompanyScope` global; leitura fora da empresa exige `anyCompany()` explícito |
| Conta raiz | `is_root` não é atribuível por request e a conta raiz resiste a exclusão, desativação, remanejamento e a perder a própria bandeira |
| Diálogos | `alert()`, `confirm()` e `prompt()` nativos vetados e cobertos por teste; SweetAlert2 e Toastr escapam HTML |
| Segredos | `.env` fora do Git; `.env.example` sem valor; nenhuma credencial em código, seed ou teste |

---

## Testes

Os testes de feature usam MySQL (schema `nexusfield_test`, engine InnoDB), então crie o schema uma vez:

```sql
CREATE DATABASE IF NOT EXISTS nexusfield_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Depois:

```bash
php artisan test
```

Hoje são **318 testes / 3050 asserções**, cobrindo login válido e inválido, usuário inativo, assinatura
vencida, throttle, troca de ID de sessão, logout, gate de permissão por papel, reset de senha com token
válido/forgiado/fraco, perfil próprio com chave e senha trocadas só mediante a senha atual, isolamento entre tenants, as três telas abertas de acesso, o contrato do seletor
de tema entre Blade e JavaScript, a paleta dos dois temas calculada até o contraste WCAG — inclusive a
tinta do menu sobre o painel claro — e a marca verde medida do medalhão, o contrato das seis capturas
deste README (existem, estão linkadas e medem 1440×900), a proibição dos diálogos nativos do navegador,
o CRUD de clientes com contatos e endereços, o de técnicos, equipes e especialidades, o do catálogo —
inclusive o saldo central somado das movimentações, a unidade fora do catálogo recusada e a exclusão
vetada quando já existe histórico — e o de ordens de serviço: sequência anual por empresa, estado que só
anda pelo fluxo com carimbo e aprovação, linha que congela o preço, total somado no SQL, quadro de
comissão com passagem preservada, alcance do técnico e da conta de cliente no painel e na listagem, e o
CSV sendo a mesma consulta da tela — e o de chamados: protocolo que não repete o da empresa ao lado,
prioridade que calcula o prazo, atraso medido pelo banco, nota obrigatória para resolver, passo recusado
quando o fluxo não existe, nota interna que não aparece para quem não pode ler, HTML malicioso que chega
inteiro na tela e volta limpo do banco, e o alcance do técnico e da conta de cliente na ficha e na
conversa — e o de agenda: o JSON que só devolve o que a conta alcança, a janela de leitura com teto, o
arraste que grava no banco ou volta ao lugar, o dia inteiro deslocado por dias inteiros, o concluído que
não se move mais, o estado que só anda pelo fluxo e o painel contando o mesmo que o quadro — e o de
check-in: a hora e o técnico que o request tenta forjar e o servidor ignora, a distância medida no servidor
contra o endereço da ordem, a ausência de GPS que não vira zero, a saída que encerra a passagem sem tocar
na ordem, o raio escolhido pela empresa que marca mas não recusa, o alcance de escritório, técnico, cliente
e conta de outra empresa na tela, no CSV e no cartão do painel, e a ficha que só oferece o formulário a quem
pode registrar — e o de estoque: o saldo que é conta de linhas e nunca campo digitado, a carga que não passa
do central e o consumo que não passa da mala devolvidos sem deixar rastro, a data e o autor que o request
tenta forjar e o servidor ignora, o ajuste que é de quem responde pelo inventário, as rotas que não existem
para editar ou apagar linha, o alcance do técnico na própria mala, a ordem que o consumo exige, o aviso que
sai no momento em que o saldo cruza o ponto, o filtro que o CSV devolve no mesmo ponto e o painel contando o
mesmo que a listagem — e o de financeiro: o módulo que não tem rota para marcar conta como paga, conferido
rota por rota, o dinheiro registrado que é a única coisa que move o estado e a data do fato que volta do
último pagamento, o valor, o tipo e a empresa que o request tenta forjar, o pagamento acima do saldo, na
conta quitada e na cancelada, a data futura e a de dois anos atrás, o estorno que é de quem responde pelo
caixa e o id de outra conta que é 404, a conta com dinheiro que não muda de valor nem de tipo nem se cancela
nem se apaga, o cancelamento que pede motivo e a reabertura que deriva o estado de novo, categoria que é
vocabulário do tipo, despesa que não tem cliente, ordem e cliente que têm de ser a mesma carteira, a cobrança
da OS que só existe com o serviço terminado e com o valor somado das linhas, a carteira que filtra no banco e
o CSV devolvendo o mesmo saldo da ficha, e o alcance de gestor, funcionário, técnico e conta de cliente nas
treze rotas da carteira e na cobrança que nasce na ficha da ordem — e o de relatórios: a janela que um link pode
estender até derrubar o banco e volta cortada com aviso, a ponta invertida que é aceita de cabeça para baixo em vez
de virar período vazio, o fechado que soma a mesma consulta da listagem, o CSV que devolve a coleção inteira na
ordem da tela e com o período no nome do arquivo, a coluna pedida por injeção que cai no catálogo em vez de
executar, o link de ordenar pelo nome que ordena nos dois sentidos, a medida inexistente que não vira zero, o
cartão que some quando a conta não lê o módulo e o 403 que nomeia o degrau faltante; e o CSRF, que varre as views
e o HTML servido porque o `VerifyCsrfToken` se isenta durante os testes e nenhum outro teste do projeto veria o
formulário sem token. As cinco portas de erro — 403, 404, 419, 500 e 503 — vestem a casca
da casa sem depuração, contam o motivo que o servidor escolheu contar e deixam do lado de fora
rota pedida, nome de model e stack; e a micro-visualização do painel, que amarra cada traço à contagem
que o banco devolveu no dia, cobra a régua entre o número do cartão e o período do traço, não deixa linha
nascer onde não há série, nem anel nascer onde a ficha não tinha prazo, nem tinta nova entrar na folha dos
componentes. A demonstração também é provada como história: a hora em que cada ordem terminou é a mesma hora
gravada na trilha de estado, na saída da visita de campo e na liberação da comissão do técnico. E a camada de
apresentação tem régua travada: `CoerenciaVisualTest` (11 testes / 146 asserções) varre as 205 classes `nf-`
da folha contra a interface, não deixa o layout partir palavra no meio nem o hífen cortá-la no meio da frase,
conta duas — não três — chamadas para entrar na tela aberta, prova que o botão do menu desenha o próprio estado
em CSS com tinta só dos tokens, e prende a abertura da boas-vindas na faixa que a fecha: seis passos, fora da
coluna do medalhão, desembocando na cobrança.

No Windows, se `php artisan test` falhar ao compilar views com o aviso
`tempnam(): file created in the system's temporary directory`, rode o PHPUnit direto pelo
interpretador — o wrapper `vendor/bin/phpunit` é um `.bat` e herda as restrições de escrita do
`cmd.exe`:

```bash
php vendor/phpunit/phpunit/phpunit
```

---

## Demonstração

Não há demonstração online: o projeto roda localmente. A demonstração de dados é um seeder, grava
tudo numa empresa à parte (`nexusfield-demo`) e recusa produção:

```bash
php artisan db:seed --class=DemoSeeder
```

Os usuários criados são `admin.demo@nexusfield.local` (administrador),
`gestor.demo@nexusfield.local` (supervisor), `campo.demo@nexusfield.local` (técnico) e
`cliente.demo@nexusfield.local` (cliente). A demonstração também enche a carteira: 29 contas (17 receitas e
12 despesas), todas abertas sem data de ocorrência, mudando de estado só pelos 22 pagamentos gravados como
linhas reais em `payments`, com método, data e autor — `recalcularEstado()` depois de
cada um, exatamente como a tela faz. O caixa percorre seis meses: cinco meses de contrato e conta da base,
pagos no dia em que teriam sido pagos, mais o movimento do mês corrente — é isso que dá história ao traço
mensal do painel, sem empurrar a data de um mês para dentro de outro. Há receita quitada, receita meio paga e vencida, despesa em aberto e
despesa vencida, e uma receita cancelada com motivo: a única decisão digitada. Nenhum estado derivado é
escrito à mão. A senha não está neste README nem em lugar nenhum do
repositório: ela vem de `SEED_DEMO_PASSWORD`, que cai para `SEED_ADMIN_PASSWORD` quando não tem
valor; sem nenhum dos dois, o seeder gera uma e mostra no console. Rodar de novo limpa a empresa de
demonstração e regrava — é fixture de tela, não histórico de operação.

---

## Roadmap

### Concluído

- [x] **Fase 1** — Estrutura base e Git
- [x] **Fase 2** — Modelagem e migrations do banco `nexusfield` (InnoDB, FKs em todo o domínio)
- [x] **Fase 3** — Autenticação, autorização por permissão e multi-tenancy
- [x] **Fase 4** — Design system "Premium Gourmet + Technology" sobre AdminLTE 4
- [x] **Fase 5** — Página pública de apresentação
- [x] **Fase 6** — Entrada, recuperação e redefinição de acesso
- [x] **Fase 7** — Layout autenticado com AdminLTE 4 oficial
- [x] **Fase 8** — Indicadores do painel contados no banco e `DemoSeeder` local
- [x] **Fase 8.1** — Conta raiz permanente, protegida no model
- [x] **Fase 8.2** — Refino de cabeçalho e rodapé, chave de tema em pílula e revelar senha
- [x] **Fase 8.3** — Paleta global: fundo preto no escuro, branco neutro no claro, texto acima do AA
- [x] **Fase 9** — Clientes: CRUD, filtros, paginação própria, ficha com contatos e endereços
- [x] **Fase 10** — Técnicos, equipes e especialidades: escala, quadro com histórico de passagem e base de trabalho
- [x] **Fase 11** — Catálogo de serviços, produtos e categorias: preço, duração, SKU e saldo lido das movimentações
- [x] **Fase 12** — Ordens de serviço: sequência anual por empresa, fluxo de estado com carimbo, linhas que congelam o preço, quadro de comissão e total somado no SQL
- [x] **Fase 13** — Chamados: protocolo por empresa e ano, prioridade que calcula o prazo, conversa com nota interna, estado conduzido por `tickets.execute` e HTML do editor limpo no servidor
- [x] **Fase 14** — Agenda (FullCalendar 6): feed JSON por janela com alcance e teto de varredura, CRUD de
  compromisso, estado pelo fluxo, arraste que grava no banco ou volta ao lugar, e a ordem agendada como
  evento somente-leitura
- [x] **Fase 15** — Check-in e check-out com geolocalização: chegada na ficha da ordem e saída na passagem,
  hora/técnico/distância decididos pelo servidor, raio aceito lido das configurações da empresa (marca, não
  recusa), ausência de GPS tratada como medida inexistente, tela `/visitas` com filtros, paginação e CSV, e
  o alcance de escritório, técnico e conta de cliente em cada leitura
- [x] **Fase 16** — Estoque e movimentações: livro-caixa append-only com saldo central somado no SQL,
  cinco tipos com dois donos (central e mala), trava de linha e índice único para dois registros simultâneos
  não inventarem estoque, data e autor decididos pelo servidor, recorte por `stock.adjust`, rota nenhuma para
  editar ou apagar, aviso de reposição na resposta que baixa o saldo, cartão no painel e carga do técnico na
  ficha dele, filtros, paginação própria e CSV da mesma consulta
- [x] **Fase 17** — Financeiro: estado que ninguém digita, porque `status` é a soma dos pagamentos lida do
  banco dentro da transação com a linha travada; registrar dinheiro é `financial.create`, estornar e cancelar
  são `financial.approve`, e não existe rota para marcar conta como paga. Pagamento acima do saldo, em conta
  quitada ou em conta cancelada é recusado; `occurred_at` volta do último pagamento; categoria é vocabulário
  fechado por tipo; despesa não tem cliente; a cobrança da OS recomputa o valor nas linhas do banco, só com o
  serviço terminado e uma vez por ordem; carteira com filtros, totais do recorte, paginação própria e CSV da
  mesma consulta; quatro KPIs de caixa no painel somando a mesma expressão SQL da ficha
- [x] **Fase 18** — Relatórios e exportações: quatro fechamentos de período agregando os mesmos `visiveisPara()`
  das listagens, janela resolvida no servidor com teto de 366 dias e aviso do que foi cortado, ordenação por lista
  fechada em que coluna de injeção cai no catálogo, CSV (`;` + BOM + dinheiro e duração formatados) saindo da
  coleção inteira com o período no nome do arquivo, dois degraus de permissão (`reports.view` para a tela,
  `reports.export` para o arquivo) mais a leitura do módulo que o fechado resume, e cartão que some do hub em vez
  de abrir tela zerada
- [x] **Fase 19** — Notificações: sino por conta com dedup por fato e origem, seis gatilhos em atos de
  negócio reais, varredura diária às 07:00 rodando por contexto de tenant e central com os mesmos
  filtros, ordenação e paginação das listagens
- [x] **Fase 20** — Usuários e papéis: ninguém concede o que não tem, quota do plano barrando antes do
  INSERT, raiz intocável por tela, auto-degradação bloqueada, papel de sistema imutável e
  `nf:papeis:sincronizar` idempotente
- [x] **Fase 21** — Configurações da empresa: perfil com chave de endereço e plano fora de alcance,
  marca validada pixel a pixel sem deixar imagem órfã, cinco preferências com consumo vivo e
  supervisor em modo leitura contra administrador que gere
- [x] **Fase 22** — Auditoria: trilha virou tela com ficha que congela o nome do autor e mostra o
  antes/depois campo a campo, exportação pelo degrau próprio saindo da mesma consulta filtrada, e o
  gravador corrigido na raiz — sem dupla codificação, com as linhas antigas continuando legíveis
- [x] **Perfil da conta** — espelho em `/perfil` com os dados lidos do banco, as próximas janelas da
  agenda do técnico, troca de chave de acesso e renovação de senha ambas exigindo a senha atual,
  sessões demais encerradas na renovação e "Meu perfil" no dropdown do cabeçalho
- [x] **Prontidão de deploy** — caches de configuração, rota, view e evento validados, páginas de erro
  403/404/419/500/503 vestindo a marca sem vazar stack, e o passo a passo de produção documentado
- [x] **Fase 23** — Micro-visualização do painel: série por período e anel de proporção em SVG próprio,
  saído da mesma consulta dos indicadores, sem biblioteca de gráfico, sem tinta nova e sem desenho onde não
  há dado
- [x] **Fase 24** — Camada de apresentação fechada sem exceção: esqueleto no único carregamento real da
  interface, virada de tema com janela de 200ms e a folha varrida — nenhuma classe `nf-` esperando uso
- [x] **Fase 25** — Coerência global sem repetir informação: a folha parou de se desmentir (20 declarações
  mortas cortadas, estilo computado provado idêntico), a agenda ficou com um só caminho de rede, e a
  escrita por arrasto ganhou cara enquanto o banco não responde
- [x] **Fase 26** — Apresentação sem ruído: a boas-vindas ficou com uma porta de entrada só, o botão do menu
  ganhou desenho e estado próprios, e a palavra parou de se partir ao meio — `anywhere` fora das seis folhas,
  `pretty` e `balance` nas linhas que sobravam, provado nó de texto a nó de texto em 1440px e 390px
- [x] **Fase 27** — A abertura fecha em faixa própria: o ciclo desceu do medalhão para uma esteira de seis
  passos que atravessa a página e desemboca na cobrança, o texto ganhou o segundo fôlego (quem está na rua e
  quem fica dentro), e o hífen parou de cortar palavra nas telas abertas. 826px de hero em 1440px com as duas
  colunas a 56px uma da outra, 0 palavras partidas e 0 estouros em cinco larguras
- [x] **Camada de Serviços** — a escrita dos domínios de operação saiu do controller: `FluxoDeOrdem` e
  `RegistroDePresenca` nas ordens e na chegada de campo, `FluxoDeChamado` e `ConversaDeChamado` nos chamados,
  `LancamentoDeEstoque` no estoque, `LancamentoDeConta` e `RegistroDePagamento` no financeiro,
  `AgendamentoDeCompromisso` na agenda. Transação, trava de saldo, estado derivado, carimbo de auditoria e sino
  têm um dono só; à tela ficam o alcance de leitura, a regra de campo e o idioma da resposta
- [x] **Fichas com histórico** — a ficha de cliente e a de técnico pararam de ser formulário com resumo:
  `FichaHistorico` é a régua compartilhada de leitura (as seis linhas mais recentes de um domínio, as
  quatro janelas que ainda vêm mais a última que passou, e a trilha da própria linha), cada número foi
  morar no cartão do domínio que o possui, e toda leitura passa por `visiveisPara()` antes de virar
  linha — inclusive o `DB::table` que soma os serviços cobrados

### A seguir

O plano de reconstrução fechou: da fase 1 à 27, mais a prontidão de deploy e a camada de serviços,
tudo no ar. O que vem depois é decisão de operação, não fase: publicar numa máquina real, contratar o
primeiro tenant e bater o martelo da licença.

---

## Histórico de versões

O detalhamento por fase está em [CHANGELOG.md](CHANGELOG.md). O baseline desta reconstrução é
`0.1.0`, e commits seguem a mesma regra: um por etapa funcional validada, em português brasileiro,
dizendo o que mudou e por quê.

---

## Desenvolvedor

<table>
  <tr>
    <td width="90" align="center">
      <img src="https://github.com/claytonmarcelo.png" width="90" alt="Clayton Marcelo">
    </td>
    <td>
      <strong>Clayton Marcelo</strong><br>
      Full stack — Laravel, MySQL, JavaScript<br><br>
      <a href="https://github.com/claytonmarcelo"><img src="https://img.shields.io/badge/GitHub-claytonmarcelo-181717?style=flat-square&logo=github" alt="GitHub"></a>
      <a href="https://www.linkedin.com/in/clayton-marcelo-dev/"><img src="https://img.shields.io/badge/LinkedIn-clayton--marcelo--dev-0A66C2?style=flat-square&logo=linkedin&logoColor=white" alt="LinkedIn"></a>
      <a href="https://www.youtube.com/@c.marcelodev.brasil"><img src="https://img.shields.io/badge/YouTube-C.%20Marcelo%20Dev.%20Brasil-FF0000?style=flat-square&logo=youtube&logoColor=white" alt="Canal C. Marcelo Dev. Brasil no YouTube"></a>
    </td>
  </tr>
</table>

---

<p align="center">
  <sub>NEXUS-FIELD · Clayton Marcelo · 2026</sub>
</p>
