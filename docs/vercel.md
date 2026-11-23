# Deploy global na Vercel — NEXUS-FIELD

Data da revisão: 21/11/2026.

## Por que Container Runtime

A Vercel não oferece runtime PHP nativo. O projeto usa `framework: container` em
`vercel.json` e uma imagem OCI única com nginx + PHP-FPM:

- `Dockerfile`: PHP 8.4/Alpine com as extensões exigidas por Laravel, DomPDF e
  PhpSpreadsheet; dependências de produção e assets Vite compilados no build.
- `docker/nginx/nginx.conf` e `docker/nginx/default.conf`: PID em
  `/tmp/nexusfield/nginx.pid`, porta `$PORT` (padrão 80), cabeçalhos de
  segurança e bloqueio de `.env`, `.git` e arquivos de cache.
- `docker/php/www.conf`: variáveis de ambiente repassadas aos workers
  (`clear_env = no`) — sem isso, `APP_KEY` não chega ao PHP-FPM e o login quebra.
- `docker/entrypoint.sh`: falha cedo se `APP_KEY` ou `APP_URL` estiverem ausentes,
  cria os diretórios graváveis em `/tmp`, inicia o FPM com
  `php-fpm -D -g /tmp/nexusfield/php-fpm.pid` e aplica migrações apenas quando
  `RUN_MIGRATIONS=true` (remover a variável depois).
- Região: `gru1` (São Paulo). Multiple regions não são usadas porque o banco é
  externo e único; replicar o container não replicaria os dados. Cron diário:
  `0 10 * * *` (10:00 UTC = 07:00 de São Paulo), com GET em `/cron/schedule`.

O filesystem da imagem é somente leitura. Todo estado gravável vive em
`/tmp/nexusfield/storage` (`APP_STORAGE_PATH`); sessão, cache e fila ficam no
banco externo. Sem credenciais S3, use `FILESYSTEM_DISK=local` (os uploads vivem
em `/tmp` e não sobrevivem a redeploys); com `AWS_*` configurados, prefira
`FILESYSTEM_DISK=s3`.

## Variáveis de ambiente — somente os NOMES

Defina no projeto da Vercel (Production e, se desejar, Preview). **Nenhum valor
deve ser escrito em arquivo versionado, teste, documentação, log ou commit.**

| Variável | Finalidade |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Ambiente, modo e URL HTTPS pública |
| `APP_KEY` | Chave de criptografia; gere com `php artisan key:generate` |
| `APP_STORAGE_PATH` | Já definida na imagem como `/tmp/nexusfield/storage` |
| `APP_MAINTENANCE_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | Use `cache`/`database`/`database`/`database` |
| `SESSION_SECURE_COOKIE` | `true` em produção; sem HTTPS o cookie não volta |
| `TRUSTED_PROXIES` | TLS termina no edge; use `REMOTE_ADDR` |
| `TRUSTED_HOSTS` | Expressões **regulares** PCRE, não nomes de domínio: `^.*\.vercel\.app$` (o host de `APP_URL` já é confiável) |
| `DB_CONNECTION`, `DB_URL` | `pgsql`; `DB_URL` ou, na integração Neon da Vercel, `DB_POSTGRES_URL` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `smtp` ou `resend`; `log` apenas local |
| `FILESYSTEM_DISK`, `AWS_*` | Disk S3 para uploads; obrigatório em produção |
| `CRON_SECRET` | Segredo do header `Authorization: Bearer` do cron |
| `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | Conta raiz criada pelo seeder; defina apenas no ambiente de seed |

`MAIL_MAILER=log` **não** é aceitável em produção: o link de recuperação ficaria
preso no log e o usuário nunca receberia o e-mail. A conta raiz não nasce com
senha no código: o seeder lê `SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD` do
ambiente e recusa semear em produção sem senha definida.

## Ordem de operação

1. Criar o projeto na Vercel apontando para este repositório. O `vercel.json`
   define o preset, as regiões e o cron; não use build preset PHP.
2. Criar o banco Postgres externo (Lakebase/Neon) e configurar `DB_CONNECTION=pgsql`
   mais `DB_URL` — ou usar a integração Neon da Vercel, que injeta
   `DB_POSTGRES_URL`.
3. Aplicar as migrações no banco de produção (`php artisan migrate --force`)
   com as mesmas variáveis de ambiente da aplicação. A Vercel não executa
   migrações automaticamente no build do container.
4. Rodar o seeder uma vez com `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD`
   definidos (`php artisan db:seed --class=DatabaseSeeder --force`). O seeder é
   idempotente e não imprime a senha quando ela vem do ambiente.
5. Configurar o provedor de e-mail transacional e testar "esqueci minha senha"
   com uma conta real antes de liberar o acesso.
6. Confirmar no painel da Vercel que o Cron Job `0 10 * * *` está ativo e que
   `CRON_SECRET` foi definido no mesmo ambiente da aplicação.
7. Validar login da conta raiz, criação de usuário pelo administrador,
   recuperação de senha e carregamento do dashboard em HTTPS.

## Verificação executada neste repositório

- Suíte completa: `453 testes / 4407 asserções` verdes em MySQL de teste.
- `composer audit`: nenhuma vulnerabilidade.
- `npm audit --omit=dev`: 0 vulnerabilidades.
- `git diff --check`: sem erros de whitespace.
- Varredura de 399 arquivos versionados: nenhum BOM e nenhum caractere CJK ou
  invisível.
- Conta raiz: validada localmente com `SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD`
  lidos do `.env`, sem imprimir valores (`USUARIO_EXISTE=true`,
  `SENHA_VALIDA=true`).
- `vercel.json`: JSON válido, `framework=container`, 3 regiões e 1 cron.

## Não verificado

- Build da imagem: `docker` está instalado, mas o daemon não está rodando nesta
  máquina
  (`failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine`).
  O `Dockerfile` foi revisado estaticamente, não compilado.
- Deploy real na Vercel: depende de projeto, domínio e variáveis fornecidas.
- Envio real de e-mail de recuperação: depende de provedor configurado.
- Banco externo de produção e conexão SSL: depende de instância MySQL fornecida.
- Disparo real do cron pela Vercel e renovação de certificado: depende do projeto
  publicado.

Nada acima é declarado como seguro ou funcionando em produção sem a execução
desses passos no ambiente da Vercel.
