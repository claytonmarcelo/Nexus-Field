<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyBackup;
use App\Services\Backup\GerenciadorDeBackups;
use App\Services\Recusa;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * As três ações que a empresa faz com o próprio back‑up: pedir uma cópia,
 * baixar o arquivo e reaplicar a configuração. Tudo passa pelo serviço, e a
 * checagem explícita de empresa complementa o escopo global do tenant — um id
 * de cópia alheia nunca abre nem arquivo nem restauração.
 */
class BackupController extends Controller
{
    public function __construct(private readonly GerenciadorDeBackups $backups) {}

    public function store(Request $request): RedirectResponse
    {
        $empresa = $this->empresa();

        try {
            $backup = $this->backups->criar($empresa, $request->user());
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return back()->with('status', sprintf(
            'Back‑up individual criado às %s, com %d configurações guardadas.',
            $backup->created_at->format('H:i'),
            $backup->total_configuracoes,
        ));
    }

    public function download(CompanyBackup $backup): StreamedResponse
    {
        $this->garantir($backup);

        return Storage::disk($backup->disk)->download($backup->path, $backup->nome_arquivo);
    }

    public function restore(Request $request, CompanyBackup $backup): RedirectResponse
    {
        $empresa = $this->empresa();
        $this->garantir($backup, $empresa);

        try {
            $this->backups->restaurar($backup, $empresa, $request->user());
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return back()->with('status', 'Configuração restaurada do back‑up. Nenhum cliente, ordem, arquivo ou rota foi apagado.');
    }

    private function garantir(CompanyBackup $backup, ?Company $empresa = null): void
    {
        $empresa ??= $this->empresa();

        abort_unless((int) $backup->company_id === (int) $empresa->id, 404,
            'Este back‑up não pertence à empresa da sessão.');
    }

    private function empresa(): Company
    {
        $empresa = Company::query()->find(TenantContext::id());

        abort_unless($empresa !== null, 404,
            'Esta conta não pertence a uma empresa com perfil para configurar.');

        return $empresa;
    }
}
