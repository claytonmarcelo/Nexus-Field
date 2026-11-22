<?php

namespace App\Services\Cadastro;

use App\Models\LegalAcceptance;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyRegistration;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\CadastroAprovado;
use App\Notifications\CadastroAprovadoContaExistente;
use App\Notifications\CadastroEmAnalise;
use App\Notifications\CadastroReprovado;
use App\Notifications\ConfirmacaoDeCadastro;
use App\Services\BackfillOrganizacional;
use App\Services\Recusa;
use App\Support\Auditor;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dono do cadastro público de empresas (E6).
 *
 * Nenhum controller escreve nestas tabelas: o protocolo, os hashes, as transições
 * e a criação da empresa aprovada vivem aqui, numa transação só. A aprovação
 * reusa `BackfillOrganizacional` e `Roles::provision`, então a empresa nova nasce
 * exatamente como as antigas — sem regra paralela.
 */
final class GestorDeCadastro
{
    /** @var array<string, string> */
    private const MIME_PERMITIDO = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    private const TAMANHO_MAXIMO = 5 * 1024 * 1024;

    private const HORAS_TOKEN_EMAIL = 48;

    private const DIAS_TOKEN_ACESSO = 7;

    /**
     * @param array<string, mixed> $dados
     * @return array{registro: CompanyRegistration, email_token: string, access_token: string}
     */
    public function iniciar(array $dados, ?string $ip, ?string $userAgent, ?User $ator = null): array
    {
        $dados = $this->normalizar($dados);
        $cnpjHash = hash('sha256', (string) $dados['cnpj']);
        $emailHash = hash('sha256', (string) $dados['email']);
        $ipHash = $this->hashIp($ip);

        if (CompanyRegistration::query()->where('cnpj_hash', $cnpjHash)->exists()
            || Company::withTrashed()->where('cnpj', $dados['cnpj'])->exists()) {
            throw new Recusa('Este CNPJ já possui um cadastro na plataforma.');
        }

        $conta = User::withTrashed()->where('email', $dados['email'])->first();

        if ($conta !== null && ! $conta->isRoot() && $ator?->is($conta)) {
            $conta = null; // A própria pessoa está cadastrando a empresa da conta dela.
        }

        if ($conta !== null) {
            throw new Recusa('Este e-mail já possui uma conta na plataforma.');
        }

        if ($ipHash !== null
            && CompanyRegistration::query()->where('ip_hash', $ipHash)
                ->where('created_at', '>=', now()->subHour())->count() >= 3) {
            throw Recusa::aviso('Muitas solicitações neste endereço. Tente novamente mais tarde.');
        }

        $resultado = DB::transaction(function () use ($dados, $cnpjHash, $emailHash, $ipHash, $userAgent): array {
            $emailToken = Str::random(64);
            $accessToken = Str::random(64);

            $registro = CompanyRegistration::create([
                'protocol' => $this->proximoProtocolo(),
                'state' => CompanyRegistration::RASCUNHO,
                'dados' => Arr::except($dados, ['aceite_termos', 'aceite_privacidade']),
                'cnpj_hash' => $cnpjHash,
                'email_hash' => $emailHash,
                'email_token_hash' => hash('sha256', $emailToken),
                'email_token_expires_at' => now()->addHours(self::HORAS_TOKEN_EMAIL),
                'access_token_hash' => hash('sha256', $accessToken),
                'access_token_expires_at' => now()->addDays(self::DIAS_TOKEN_ACESSO),
                'ip_hash' => $ipHash,
                'user_agent' => $this->userAgent($userAgent),
            ]);

            $this->registrarEvento($registro, null, CompanyRegistration::RASCUNHO, null, $ipHash, $userAgent);

            return ['registro' => $registro, 'email_token' => $emailToken, 'access_token' => $accessToken];
        });

        Notification::route('mail', (string) $dados['email'])->notify(new ConfirmacaoDeCadastro(
            $resultado['email_token'],
            $resultado['access_token'],
            $resultado['registro']->protocol,
            (string) $dados['razao_social'],
        ));

        return $resultado;
    }

    public function confirmarEmail(string $token, ?string $ip, ?string $userAgent): CompanyRegistration
    {
        $registro = CompanyRegistration::query()
            ->where('email_token_hash', hash('sha256', $token))
            ->where('email_token_expires_at', '>', now())
            ->first();

        if ($registro === null) {
            throw new Recusa('Este link de confirmação é inválido ou expirou.');
        }

        if (! $registro->estaEm(CompanyRegistration::RASCUNHO)) {
            throw new Recusa('Este cadastro já foi confirmado.');
        }

        $this->transitar(
            $registro,
            CompanyRegistration::EMAIL_PENDENTE,
            null,
            'e-mail confirmado pelo solicitante',
            $this->hashIp($ip),
            $userAgent,
            ['email_token_hash' => null, 'email_token_expires_at' => null],
        );

        return $registro;
    }

