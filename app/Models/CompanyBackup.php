<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O cartão de identidade de um arquivo de back‑up. O JSON com a configuração
 * vive no disco privado; aqui ficam o caminho, o checksum e o tamanho, que é
 * o bastante para listar, baixar, restaurar e avisar quando o uso cresce.
 */
class CompanyBackup extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'tipo', 'disk', 'path', 'nome_arquivo', 'checksum',
        'size_bytes', 'total_configuracoes', 'resumo', 'created_by', 'restaurado_em',
    ];

    protected function casts(): array
    {
        return [
            'resumo' => 'array',
            'restaurado_em' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
