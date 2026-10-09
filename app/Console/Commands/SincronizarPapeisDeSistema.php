<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Permission;
use App\Support\PermissionCatalog;
use App\Support\Roles;
use Illuminate\Console\Command;

/**
 * Regrava os papéis de sistema de todas as empresas a partir do catálogo.
 * O seeder só roda no nascimento da empresa; quando o catálogo ganha uma
 * permissão — o supervisor que ganhou `users.create` na Fase 20 é o exemplo —,
 * são as empresas vivas que precisam sentir a mudança. O comando é idempotente:
 * roda duas vezes e a segunda não muda nada além dos timestamps.
 *
 * Papel personalizado não passa por aqui: o `provision` só conhece os cinco
 * slugs do catálogo, e é assim que a tela de um gestor fica intocada.
 */
class SincronizarPapeisDeSistema extends Command
{
    protected $signature = 'nf:papeis:sincronizar';

    protected $description = 'Regrava os papéis de sistema de todas as empresas a partir do catálogo de permissões';

    public function handle(): int
    {
        // O catálogo é a fonte: primeiro as permissões, depois os papéis.
        // Uma permissão nova do catálogo entra agora no banco e no instante
        // seguinte já está disponível para quem de direito.
        $ids = [];

        foreach (PermissionCatalog::all() as $slug => $definicao) {
            $permissao = Permission::query()->firstOrNew(['slug' => $slug]);
            $permissao->module = $definicao['module'];
            $permissao->action = $definicao['action'];
            $permissao->save();

            $ids[$slug] = $permissao->id;
        }

        $empresas = Company::query()->orderBy('id')->cursor();
        $total = 0;

        foreach ($empresas as $empresa) {
            Roles::provision($empresa, $ids);
            $total += 1;

            $this->line(sprintf('  %s · %s', $empresa->name, $empresa->slug ?? "#{$empresa->id}"));
        }

        $this->info(sprintf(
            '%d permiss%s no catálogo, %d empresa%s regravada%s.',
            count($ids),
            count($ids) === 1 ? 'ão' : 'ões',
            $total,
            $total === 1 ? '' : 's',
            $total === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