    /** O token de acesso é a credencial da etapa de documentos, guardada só como hash. */
    public function porTokenDeAcesso(string $token): CompanyRegistration
    {
        $registro = CompanyRegistration::query()
            ->where('access_token_hash', hash('sha256', $token))
            ->where('access_token_expires_at', '>', now())
            ->first();

        if ($registro === null) {
            throw new Recusa('O link deste cadastro é inválido ou expirou.');
        }

        return $registro;
    }

    public function anexarDocumento(
        CompanyRegistration $registro,
        UploadedFile $arquivo,
        string $tipo,
        ?string $ip,
        ?string $userAgent,
    ): CompanyDocument {
        if (! $registro->estaEm(CompanyRegistration::EMAIL_PENDENTE, CompanyRegistration::PENDENCIAS)) {
            throw new Recusa('Confirme o e-mail antes de enviar documentos.');
        }

        if (! in_array($tipo, CompanyDocument::TIPOS, true)) {
            throw new Recusa('Tipo de documento não reconhecido.');
        }

        if (! $arquivo->isValid() || $arquivo->getSize() > self::TAMANHO_MAXIMO) {
            throw new Recusa('O arquivo é inválido ou passa de 5 MB.');
        }

        $detectado = (string) (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $arquivo->getRealPath()) ?: '');
        $extensao = self::MIME_PERMITIDO[$detectado] ?? null;

        if ($extensao === null) {
            throw new Recusa('Envie apenas PDF, PNG ou JPG.');
        }

        $conteudo = file_get_contents($arquivo->getRealPath());
        $caminho = sprintf('cadastros/%d/%s-%s.%s', $registro->id, $tipo, Str::random(18), $extensao);
        Storage::disk('local')->put($caminho, $conteudo);

