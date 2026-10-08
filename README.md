# NEXUS-FIELD

Plataforma de Field Service Management (FSM) para gestão de operações de serviço em campo.

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
`key:generate`, `migrate`, `npm install` e `npm run build`:

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
npm install
npm run build
php artisan serve
```

A aplicação sobe em `http://localhost:8000`.

## Executando em ambiente de desenvolvimento

```bash
composer run dev
```

Sobe servidor, filas e Vite juntos. Para apenas o servidor HTTP:

```bash
php artisan serve
```

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
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexão MySQL |
| `DB_ENGINE` | Engine das tabelas; deve ser `InnoDB` |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Persistência e expiração de sessão |
| `CACHE_STORE`, `QUEUE_CONNECTION` | Cache e filas |
| `FILESYSTEM_DISK` | Disco padrão de upload |
| `MAIL_*` | Envio de e-mail (recuperação de acesso, notificações) |

`.env` contém segredos e **não** é versionado. Só `.env.example` entra no Git.

## Segurança

- Senhas com hash (bcrypt, custo definido em `BCRYPT_ROUNDS`); nunca em texto claro.
- Sessão em banco, com regeneração de identificador no login.
- CSRF habilitado em todo formulário POST.
- Autorização por permissão verificada no backend, não apenas escondendo controles no front.
- Uploads validados por tipo e tamanho, gravados fora da raiz pública.

## Git

Commits humanizados em português brasileiro, um por etapa funcional validada.

## Autor

Clayton Marcelo — 2026.
