# Integrações de IA, agentes e APIs

Este registro fecha a pesquisa de ferramentas coerentes com o NEXUS-FIELD. O critério não foi
"instalar tudo que aparecesse na busca": foi instalar o que tem **dono claro no domínio** — FSM
multiempresa, RBAC, check-in geolocalizado, financeiro e relatórios — e deixar fora o que exigiria
conta, credencial ou decisão de produto que o projeto ainda não tomou.

## O que entrou

| Ferramenta | Versão | Por que faz sentido aqui |
| --- | --- | --- |
| `laravel/boost` | 2.10.4 | Fecha o setup do agente de código: 8 guidelines, 4 skills (`infer-conventions`, `deploying-to-cloud`, `laravel-best-practices`, `testing-best-practices`) e MCP server em `.claude/`, `CLAUDE.md`, `boost.json` e `.mcp.json`. |
| `laravel/mcp` | 1.2.1 | Base de MCP client/server; é pré-requisito dos MCP tools do AI SDK e já veio junto do Boost. |
| `laravel/ai` | `1.x-dev` (commit `2429141` no lock) | SDK oficial de LLMs, agents, tools, structured output, skills, sub-agents, embeddings e classificação. Ainda não há release estável, por isso a dependência é explicitamente dev enquanto o restante do projeto permanece em `minimum-stability: stable`. |
| `maatwebsite/excel` | 4.0.7 | Exportação `.xlsx` para os fechamentos que hoje saem só em CSV; a regra da casa continua valendo: arquivo gerado pela mesma consulta da tela. |
| `barryvdh/laravel-dompdf` | 3.1.2 | OS, relatório e comprovante em PDF, com o mesmo dado que a ficha já mostra. |
| `league/flysystem-aws-s3-v3` | 3.35.3 | Disco `s3` do Flysystem para marca da empresa e anexos; também traz o AWS SDK usado pelo provedor Bedrock do AI SDK. |

Arquivos publicados: `config/ai.php`, `config/excel.php`, `config/dompdf.php`, os stubs de
`agent`/`tool` em `stubs/`, e a migration
`2026_11_21_133225_create_agent_conversations_table.php` (`agent_conversations` e
`agent_conversation_messages`), já aplicada no banco local.

## Como habilitar

1. Copie as chaves necessárias do `.env.example` para o `.env`. Nenhuma chave de API vive no
   repositório. O provedor padrão em `config/ai.php` é `openai`; troque `default` (e os defaults de
   imagem/áudio/embedding, se usar) para o provedor contratado.
2. Para storage remoto, defina `FILESYSTEM_DISK=s3` e preencha `AWS_BUCKET`, `AWS_ACCESS_KEY_ID`,
   `AWS_SECRET_ACCESS_KEY` e `AWS_DEFAULT_REGION`. `AWS_ENDPOINT` atende R2/MinIO.
3. Para criar um agente: `php artisan make:agent ResumidorDeChamado`. O contrato é
   `Laravel\Ai\Contracts\Agent`; `prompt()`, `stream()` e saída estruturada saem da caixa.
4. Exportações em Excel e PDF devem reaproveitar o método `consulta()` da listagem correspondente —
   nunca montar uma segunda seleção só para o arquivo.

### Regra de tenant para IA

As tabelas de conversa do AI SDK identificam o participante por `participant_type` + `participant_id`,
mas não têm `company_id`. Em um agente que lê chamados, ordens ou financeiro, a empresa deve vir do
`TenantContext` e a conversa só pode ser continuada depois de conferir que o participante pertence à
empresa da request. A SDK não adivinha o isolamento; a aplicação continua responsável por ele.

## O que foi encontrado e ficou de fora

| Candidato | Motivo da não instalação |
| --- | --- |
| `laravel/socialite`, OAuth/SAML, MFA | Exige escolha de provedor, registro de aplicação e decisão de quais papéis podem autenticar externamente. Hoje o login é local e auditado; trocar isso sem desenho é abrir superfície de acesso. |
| `laravel/sanctum` | `bootstrap/app.php` já trata `api/*` como JSON, mas `routes/api.php` não existe. Tokens sem endpoint são superfície morta; entra quando a API pública for desenhada. |
| `laravel/scout` + Meilisearch/Typesense | As listagens e o painel já contam no MySQL e respeitam `CompanyScope`. Busca full-text pede infraestrutura, política de reindexação e regras de tenant por índice. |
| SDKs de SMS/WhatsApp/push | Dependem de contrato com operadora, consentimento e preferência do usuário; `Notifier` já resolve o sino interno sem custo nem dados fora da empresa. |
| Gateways de PIX/cartão e assinaturas | O módulo financeiro é um razão interno; cobrança real pede conta de comerciante, webhooks, reconciliação e tratamento de estorno — escopo de produto, não de dependência. |
| ViaCEP/Receita Federal | Não há pacote necessário: `Http` do Laravel resolve quando a tela de endereço pedir. Instalar SDK agora seria peso sem uso. |
| Telescope/Sentry | `laravel/pail` já cobre log local em desenvolvimento; observabilidade remota entra com plano e retenção definidos. |

## Fontes consultadas

- Documentação oficial do Laravel AI SDK: <https://laravel.com/docs/13.x/ai-sdk>
- Packagist: <https://packagist.org/packages/laravel/ai>, `laravel/mcp`, `maatwebsite/excel`,
  `barryvdh/laravel-dompdf`, `league/flysystem-aws-s3-v3`
- Laravel Boost: <https://laravel.com/docs/boost>
