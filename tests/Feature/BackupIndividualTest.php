<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBackup;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\Backup\GerenciadorDeBackups;
use App\Services\Recusa;
use App\Support\Settings;
use App\Support\SettingsCatalog;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A cópia individual protege a configuração da empresa sem virar dump e sem
 * virar risco: o arquivo é privado, a restauração não apaga operação, e o
 * limite de armazenamento é respeitado antes de qualquer gravação.
 */
class BackupIndividualTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_copia_guarda_apenas_configuracao_e_restauracao_nao_apaga_operacao(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $organizacao = $this->organizacao($empresa);

        TenantContext::set($empresa->id);
        CompanySetting::put(SettingsCatalog::RAIO_CHECKIN, 500);
        TenantContext::forget();

        $empresa->newQuery()->withoutGlobalScopes()->whereKey($empresa->id)
            ->update(['name' => 'Confeitaria Antes']);
        $empresa = Company::withoutGlobalScopes()->findOrFail($empresa->id);
        DB::table('branches')->where('id', $organizacao['filial'])->update(['name' => 'Filial Antes']);
        DB::table('departments')->where('id', $organizacao['setor'])->update(['name' => 'Setor Antes']);

        $clienteId = $this->cliente($empresa);
        $ordemId = $this->ordem($empresa, $clienteId);

        $backups = app(GerenciadorDeBackups::class);
        $backup = $backups->criar($empresa, $admin);

        $this->assertDatabaseHas('company_backups', [
            'id' => $backup->id,
            'company_id' => $empresa->id,
            'tipo' => 'configuracao',
            'disk' => 'local',
        ]);
        Storage::disk('local')->assertExists($backup->path);

        $conteudo = Storage::disk('local')->get($backup->path);
        $this->assertSame(hash('sha256', $conteudo), $backup->checksum);
        $this->assertStringContainsString('Confeitaria Antes', $conteudo);
        $this->assertStringNotContainsString('Cliente Vitrine', $conteudo);
        $this->assertStringNotContainsString('OS-BACKUP-001', $conteudo);
        $this->assertSame(0, $backup->resumo['clients'] ?? 0);

        $empresa->newQuery()->withoutGlobalScopes()->whereKey($empresa->id)
            ->update(['name' => 'Confeitaria Depois']);
        DB::table('branches')->where('id', $organizacao['filial'])->update(['name' => 'Filial Depois']);
        DB::table('departments')->where('id', $organizacao['setor'])->update(['name' => 'Setor Depois']);
        TenantContext::set($empresa->id);
        CompanySetting::put(SettingsCatalog::RAIO_CHECKIN, 250);
        $this->assertSame(250, Settings::inteiro(SettingsCatalog::RAIO_CHECKIN));
        TenantContext::forget();

        $backups->restaurar($backup, $empresa, $admin);

        $this->assertSame('Confeitaria Antes', $empresa->fresh()->name);
        $this->assertSame('Filial Antes', DB::table('branches')->where('id', $organizacao['filial'])->value('name'));
        $this->assertSame('Setor Antes', DB::table('departments')->where('id', $organizacao['setor'])->value('name'));
        TenantContext::set($empresa->id);
        $this->assertSame(500, Settings::inteiro(SettingsCatalog::RAIO_CHECKIN));
        TenantContext::forget();

        $this->assertDatabaseHas('clients', ['id' => $clienteId, 'name' => 'Cliente Vitrine']);
        $this->assertDatabaseHas('service_orders', ['id' => $ordemId, 'number' => 'OS-BACKUP-001']);
        $this->assertNotNull($backup->fresh()->restaurado_em);
    }

    public function test_perfil_mostra_copia_configuracao_e_aviso_com_degraus_de_permissao(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisor = $this->conta($empresa, 'supervisor', 'supervisor@backup.local');

        TenantContext::set($empresa->id);
        CompanySetting::put(SettingsCatalog::BACKUP_RETENCAO, 3);
        CompanySetting::put(SettingsCatalog::BACKUP_INTERVALO_DIAS, 2);
        CompanySetting::put(SettingsCatalog::BACKUP_LIMITE_MB, 1);
        CompanySetting::put(SettingsCatalog::BACKUP_AVISO_PERCENTUAL, 50);
        TenantContext::forget();

        $backup = app(GerenciadorDeBackups::class)->criar($empresa, $admin);
        DB::table('company_backups')->where('id', $backup->id)->update(['size_bytes' => 600000]);

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Back‑up individual da empresa')
            ->assertSee('Cópias guardadas')
            ->assertSee('Aviso de carga')
            ->assertSee('Criar back‑up agora')
            ->assertSee('Baixar')
            ->assertSee('Restaurar')
            ->assertSee('Cópias mantidas por empresa')
            ->assertSee('Intervalo do back‑up automático (dias)')
            ->assertSee('Limite de armazenamento do back‑up (MB)')
            ->assertSee('Avisar ao usar percentual do limite (%)')
            ->assertSee('value="3"', false)
            ->assertSee('value="2"', false)
            ->assertSee('value="1"', false)
            ->assertSee('value="50"', false);

        $this->actingAs($supervisor)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Modo leitura')
            ->assertSee('Baixar')
            ->assertDontSee('Criar back‑up agora')
            ->assertDontSee('Restaurar');
    }

    public function test_actions_de_backup_no_perfil_respeitam_tenant_e_permissao(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisor = $this->conta($empresa, 'supervisor', 'supervisor@backup.local');
        $outra = $this->makeCompany('backup-outra');
        $adminOutra = $this->makeUser('administrator', $outra, 'admin-outra@backup.local');

        $backup = app(GerenciadorDeBackups::class)->criar($empresa, $admin);

        $this->actingAs($supervisor)->post(route('settings.backups.store'))
            ->assertForbidden();
        $this->actingAs($supervisor)->post(route('settings.backups.restore', $backup))
            ->assertForbidden();

        $this->actingAs($adminOutra)->get(route('settings.backups.download', $backup))
            ->assertNotFound();
        $this->actingAs($adminOutra)->post(route('settings.backups.restore', $backup))
            ->assertNotFound();

        $this->actingAs($admin)->post(route('settings.backups.store'))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame(2, CompanyBackup::query()->anyCompany()->where('company_id', $empresa->id)->count());

        $this->actingAs($admin)->get(route('settings.backups.download', $backup))
            ->assertOk();
        $this->actingAs($admin)->post(route('settings.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_limite_recusa_gravacao_e_retencao_poda_copias_antigas(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        TenantContext::set($empresa->id);
        CompanySetting::put(SettingsCatalog::BACKUP_RETENCAO, 1);
        CompanySetting::put(SettingsCatalog::BACKUP_LIMITE_MB, 20);
        TenantContext::forget();

        $backups = app(GerenciadorDeBackups::class);
        $primeira = $backups->criar($empresa, $admin);
        $segunda = $backups->criar($empresa, $admin);
        $terceira = $backups->criar($empresa, $admin);

        $this->assertSame(1, CompanyBackup::query()->anyCompany()->where('company_id', $empresa->id)->count());
        Storage::disk('local')->assertMissing($primeira->path);
        Storage::disk('local')->assertMissing($segunda->path);
        Storage::disk('local')->assertExists($terceira->path);

        DB::table('company_backups')->where('id', $terceira->id)
            ->update(['size_bytes' => 20 * 1024 * 1024]);

        try {
            $backups->criar($empresa, $admin);
            $this->fail('O limite de armazenamento deveria recusar a cópia nova.');
        } catch (Recusa $recusa) {
            $this->assertStringContainsString('Aviso de carga', $recusa->getMessage());
        }
    }

    public function test_varredura_diaria_respeita_o_intervalo_da_empresa(): void
    {
        [$empresa] = $this->empresaComAdmin();
        $backups = app(GerenciadorDeBackups::class);
        $primeira = $backups->criar($empresa);

        $this->artisan('nf:backups:empresas', ['--empresa' => (string) $empresa->id])->assertSuccessful();
        $this->assertSame(1, CompanyBackup::query()->anyCompany()->where('company_id', $empresa->id)->count());

        DB::table('company_backups')->where('id', $primeira->id)
            ->update(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        $this->artisan('nf:backups:empresas', ['--empresa' => (string) $empresa->id])->assertSuccessful();
        $this->assertSame(2, CompanyBackup::query()->anyCompany()->where('company_id', $empresa->id)->count());

        TenantContext::set($empresa->id);
        CompanySetting::put(SettingsCatalog::BACKUP_INTERVALO_DIAS, 30);
        TenantContext::forget();
        DB::table('company_backups')->where('company_id', $empresa->id)
            ->update(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        $this->artisan('nf:backups:empresas', ['--empresa' => (string) $empresa->id])->assertSuccessful();
        $this->assertSame(2, CompanyBackup::query()->anyCompany()->where('company_id', $empresa->id)->count());
    }

    public function test_restauracao_recusa_arquivo_alterado_e_copia_de_outra_empresa(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $outra = $this->makeCompany('backup-outra');
        $backups = app(GerenciadorDeBackups::class);
        $backup = $backups->criar($empresa, $admin);
        $alheio = $backups->criar($outra);

        Storage::disk('local')->put($backup->path, $backup->checksum.' alterado');

        try {
            $backups->restaurar($backup, $empresa, $admin);
            $this->fail('Um arquivo alterado não pode ser restaurado.');
        } catch (Recusa $recusa) {
            $this->assertStringContainsString('arquivo mudou', $recusa->getMessage());
        }

        try {
            $backups->restaurar($alheio, $empresa, $admin);
            $this->fail('Uma cópia de outra empresa não pode ser restaurada.');
        } catch (Recusa $recusa) {
            $this->assertStringContainsString('outra empresa', $recusa->getMessage());
        }
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('backup-alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'admin@backup.local')];
    }

    private function conta(Company $empresa, string $papel, string $email): User
    {
        TenantContext::set($empresa->id);
        $usuario = $this->makeUser($papel, $empresa, $email);
        TenantContext::forget();

        return $usuario;
    }

    /** @return array{filial: int, setor: int, cargo: int} */
    private function organizacao(Company $empresa): array
    {
        $agora = now();

        $filial = DB::table('branches')->insertGetId([
            'company_id' => $empresa->id,
            'name' => 'Filial Original',
            'slug' => 'filial-original',
            'type' => 'filial',
            'active' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $setor = DB::table('departments')->insertGetId([
            'company_id' => $empresa->id,
            'branch_id' => $filial,
            'name' => 'Setor Original',
            'slug' => 'setor-original',
            'type' => 'operacao',
            'depth' => 0,
            'path' => '/',
            'active' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $cargo = DB::table('positions')->insertGetId([
            'company_id' => $empresa->id,
            'department_id' => $setor,
            'name' => 'Cargo Original',
            'level' => 5,
            'active' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return ['filial' => $filial, 'setor' => $setor, 'cargo' => $cargo];
    }

    private function cliente(Company $empresa): int
    {
        $agora = now();

        return DB::table('clients')->insertGetId([
            'company_id' => $empresa->id,
            'name' => 'Cliente Vitrine',
            'status' => 'active',
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }

    private function ordem(Company $empresa, int $clienteId): int
    {
        $agora = now();

        return DB::table('service_orders')->insertGetId([
            'company_id' => $empresa->id,
            'client_id' => $clienteId,
            'number' => 'OS-BACKUP-001',
            'title' => 'Revisão da vitrine',
            'priority' => 'normal',
            'status' => 'open',
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }
}
