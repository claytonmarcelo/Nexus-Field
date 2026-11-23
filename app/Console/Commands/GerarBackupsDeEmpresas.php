<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Backup\GerenciadorDeBackups;
use App\Support\Settings;
use App\Support\SettingsCatalog;
use Illuminate\Console\Command;
use Throwable;

/**
 * A varredura que tira o clique do caminho: toda empresa ativa ganha uma cópia
 * nova quando o intervalo dela vence. A primeira já nasce na aprovação do
 * cadastro; aqui só se mantém o ritmo. Nenhum dado de operação é lido, além do
 * necessário para saber de quem é a cópia.
 */
class GerarBackupsDeEmpresas extends Command
{
    protected $signature = 'nf:backups:empresas
        {--empresa= : Id ou chave de uma empresa específica}
        {--forcar : Grava uma cópia mesmo antes do intervalo da empresa vencer}';

    protected $description = 'Grava o back‑up automático individual de cada empresa ativa';

    public function handle(GerenciadorDeBackups $backups): int
    {
        $criados = 0;
        $pulados = 0;
        $falhas = 0;

        $consulta = Company::query()
            ->where('status', 'active')
            ->orderBy('id');

        $alvo = trim((string) $this->option('empresa'));

        if ($alvo !== '') {
            if (ctype_digit($alvo)) {
                $consulta->where('id', (int) $alvo);
            } else {
                $consulta->where('slug', $alvo);
            }
        }

        $consulta->chunkById((int) config('backup.lote', 50), function ($empresas) use (
            $backups,
            &$criados,
            &$pulados,
            &$falhas,
        ): void {
            foreach ($empresas as $empresa) {
                if (! $this->option('forcar') && ! $this->venceu($empresa, $backups)) {
                    $pulados++;

                    continue;
                }

                try {
                    $backups->criar($empresa);
                    $criados++;
                    $this->line("Back‑up gravado: {$empresa->slug}");
                } catch (Throwable $erro) {
                    $falhas++;
                    report($erro);
                    $this->error("Falha no back‑up de {$empresa->slug}: ".$erro->getMessage());
                }
            }
        });

        $this->info("Back‑ups criados: {$criados}. Empresas dentro do intervalo: {$pulados}. Falhas: {$falhas}.");

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function venceu(Company $empresa, GerenciadorDeBackups $backups): bool
    {
        $ultimo = $backups->ultimo($empresa);

        if ($ultimo === null) {
            return true;
        }

        $dias = Settings::inteiro(SettingsCatalog::BACKUP_INTERVALO_DIAS, $empresa->id);

        return $ultimo->created_at->lessThan(now()->subDays($dias));
    }
}
