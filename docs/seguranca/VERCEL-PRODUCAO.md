# Relatório de segurança — deploy global na Vercel

Data: 21/11/2026. Branch: `seguranca/producao`.

## Escopo

Preparar o NEXUS-FIELD para rodar de forma global na Vercel sem quebrar login,
cadastro e recuperação de senha, mantendo a suíte verde e nenhum segredo
versionado. Cadastro público permanece fechado nesta fase: a criação de usuários
é feita pelo administrador. O auto-cadastro de empresas está enfileirado para o
módulo de organização.

## Fatos e evidências

| Verificação | Comando | Resultado |
| --- | --- | --- |
| Suíte completa | `php artisan test` | 353 testes / 3365 asserções, 0 falhas |
| Auditoria PHP | `composer audit --format=plain` | Nenhuma vulnerabilidade |
| Auditoria JS de produção | `npm audit --omit=dev` | 0 vulnerabilidades |
| Diff | `git diff --check` | Sem erros |
| BOM/CJK/invisíveis | varredura em 399 arquivos versionados | Nenhum |
| Manifesto | leitura de `vercel.json` | JSON válido, `container`, 3 regiões, 1 cron |
| Conta raiz | `Auth::validate` com variáveis de ambiente, saída só booleana | `USUARIO_EXISTE=true`, `SENHA_VALIDA=true` |

Os valores das credenciais não foram impressos, copiados para arquivo nem
commitados. A validação local usou `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD`
lidos do `.env`.

## Commits desta frente

- `520a04d` — inventário de rotas e baseline de segurança.
- `5a11254` — análise competitiva e critérios de diferenciação.
- `617e6f5` — Container Runtime: Dockerfile, nginx, PHP-FPM, entrypoint, cron.
- `6cf6f58` — proxies confiáveis, hosts, cookies `secure`, storage em `/tmp`.
- `2d7e9e8` — cron protegido e recuperação de senha sem enumeração.

Nenhum push foi realizado.

## Controles implementados

1. **Container**: nginx + PHP-FPM, `$PORT`, diretórios graváveis em `/tmp`,
   `APP_KEY`/`APP_URL` obrigatórios no entrypoint, `.env` bloqueado pelo nginx.
2. **HTTPS atrás do edge**: `TrustProxies`, `TrustHosts`, `URL::forceScheme('https')`
   e cookie de sessão `secure` por padrão em produção — sem isso o link de reset
   sai com `http://` e o login some silenciosamente.
3. **Cron**: `GET /cron/schedule` exige `Authorization: Bearer $CRON_SECRET`,
   usa `hash_equals`, falha fecha se o segredo não estiver configurado, limita
   10 chamadas/minuto/IP, registra recusas com IP/navegador/motivo sem segredo e
   só dispara `schedule:run`. Está isento do modo manutenção.
4. **Recuperação de senha**: resposta idêntica para e-mail existente e
   inexistente, sem erro de validação que revele a conta; log com hash SHA-256
   do e-mail, status e IP, nunca o e-mail em claro.
5. **Conta raiz**: o seeder lê e-mail e senha do ambiente, recusa semear em
   produção sem senha e é idempotente; nenhum valor real foi versionado nesta
   frente.

## Riscos residuais / não verificado

- Build do container não executado: o daemon Docker está indisponível nesta
  máquina, embora o CLI exista.
- Deploy real, domínio/HTTPS, banco MySQL externo, provedor de e-mail, S3 e
  disparo do cron pela Vercel dependem de configuração e acesso fornecidos.
- O cadastro público continua fechado nesta fase; a REGRA de "cadastro e
  recuperar senha funcionando" é atendida no sentido de criação de contas pelo
  administrador e recuperação por e-mail transacional configurado. O envio real
  ainda depende do provedor.
- Turnstile/hCaptcha, antivírus e scanners (ZAP/Nuclei) pertencem ao módulo de
  organização e não foram executados aqui.

## Conclusão

A aplicação está pronta para deploy com as variáveis documentadas em
`docs/vercel.md`. O que depende de infraestrutura externa está marcado como
**não verificado**; nada disso foi declarado funcionando em produção.
