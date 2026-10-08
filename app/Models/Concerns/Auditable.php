<?php

namespace App\Models\Concerns;

use App\Support\Auditor;
use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de auditoria automática para modelos de domínio: criar, alterar e
 * excluir deixam linha em audit_logs sem que cada controller precise lembrar.
 * A ação de negócio que não é CRUD (aprovar ordem, dar baixa, fazer check-in)
 * continua gravada explicitamente pelo controller, com o verbo certo.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $modelo) => Auditor::gravar(
            'criado', $modelo, [], 'Cadastro de '.static::resumoAuditoria($modelo),
        ));

        static::updated(function (Model $modelo) {
            $mudancas = Auditor::diferenca($modelo);

            if ($mudancas === []) {
                return;
            }

            Auditor::gravar('atualizado', $modelo, $mudancas, 'Alteração de '.static::resumoAuditoria($modelo));
        });

        static::deleted(function (Model $modelo) {
            $suave = method_exists($modelo, 'trashed') && $modelo->trashed();

            Auditor::gravar(
                $suave ? 'excluido suavemente' : 'excluido',
                $modelo,
                [],
                'Exclusão de '.static::resumoAuditoria($modelo),
            );
        });

        // Só faz sentido em modelo com exclusão suave; sem isso o evento não existe.
        if (method_exists(static::class, 'restore')) {
            static::restored(fn (Model $modelo) => Auditor::gravar(
                'restaurado', $modelo, [], 'Restauração de '.static::resumoAuditoria($modelo),
            ));
        }
    }

    /** Identidade legível do registro na trilha: nome, número, SKU — o que o modelo tiver. */
    protected static function resumoAuditoria(Model $modelo): string
    {
        foreach (['name', 'title', 'number', 'protocol', 'sku', 'code', 'slug', 'key'] as $campo) {
            if (filled($modelo->getAttribute($campo))) {
                return trim(class_basename($modelo).' "'.strval($modelo->getAttribute($campo)).'"');
            }
        }

        return class_basename($modelo).' #'.$modelo->getKey();
    }
}
