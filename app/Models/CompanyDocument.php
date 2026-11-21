<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento privado enviado no cadastro. O arquivo fica no disco `local`,
 * nunca em `public`: o que a tabela guarda é o caminho e o sha256 para conferir
 * integridade, não uma URL que qualquer pessoa possa abrir.
 */
class CompanyDocument extends Model
{
    public const TIPOS = ['contrato_social', 'cnpj', 'endereco', 'responsavel'];

    protected $fillable = [
        'registration_id', 'company_id', 'type', 'disk', 'path', 'sha256',
        'mime', 'original_name', 'size', 'review_status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(CompanyRegistration::class, 'registration_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