        return CompanyDocument::create([
            'registration_id' => $registro->id,
            'type' => $tipo,
            'disk' => 'local',
            'path' => $caminho,
            'sha256' => hash('sha256', (string) $conteudo),
            'mime' => $detectado,
            'original_name' => Str::limit($arquivo->getClientOriginalName(), 191, ''),
            'size' => (int) $arquivo->getSize(),
        ]);
    }

    public function registrarAceite(
        CompanyRegistration $registro,
        string $kind,
        string $version,
        ?string $ip,
        ?string $userAgent,
    ): LegalAcceptance {
        if (! in_array($kind, [LegalAcceptance::TERMOS, LegalAcceptance::PRIVACIDADE], true)) {
            throw new Recusa('Termo não reconhecido.');
        }

        return LegalAcceptance::firstOrCreate(
            ['registration_id' => $registro->id, 'kind' => $kind, 'version' => $version],
            [
                'accepted_at' => now(),
                'ip_hash' => $this->hashIp($ip),
                'user_agent' => $this->userAgent($userAgent),
            ],
        );
    }

    public function submeter(CompanyRegistration $registro, ?string $ip, ?string $userAgent): CompanyRegistration
    {
        if (! $registro->estaEm(CompanyRegistration::EMAIL_PENDENTE, CompanyRegistration::PENDENCIAS)) {
            throw new Recusa('Este cadastro não está pronto para envio.');
        }

        if ($registro->documents()->count() === 0) {
            throw new Recusa('Envie ao menos um documento para a análise.');
        }

        if ($registro->acceptances()->count() < 2) {
            throw new Recusa('Aceite os termos de uso e a política de privacidade.');
        }

        $this->transitar(
            $registro,
            CompanyRegistration::EM_ANALISE,
            null,
            'documentos enviados para análise',
            $this->hashIp($ip),
            $userAgent,
            ['submitted_at' => now()],
        );

        $razao = (string) $registro->dado('razao_social', 'Empresa');
        $raiz = User::query()->where('is_root', true)->get();

        if ($raiz->isNotEmpty()) {
            Notification::send($raiz, new CadastroEmAnalise($registro->protocol, $razao));
        }

        return $registro;
    }

    public function aprovar(
        CompanyRegistration $registro,
        User $ator,
        string $motivo = '',
        ?string $ip = null,
        ?string $userAgent = null,
    ): Company {
        if (! $registro->estaEm(CompanyRegistration::EM_ANALISE, CompanyRegistration::PENDENCIAS)) {
            throw new Recusa('Só é possível aprovar um cadastro em análise.');
        }

        $dados = $registro->dados ?? [];
        $email = mb_strtolower((string) ($dados['email'] ?? ''));
        $razao = (string) ($dados['razao_social'] ?? 'Empresa');

        $conta = User::withTrashed()->where('email', $email)->first();

        if ($conta?->isRoot()) {
            throw new Recusa('A conta raiz não pode ser usada como administradora de outra empresa.');
        }

        if ($conta?->trashed()) {
            throw new Recusa('Esta conta foi removida e não pode administrar a empresa aprovada.');
        }

        $resultado = DB::transaction(function () use ($registro, $dados, $email, $razao, $ator, $motivo, $ip, $userAgent, $conta): array {
            $plano = Plan::query()->where('slug', 'operacao')->first()
                ?? Plan::query()->where('is_active', true)->first();

            $empresa = Company::create([
                'name' => $razao,
                'slug' => $this->slugLivre($dados),
                'document' => $dados['cnpj'] ?? null,
                'cnpj' => $dados['cnpj'] ?? null,
                'legal_name' => $razao,
                'trade_name' => $dados['nome_fantasia'] ?? null,
                'email' => $email,
                'phone' => $dados['telefone'] ?? null,
                'ie' => $dados['ie'] ?? null,
                'im' => $dados['im'] ?? null,
                'cnae_main' => $dados['cnae_main'] ?? null,
                'legal_nature' => $dados['natureza_juridica'] ?? null,
                'size' => $dados['porte'] ?? null,
                'opened_at' => $dados['abertura'] ?? null,
                'tax_regime' => $dados['regime_tributario'] ?? null,
                'segment' => $dados['segmento'] ?? null,
                'employee_count' => $dados['funcionarios'] ?? null,
                'field_technician_count' => $dados['tecnicos_campo'] ?? null,
                'contacts' => Arr::only($dados, ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf']),
                'legal_responsible' => Arr::only($dados, ['responsavel_nome', 'responsavel_cpf', 'responsavel_email']),
                'plan_id' => $plano?->id,
                'subscription_status' => 'active',
                'status' => 'active',
                'lifecycle_status' => 'active',
                'registration_id' => $registro->id,
            ]);

            $papeis = Roles::provision(
                $empresa,
                Permission::query()->pluck('id', 'slug')->all(),
            );

            $definirSenha = $conta === null;

            if ($definirSenha) {
                // Conta nova: senha aleatória e nunca enviada. O e-mail dá o link
                // de definição, gravado pelo broker como token hasheado.
                $administrador = User::create([
                    'company_id' => $empresa->id,
                    'name' => (string) ($dados['admin_nome'] ?? $razao),
                    'email' => $email,
                    'password' => Str::password(24),
                    'phone' => $dados['telefone'] ?? null,
                    'status' => 'active',
                ]);
            } else {
                // Quem já tem acesso comum não troca de senha nem recebe link de
                // redefinição: a aprovação apenas move a conta para a empresa.
                $administrador = $conta;
                $administrador->forceFill([
                    'company_id' => $empresa->id,
                    'status' => 'active',
                ])->save();
            }

            $administrador->forceFill(['email_verified_at' => now()])->save();
            $administrador->roles()->sync([$papeis['administrator']->id]);

            $totais = ['empresas' => 0, 'filiais' => 0, 'setores' => 0, 'cargos' => 0, 'vinculos' => 0, 'alcances' => 0];
            (new BackfillOrganizacional)->paraEmpresa($empresa, $totais);

            CompanyDocument::query()->where('registration_id', $registro->id)
                ->update(['company_id' => $empresa->id, 'review_status' => 'aprovado']);
            LegalAcceptance::query()->where('registration_id', $registro->id)
                ->update(['user_id' => $administrador->id]);

            $this->transitar(
                $registro,
                CompanyRegistration::APROVADO,
                $ator,
                $motivo !== '' ? $motivo : 'cadastro aprovado',
                $this->hashIp($ip),
                $userAgent,
            );

            Auditor::gravar('cadastro aprovado', $empresa, [], "Protocolo {$registro->protocol}.");

            return ['empresa' => $empresa, 'administrador' => $administrador, 'definir_senha' => $definirSenha];
        });

        $empresa = $resultado['empresa'];

        if ($resultado['definir_senha']) {
            $token = Password::broker()->createToken($resultado['administrador']);
            $link = route('password.reset', ['token' => $token, 'email' => $email]);

            Notification::route('mail', $email)->notify(new CadastroAprovado(
                $registro->protocol,
                $empresa->name,
                $link,
            ));
        } else {
            Notification::route('mail', $email)->notify(new CadastroAprovadoContaExistente(
                $registro->protocol,
                $empresa->name,
            ));
        }

        return $empresa;
    }

    public function pedirPendencias(
        CompanyRegistration $registro,
        User $ator,
        string $motivo,
        ?string $ip = null,
        ?string $userAgent = null,
    ): CompanyRegistration {
        if (trim($motivo) === '') {
            throw new Recusa('Informe o que precisa ser ajustado.');
        }

        return $this->transitar(
            $registro,
            CompanyRegistration::PENDENCIAS,
            $ator,
            $motivo,
            $this->hashIp($ip),
            $userAgent,
        );
    }

    public function reprovar(
        CompanyRegistration $registro,
        User $ator,
        string $motivo,
        ?string $userAgent = null,
    ): CompanyRegistration {
        if (trim($motivo) === '') {
            throw new Recusa('Informe o motivo da reprovação.');
        }

        $registro = $this->transitar(
            $registro,
            CompanyRegistration::REPROVADO,
            $ator,
            $motivo,
            null,
            $userAgent,
        );

        Notification::route('mail', (string) $registro->dado('email', ''))
            ->notify(new CadastroReprovado($registro->protocol, $motivo));

        return $registro;
    }

    /** @param array<string, mixed> $campos */
    private function transitar(
        CompanyRegistration $registro,
        string $estado,
        ?User $ator,
        string $motivo,
        ?string $ipHash,
        ?string $userAgent,
        array $campos = [],
    ): CompanyRegistration {
        if (! $registro->podeTransitarPara($estado)) {
            throw new Recusa("Não é possível mudar {$registro->state} para {$estado}.");
        }

        $anterior = $registro->state;

        return DB::transaction(function () use ($registro, $estado, $ator, $motivo, $ipHash, $userAgent, $campos, $anterior): CompanyRegistration {
            $registro->fill(array_merge(['state' => $estado], $campos))->save();
            $this->registrarEvento($registro, $anterior, $estado, $motivo, $ipHash, $userAgent, $ator?->id);

            return $registro;
        });
    }

    /** @param array<string, mixed> $dados @return array<string, mixed> */
    private function normalizar(array $dados): array
    {
        foreach (['razao_social', 'nome_fantasia', 'admin_nome', 'telefone', 'cidade', 'uf'] as $chave) {
            if (array_key_exists($chave, $dados)) {
                $dados[$chave] = trim((string) $dados[$chave]);
            }
        }

        $dados['cnpj'] = preg_replace('/\D+/', '', (string) ($dados['cnpj'] ?? ''));
        $dados['email'] = mb_strtolower(trim((string) ($dados['email'] ?? '')));

        foreach (['funcionarios', 'tecnicos_campo'] as $chave) {
            if (array_key_exists($chave, $dados) && $dados[$chave] !== '') {
                $dados[$chave] = (int) $dados[$chave];
            }
        }

        return $dados;
    }

    private function proximoProtocolo(): string
    {
        $prefixo = 'NF-CAD-'.now()->format('Y').'-';
        $ultimo = CompanyRegistration::withTrashed()
            ->where('protocol', 'like', $prefixo.'%')
            ->max('protocol');

        $sequencia = $ultimo === null ? 1 : ((int) substr((string) $ultimo, -4)) + 1;
        $protocolo = $prefixo.str_pad((string) $sequencia, 4, '0', STR_PAD_LEFT);

        while (CompanyRegistration::withTrashed()->where('protocol', $protocolo)->exists()) {
            $protocolo = $prefixo.str_pad((string) ++$sequencia, 4, '0', STR_PAD_LEFT);
        }

        return $protocolo;
    }

    /** @param array<string, mixed> $dados */
    private function slugLivre(array $dados): string
    {
        $base = Str::slug((string) ($dados['nome_fantasia'] ?: $dados['razao_social'] ?: 'empresa'));
        $slug = $base !== '' ? $base : 'empresa';
        $sufixo = 2;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$sufixo++;
        }

        return $slug;
    }

    private function registrarEvento(
        CompanyRegistration $registro,
        ?string $de,
        string $para,
        ?string $motivo,
        ?string $ipHash,
        ?string $userAgent,
        ?int $atorId = null,
    ): void {
        $registro->events()->create([
            'from_state' => $de,
            'to_state' => $para,
            'actor_id' => $atorId,
            'reason' => $motivo === null || $motivo === '' ? null : Str::limit($motivo, 2000, ''),
            'ip_hash' => $ipHash,
            'user_agent' => $this->userAgent($userAgent),
            'created_at' => now(),
        ]);
    }

    private function hashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash('sha256', $ip);
    }

    private function userAgent(?string $userAgent): ?string
    {
        return $userAgent === null || $userAgent === '' ? null : Str::limit($userAgent, 255, '');
    }
}
