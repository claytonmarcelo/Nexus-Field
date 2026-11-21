<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pedido de cadastro de uma empresa, antes dela existir no tenant.
 *
 * Não usa `BelongsToCompany`: o registro é da plataforma e não pertence a
 * nenhuma empresa enquanto a aprovação não acontece. Por isso também não entra
 * na `CompanyScope`, e toda consulta pública é fechada por hash/token.
 */
class CompanyRegistration extends Model
{
    use SoftDeletes;

    public const RASCUNHO = 'rascunho';
    public const EMAIL_PENDENTE = 'email_pendente';
    public const EM_ANALISE = 'em_analise';
    public const PENDENCIAS = 'pendencias';
    public const APROVADO = 'aprovado';
    public const REPROVADO = 'reprovado';
    public const CANCELADO = 'cancelado';
    public const EXPIRADO = 'expirado';

    /** Única fonte das transições permitidas — o Service valida contra ela. */
    public const TRANSICOES = [
        self::RASCUNHO => [self::EMAIL_PENDENTE, self::CANCELADO, self::EXPIRADO],
        self::EMAIL_PENDENTE => [self::EM_ANALISE, self::CANCELADO, self::EXPIRADO],
        self::EM_ANALISE => [self::PENDENCIAS, self::APROVADO, self::REPROVADO, self::CANCELADO],
        self::PENDENCIAS => [self::EM_ANALISE, self::REPROVADO, self::CANCELADO, self::EXPIRADO],
        self::APROVADO => [],
        self::REPROVADO => [],
        self::CANCELADO => [],
        self::EXPIRADO => [],
    ];

    protected $fillable = [
        'protocol', 'state', 'dados', 'cnpj_hash', 'email_hash',
        'email_token_hash', 'email_token_expires_at',
        'access_token_hash', 'access_token_expires_at',
        'ip_hash', 'user_agent', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'email_token_expires_at' => 'datetime',
            'access_token_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class, 'registration_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CompanyReviewEvent::class, 'registration_id')->orderBy('created_at');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class, 'registration_id');
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'registration_id');
    }

    public function estaEm(string ...$estados): bool
    {
        return in_array($this->state, $estados, true);
    }

    public function podeTransitarPara(string $estado): bool
    {
        return in_array($estado, self::TRANSICOES[$this->state] ?? [], true);
    }

    /** Lê um campo do JSON sem deixar a chave virar exceção em tela pública. */
    public function dado(string $chave, mixed $padrao = null): mixed
    {
        return $this->dados[$chave] ?? $padrao;
    }
}
