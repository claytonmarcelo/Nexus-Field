<?php

namespace App\Services\Backup;

use App\Models\Company;
use App\Models\CompanyBackup;
use App\Models\User;
use App\Services\Recusa;
use App\Support\Auditor;
use App\Support\Settings;
use App\Support\SettingsCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Dono do back‑up individual de cada empresa.
 *
 * A cópia é lógica e deliberadamente pequena: perfil, preferências e
 * organograma — exatamente a configuração que uma limpeza de cache não pode
 * levar. Clientes, ordens, arquivos e rotas não entram no arquivo e nunca são
 * apagados por uma restauração: ela apenas reaplica o que estava gravado.
 * O arquivo mora no disco privado, com checksum, e a retenção é podada sozinha.
 */
final class GerenciadorDeBackups
{
    /** @var array<string, array<int, string>> Colunas gravadas de cada tabela de configuração. */
    private const TABELAS = [
        'branches' => ['id', 'company_id', 'name', 'slug', 'type', 'manager_user_id', 'active',
            'deleted_at', 'created_at', 'updated_at'],
        'departments' => ['id', 'company_id', 'branch_id', 'parent_id', 'name', 'slug', 'description',
            'manager_user_id', 'type', 'depth', 'path', 'active', 'deleted_at', 'created_at', 'updated_at'],
        'positions' => ['id', 'company_id', 'department_id', 'name', 'level', 'description',
            'suggested_role_id', 'active', 'deleted_at', 'created_at', 'updated_at'],
    ];

    /** @var array<int, string> Só o perfil configurável; slug, plano e estado não são regravados. */
    private const CAMPOS_PERFIL = ['name', 'document', 'phone', 'email', 'website', 'logo_path'];

    public function criar(Company $empresa, ?User $ator = null): CompanyBackup
    {
        $dados = $this->retrato($empresa);
        $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR);

        $this->garantirEspaco($empresa, strlen($json));

        $disco = (string) config('backup.disk', 'local');
        $diretorio = (string) config('backup.diretorio', 'empresas');
        $caminho = $diretorio.'/'.$empresa->id.'/configuracao-'.now()->format('Ymd-His')
            .'-'.Str::lower(Str::random(8)).'.json';
        $storage = Storage::disk($disco);
        $temporario = $caminho.'.tmp';

        try {
            if (! $storage->put($temporario, $json)) {
                throw new RuntimeException('O disco de back‑up recusou a gravação da empresa '.$empresa->id.'.');
            }

            $storage->move($temporario, $caminho);
        } catch (Throwable $erro) {
            $storage->delete([$temporario, $caminho]);

            throw $erro;
        }

