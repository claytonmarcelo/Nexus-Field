# NEXUS-FIELD

Plataforma de Field Service Management (FSM) para gestão de operações de serviço em campo.

## Telas

Capturas do aplicativo rodando (Laravel + AdminLTE 4 sobre MySQL), nos dois temas e em celular.
Os painéis mostram a empresa de demonstração criada pelo `DemoSeeder` — é ela que tem ordens,
chamados, financeiro e estoque para os indicadores calcularem; na empresa real sem dados, os
mesmos blocos aparecem nos estados vazios. As telas de gestão (clientes, ordens, chamados, agenda)
chegam nas fases seguintes.

<table>
  <tr>
    <td width="50%"><a href="docs/screenshots/01-boas-vindas.png"><img src="docs/screenshots/01-boas-vindas.png" alt="Página de apresentação pública do NEXUS-FIELD em tema claro, com o ciclo de um serviço ao lado do título"></a><br><sub>Apresentação pública · tema claro · 1440×900</sub></td>
    <td width="50%"><a href="docs/screenshots/02-entrada.png"><img src="docs/screenshots/02-entrada.png" alt="Tela de entrada com e-mail, senha, manter conectado e recuperação de acesso em tema escuro"></a><br><sub>Entrada · tema escuro · 1440×900</sub></td>
  </tr>
  <tr>
    <td width="50%"><a href="docs/screenshots/03-recuperar-acesso.png"><img src="docs/screenshots/03-recuperar-acesso.png" alt="Tela de recuperação de acesso pedindo o e-mail da conta em tema claro"></a><br><sub>Recuperação de acesso · tema claro · 1440×900</sub></td>
    <td width="50%"><a href="docs/screenshots/06-painel-celular.png"><img src="docs/screenshots/06-painel-celular.png" alt="Painel com os indicadores empilhados em uma coluna num celular de 390px em tema escuro"></a><br><sub>Painel no celular · tema escuro · 390×844</sub></td>
  </tr>
  <tr>
    <td colspan="2"><a href="docs/screenshots/04-painel-claro.png"><img src="docs/screenshots/04-painel-claro.png" alt="Painel em tema claro com doze indicadores, a fila de ordens da semana e a distribuição por estado"></a><br><sub>Painel operacional · tema claro · 1440×1000 — KPIs, fila de ordens e distribuição por estado, todos contados no MySQL desta empresa</sub></td>
  </tr>
  <tr>
    <td colspan="2"><a href="docs/screenshots/05-painel-escuro.png"><img src="docs/screenshots/05-painel-escuro.png" alt="Painel em tema escuro com os mesmos indicadores, tabelas e distribuição por estado"></a><br><sub>Painel operacional · tema escuro · 1440×1000 — o mesmo painel sobre a noite bonita, sem branco nem preto puros</sub></td>
  </tr>
</table>

## Requisitos

- PHP 8.3 com as extensões `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `zip`, `gd`
  e `intl`
- Composer 2
- Node.js 20+ e npm
- MySQL 8.x escutando em `127.0.0.1:3306`

No WAMP, use o PHP que o Apache carrega (`C:\wamp64\bin\php\php8.3.28\php.exe`). O `php` do
`PATH` pode ser um build reduzido, sem `mbstring` nem `openssl`, e faz Composer e Artisan
falharem com erros desconectados do problema real.

## Instalação

O atalho abaixo executa, nesta ordem, `composer install`, cópia de `.env.example` para `.env`,
`key:generate`, `migrate`, `db:seed`, `npm install` e `npm run build`:

```bash
composer run setup
```

Para fazer passo a passo:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Crie o banco e ajuste em `.env` as credenciais do seu MySQL:

```sql
CREATE DATABASE nexusfield CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nexusfield
DB_USERNAME=root
DB_PASSWORD=
DB_ENGINE=InnoDB
```

`DB_ENGINE=InnoDB` não é opcional aqui: o projeto precisa de chave estrangeira e transação, e
há servidores MySQL com MyISAM como engine padrão.

```bash
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan serve
```

`db:seed` faz parte da instalação, não é opcional: é ele que cria o catálogo de permissões, os
cinco papéis de sistema e o administrador da sua empresa. Sem ele não há quem possa autorizar
nada, e o login cai em uma conta sem papel.

Defina a senha antes de semear:

```env
SEED_ADMIN_EMAIL=marcelolimadez@gmail.com
SEED_ADMIN_PASSWORD=a-senha-da-conta-raiz
```

Essa é a **conta raiz** do sistema: ela recebe o catálogo inteiro de permissões e não pode ser
excluída, desativada, remanejada de empresa nem ter o e-mail trocado — a regra está no model
(`User::booted()`), então vale mesmo para request de administrador. A senha, essa sim, é
rotacionável: troque o valor no `.env` e rode `php artisan db:seed` de novo, ou altere a senha por
dentro da aplicação. Em produção o seeder recusa senha gerada automaticamente; credencial nunca
entra no código nem no Git.

A aplicação sobe em `http://localhost:8000`.