        $agora = now();
        $id = DB::table('company_backups')->insertGetId([
            'company_id' => $empresa->id,
            'tipo' => 'configuracao',
            'disk' => $disco,
            'path' => $caminho,
            'nome_arquivo' => basename($caminho),
            'checksum' => hash('sha256', $json),
            'size_bytes' => strlen($json),
            'total_configuracoes' => (int) $dados['resumo']['total'],
            'resumo' => json_encode($dados['resumo'], JSON_THROW_ON_ERROR),
            'created_by' => $ator?->id,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $backup = $this->buscar($id);
        $this->podar($empresa);

        if ($ator !== null) {
            Auditor::gravar('back‑up criado', $backup, [], sprintf(
                'Cópia individual de configuração da empresa %s gravada com %d ajustes.',
                $empresa->name,
                $backup->total_configuracoes,
            ));
        }

        return $backup;
    }

    /** @return Collection<int, CompanyBackup> */
    public function listar(Company $empresa, int $limite = 10): Collection
    {
        return CompanyBackup::query()->anyCompany()
            ->where('company_id', $empresa->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    public function ultimo(Company $empresa): ?CompanyBackup
    {
        return CompanyBackup::query()->anyCompany()
            ->where('company_id', $empresa->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A carga que o perfil mostra. O aviso é cedo de propósito: quem configurou
     * o limite deve saber que ele está chegando antes da gravação ser recusada.
     *
     * @return array{quantidade: int, bytes: int, limite_bytes: int, percentual: int, aviso: ?string, ultimo: ?CompanyBackup}
     */
    public function carga(Company $empresa): array
    {
        $copias = $this->listar($empresa, 1000);
        $bytes = (int) $copias->sum('size_bytes');
        $limiteMb = Settings::inteiro(SettingsCatalog::BACKUP_LIMITE_MB, $empresa->id);
        $limite = $limiteMb * 1024 * 1024;
        $percentual = $limite > 0 ? (int) round($bytes / $limite * 100) : 100;
        $avisoPercentual = Settings::inteiro(SettingsCatalog::BACKUP_AVISO_PERCENTUAL, $empresa->id);

        $aviso = $percentual >= $avisoPercentual
            ? sprintf(
                'Aviso de carga: as cópias desta empresa ocupam %s de %s MB (%d%%). '
                    .'Ajuste a retenção ou o limite no perfil antes que a próxima gravação seja recusada.',
                $this->mb($bytes),
                $this->mb($limite),
                $percentual,
            )
            : null;

        return [
            'quantidade' => $copias->count(),
            'bytes' => $bytes,
            'limite_bytes' => $limite,
            'percentual' => $percentual,
            'aviso' => $aviso,
            'ultimo' => $copias->first(),
        ];
    }

    public function restaurar(CompanyBackup $backup, Company $empresa, ?User $ator = null): CompanyBackup
    {
        if ((int) $backup->company_id !== (int) $empresa->id) {
            throw new Recusa('Este back‑up pertence a outra empresa.');
        }

        $conteudo = Storage::disk($backup->disk)->get($backup->path);

        if (! is_string($conteudo)) {
            throw new Recusa('O arquivo deste back‑up não está mais disponível no disco privado.');
        }

        if (! hash_equals($backup->checksum, hash('sha256', $conteudo))) {
            throw new Recusa('O arquivo mudou desde a gravação; a restauração foi cancelada por segurança.');
        }

        try {
            $dados = json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Recusa('O arquivo deste back‑up não pôde ser lido.');
        }

        if (($dados['empresa']['id'] ?? null) !== $empresa->id) {
            throw new Recusa('O back‑up não corresponde a esta empresa.');
        }

        DB::transaction(function () use ($dados, $empresa, $backup): void {
            $perfil = Arr::only($dados['empresa']['perfil'] ?? [], self::CAMPOS_PERFIL);

            if ($perfil !== []) {
                DB::table('companies')->where('id', $empresa->id)->update($perfil);
            }

            $this->restaurarPreferencias($dados['preferencias'] ?? [], $empresa);
            $this->restaurarTabelas($dados['organizacao'] ?? [], $empresa);

            CompanyBackup::query()->anyCompany()->whereKey($backup->id)
                ->update(['restaurado_em' => now()]);
        });

        if ($ator !== null) {
            Auditor::gravar('back‑up restaurado', $backup, [], sprintf(
                'Configuração da empresa %s reaplicada a partir do back‑up de %s. '
                    .'Nenhum cliente, ordem, arquivo ou rota foi apagado.',
                $empresa->name,
                $backup->created_at?->format('d/m/Y H:i') ?? 'data indefinida',
            ));
        }

        return $this->buscar($backup->id);
    }

    private function buscar(int $id): CompanyBackup
    {
        return CompanyBackup::query()->anyCompany()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function retrato(Company $empresa): array
    {
        // Ler do banco, e não do objeto em memória: uma edição feita no mesmo
        // pedido não pode deixar a cópia com o perfil de antes da mudança.
        $perfil = (array) DB::table('companies')->where('id', $empresa->id)->firstOrFail();

        $preferencias = DB::table('company_settings')
            ->where('company_id', $empresa->id)
            ->orderBy('key')
            ->get(['key', 'value'])
            ->map(fn ($linha): array => ['key' => $linha->key, 'value' => $linha->value])
            ->all();

        $organizacao = [];

        foreach (self::TABELAS as $tabela => $colunas) {
            $organizacao[$tabela] = DB::table($tabela)
                ->where('company_id', $empresa->id)
                ->orderBy('id')
                ->get($colunas)
                ->map(fn ($linha): array => (array) $linha)
                ->all();
        }

        $resumo = [
            'perfil' => count(self::CAMPOS_PERFIL),
            'preferencias' => count($preferencias),
        ];

        foreach ($organizacao as $tabela => $linhas) {
            $resumo[$tabela] = count($linhas);
        }

        $resumo['total'] = array_sum($resumo);

        return [
            'versao' => 1,
            'criado_em' => now()->toIso8601String(),
            'empresa' => [
                'id' => $empresa->id,
                'slug' => $perfil['slug'] ?? $empresa->slug,
                'perfil' => Arr::only($perfil, self::CAMPOS_PERFIL),
            ],
            'preferencias' => $preferencias,
            'organizacao' => $organizacao,
            'resumo' => $resumo,
        ];
    }

    private function garantirEspaco(Company $empresa, int $novoBytes): void
    {
        $usado = (int) CompanyBackup::query()->anyCompany()
            ->where('company_id', $empresa->id)
            ->sum('size_bytes');
        $limite = Settings::inteiro(SettingsCatalog::BACKUP_LIMITE_MB, $empresa->id) * 1024 * 1024;

        if ($usado + $novoBytes > $limite) {
            throw new Recusa(sprintf(
                'Aviso de carga: o back‑up desta empresa já usa %s de %s MB. '
                    .'Aumente o limite ou reduza a retenção no perfil para gravar uma cópia nova.',
                $this->mb($usado),
                $this->mb($limite),
            ));
        }
    }

    private function podar(Company $empresa): void
    {
        $manter = max(1, Settings::inteiro(SettingsCatalog::BACKUP_RETENCAO, $empresa->id));

        /*
         * MySQL não aceita `offset` sem `limit`. Buscar só o primeiro excedente
         * resolve a poda de cada gravação e também a queda de retenção: o laço
         * segue até a empresa ficar com exatamente o número escolhido.
         */
        while (true) {
            $copias = CompanyBackup::query()->anyCompany()
                ->where('company_id', $empresa->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($manter + 1)
                ->get(['id', 'disk', 'path']);

            if ($copias->count() <= $manter) {
                return;
            }

            $excedente = $copias->last();
            Storage::disk($excedente->disk)->delete($excedente->path);
            DB::table('company_backups')->where('id', $excedente->id)->delete();
        }
    }

    /** @param array<int, array<string, mixed>> $preferencias */
    private function restaurarPreferencias(array $preferencias, Company $empresa): void
    {
        foreach ($preferencias as $preferencia) {
            $chave = (string) ($preferencia['key'] ?? '');
            $valor = $preferencia['value'] ?? null;

            if ($chave === '') {
                continue;
            }

            $atual = DB::table('company_settings')
                ->where('company_id', $empresa->id)
                ->where('key', $chave)
                ->first();

            if ($atual !== null) {
                DB::table('company_settings')
                    ->where('id', $atual->id)
                    ->update(['value' => $valor, 'updated_at' => now()]);

                continue;
            }

            DB::table('company_settings')->insert([
                'company_id' => $empresa->id,
                'key' => $chave,
                'value' => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, array<int, array<string, mixed>>> $organizacao */
    private function restaurarTabelas(array $organizacao, Company $empresa): void
    {
        foreach (self::TABELAS as $tabela => $colunas) {
            foreach ($organizacao[$tabela] ?? [] as $linha) {
                $id = (int) ($linha['id'] ?? 0);

                if ($id === 0) {
                    continue;
                }

                $atual = DB::table($tabela)->where('id', $id)->first();

                if ($atual !== null && (int) $atual->company_id !== (int) $empresa->id) {
                    throw new Recusa('Um registro do back‑up pertence a outra empresa; nada foi reaplicado.');
                }

                $valores = Arr::only($linha, array_diff($colunas, ['id']));
                $valores['company_id'] = $empresa->id;

                if ($atual !== null) {
                    DB::table($tabela)->where('id', $id)->update($valores);

                    continue;
                }

                DB::table($tabela)->insert(['id' => $id] + $valores);
            }
        }
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / (1024 * 1024), 2, ',', '.').' MB';
    }
}