## Executando em ambiente de desenvolvimento

```bash
composer run dev
```

Sobe servidor, filas e Vite juntos. Para apenas o servidor HTTP:

```bash
php artisan serve
```

Para ver o painel com dados, sem mexer na sua empresa, use a demonstração. Ela grava tudo numa
empresa à parte (`nexusfield-demo`) e recusa produção:

```bash
php artisan db:seed --class=DemoSeeder
```

Os usuários criados são `admin.demo@nexusfield.local` (administrador),
`gestor.demo@nexusfield.local` (supervisor), `campo.demo@nexusfield.local` (técnico) e
`cliente.demo@nexusfield.local` (cliente), todos na empresa de demonstração. A senha vem de
`SEED_DEMO_PASSWORD`, que cai para `SEED_ADMIN_PASSWORD` quando não tem valor; sem nenhum dos dois,
o seeder gera uma e mostra no console. Rodar de novo limpa a empresa de demonstração e regrava; é
fixture de tela, não histórico de operação.

## Testes

Os testes de feature usam MySQL (schema `nexusfield_test`, engine InnoDB), então crie o schema
uma vez:

```sql
CREATE DATABASE IF NOT EXISTS nexusfield_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Depois:

```bash
php artisan test
```

No Windows, se `php artisan test` falhar ao compilar views com o aviso
`tempnam(): file created in the system's temporary directory`, rode o PHPUnit direto pelo
interpretador — o wrapper `vendor/bin/phpunit` é um `.bat` e herda as restrições de escrita do
`cmd.exe`:

```bash
php vendor/phpunit/phpunit/phpunit
```

## Estrutura

```
app/            código da aplicação (controllers, models, services, policies)
bootstrap/      inicialização e registro de rotas
config/         configuração (banco, sessão, filesystem)
database/       migrations, seeders e factories
public/         point de entrada (index.php) e assets compilados
resources/      views Blade, CSS e JS antes do build
routes/         rotas web e de API
storage/        logs, cache e arquivos enviados
tests/          testes unitários e de feature
```

## Variáveis de ambiente

| Variável | Uso |
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

`.env` contém segredos e **não** é versionado. Só `.env.example` entra no Git.

## Segurança

- Senhas com hash (bcrypt, custo definido em `BCRYPT_ROUNDS`); nunca em texto claro.
- Sessão em banco, com regeneração de identificador no login.
- CSRF habilitado em todo formulário POST.
- Autorização por permissão verificada no backend, não apenas escondendo controles no front.
- Conta raiz protegida no model: não se exclui, desativa nem remaneja, e `is_root` não é
  atribuível por request. A credencial dela fica no `.env`, nunca no código nem no Git.
- Uploads validados por tipo e tamanho, gravados fora da raiz pública.

## Git

Commits humanizados em português brasileiro, um por etapa funcional validada.

## Autor

Clayton Marcelo — 2026.
