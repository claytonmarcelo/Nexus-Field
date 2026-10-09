<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\FinancialRecord;
use App\Models\Notification;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\User;
use App\Support\Settings;
use App\Support\SettingsCatalog;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A Fase 21 não é um formulário — é a prova de que a opinião da empresa vira
 * comportamento do sistema. O raio escolhido é o que o check-in aceita na hora;
 * a janela escolhida é o que a varredura de todo dia escuta; o ligamento
 * desligado é o silêncio do sino. E o que a tela não mostra também é regra: o
 * supervisor lê a casa mas não encosta, o slug não se mexe nem para o
 * administrador, e salvar sem mudar nada não inventa carimbo na auditoria.
 */
class SettingsTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_o_supervisor_le_a_casa_mas_nao_encosta_em_nada(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');

        $this->actingAs($supervisora)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Configurações')
            ->assertSee('Modo leitura');

        $this->actingAs($supervisora)
            ->put(route('settings.update'), ['name' => 'Empresa Renomeada pela Supervisorinha'])
            ->assertForbidden();

        $this->assertSame('Empresa alfa', $empresa->fresh()->name);
    }

    public function test_a_casa_sem_opiniao_responde_o_padrao_e_a_tela_diz_isso(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        TenantContext::set($empresa->id);
        $this->assertSame(250.0, ServiceOrderCheckin::raioAceito());
        $this->assertSame(2, Settings::inteiro(SettingsCatalog::DIAS_ALERTA_VENCIMENTO));
        $this->assertTrue(Settings::ligado(SettingsCatalog::AVISO_ORDENS_ATRASADAS));
        $this->assertSame(0, CompanySetting::query()->anyCompany()->where('company_id', $empresa->id)->count());
        TenantContext::forget();

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('value="250"', false)
            ->assertSee('value="2"', false);
    }

    public function test_o_raio_escolhido_e_o_raio_que_o_checkin_aceita(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'checkin_raio' => '500',
        ]))->assertSessionHas('status');

        TenantContext::set($empresa->id);
        $this->assertSame(500.0, ServiceOrderCheckin::raioAceito());
        $this->assertSame(500, (int) CompanySetting::valueFor(SettingsCatalog::RAIO_CHECKIN));

        // Vazar o número é devolver a casa ao padrão: a linha sai da tabela.
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'checkin_raio' => '',
        ]));

        $this->assertSame(250.0, ServiceOrderCheckin::raioAceito());
        $this->assertDatabaseMissing('company_settings', ['key' => SettingsCatalog::RAIO_CHECKIN]);
        TenantContext::forget();
    }

    public function test_a_janela_e_os_ligamentos_mandam_na_varredura_de_todo_dia(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');

        $this->registroFinanceiro($empresa, now()->addDays(5)->toDateString());

        // Janela padrão (2 dias): o vencimento de quinta-feira ainda não acorda ninguém.
        $this->artisan('nf:notificacoes:diaria')->assertSuccessful();
        $this->semAviso('financeiro.vencendo');

        // A empresa abre a janela para 7 dias: o mesmo fato agora é aviso.
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'financeiro_dias_alerta' => '7',
        ]));
        $this->artisan('nf:notificacoes:diaria')->assertSuccessful();
        $this->temAviso('financeiro.vencendo', $supervisora);

        // Desligar a família silencia o sino — o fato segue existindo, o toque não.
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'financeiro_dias_alerta' => '7',
            'varredura_vencimentos' => null,
        ]));
        Notification::query()->anyCompany()->where('type', 'financeiro.vencendo')->delete();
        $this->artisan('nf:notificacoes:diaria')->assertSuccessful();
        $this->semAviso('financeiro.vencendo');
    }

    public function test_ordem_atrasada_so_acorda_quem_a_empresa_deixar(): void
    {
        Storage::fake('public');
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->ordemAtrasada($empresa);

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'varredura_ordens_atrasadas' => null,
        ]));
        $this->artisan('nf:notificacoes:diaria')->assertSuccessful();
        $this->semAviso('ordem.atrasada');

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario());
        $this->artisan('nf:notificacoes:diaria')->assertSuccessful();
        $this->temAviso('ordem.atrasada', $admin);
    }

    public function test_salvar_sem_mudar_nada_nao_inventa_carimbo(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario())
            ->assertSessionHas('aviso', fn (?string $m): bool => str_contains((string) $m, 'Nada mudou'));

        $this->assertDatabaseMissing('audit_logs', ['action' => 'configurações alteradas']);
    }

    public function test_o_que_muda_sai_carimbado_na_auditoria_campo_a_campo(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'checkin_raio' => '400',
        ]))->assertSessionHas('status');

        $carimbo = AuditLog::query()->where('action', 'configurações alteradas')->sole();
        $this->assertSame('Company', $carimbo->entity_type);
        $this->assertSame($empresa->id, $carimbo->company_id);

        $mudancas = is_string($carimbo->changes) ? json_decode($carimbo->changes, true) : $carimbo->changes;
        $this->assertArrayHasKey(SettingsCatalog::RAIO_CHECKIN, $mudancas);
        $this->assertSame([250, 400], $mudancas[SettingsCatalog::RAIO_CHECKIN]);
    }

    public function test_o_perfil_se_renomeia_mas_a_chave_de_endereco_e_imutavel(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'name' => 'Confeitaria Alfa Renovada',
            'email' => 'escrita@alfa.com.br',
            'website' => 'https://alfa.com.br',
        ]))->assertSessionHas('status');

        $depois = $empresa->fresh();
        $this->assertSame('Confeitaria Alfa Renovada', $depois->name);
        $this->assertSame('escrita@alfa.com.br', $depois->email);
        $this->assertSame('alfa', $depois->slug, 'O slug não está entre as colunas que esta tela escreve.');

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'name' => 'Confeitaria Alfa Renovada',
            'email' => 'nao-tem-arroba',
        ]))->assertSessionHasErrors('email');
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'name' => 'Confeitaria Alfa Renovada',
            'website' => 'ftp://nao.serve',
        ]))->assertSessionHasErrors('website');
    }

    public function test_o_email_do_escritorio_nao_pode_ser_o_da_vizinha_e_o_raio_fora_da_prancha_nao_passa(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');
        $outra = $this->makeUser('administrator', $vizinha, 'b@test.local');

        // A vizinha já mora nesse e-mail: a casa alheia não se muda para lá.
        $this->actingAs($outra)->put(route('settings.update'), $this->formulario([
            'email' => 'casa@bravo.com.br',
        ]))->assertSessionHas('status');

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'email' => 'casa@bravo.com.br',
        ]))->assertSessionHasErrors('email');

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'checkin_raio' => '20',
        ]))->assertSessionHasErrors('checkin_raio');
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'financeiro_dias_alerta' => '90',
        ]))->assertSessionHasErrors('financeiro_dias_alerta');

        $this->assertDatabaseMissing('company_settings', ['company_id' => $empresa->id, 'key' => SettingsCatalog::DIAS_ALERTA_VENCIMENTO]);
    }

    public function test_a_marca_da_empresa_entra_no_arquivo_e_a_anterior_sai_junta(): void
    {
        Storage::fake('public');
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'logo' => UploadedFile::fake()->image('marca.png', 200, 200),
        ]))->assertSessionHas('status');

        $caminho = $empresa->fresh()->logo_path;
        $this->assertNotNull($caminho);
        Storage::disk('public')->assertExists(mb_substr($caminho, strlen('storage/')));

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'logo' => UploadedFile::fake()->image('nova-marca.jpg', 300, 300),
        ]));

        $novo = $empresa->fresh()->logo_path;
        $this->assertNotSame($caminho, $novo);
        Storage::disk('public')->assertMissing(mb_substr($caminho, strlen('storage/')));
        Storage::disk('public')->assertExists(mb_substr($novo, strlen('storage/')));
    }

    public function test_marca_falsa_nao_passa_e_o_svg_fica_do_lado_de_fora_da_casa(): void
    {
        Storage::fake('public');
        [$empresa, $admin] = $this->empresaComAdmin();

        $svgArmado = UploadedFile::fake()->createWithContent(
            'marca.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script src="mal.so"></script></svg>',
            'image/svg+xml',
        );
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario(['logo' => $svgArmado]))
            ->assertSessionHasErrors('logo');
        $this->assertNull($empresa->fresh()->logo_path);

        $falso = UploadedFile::fake()->create('marca.png', 12, 'image/png');
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario(['logo' => $falso]))
            ->assertSessionHasErrors('logo');

        $formiga = UploadedFile::fake()->image('selo.png', 32, 32);
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario(['logo' => $formiga]))
            ->assertSessionHasErrors('logo');

        $pano = UploadedFile::fake()->image('pano.jpg', 3000, 900);
        $this->actingAs($admin)->put(route('settings.update'), $this->formulario(['logo' => $pano]))
            ->assertSessionHasErrors('logo');

        $this->assertNull($empresa->fresh()->logo_path);
        $this->assertSame([], Storage::disk('public')->allFiles('empresas'));
    }

    public function test_a_opiniao_de_uma_empresa_nao_vaza_para_a_janela_da_vizinha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');
        $outra = $this->makeUser('administrator', $vizinha, 'b@test.local');

        $this->actingAs($admin)->put(route('settings.update'), $this->formulario([
            'checkin_raio' => '600',
        ]));

        TenantContext::set($vizinha->id);
        $this->assertSame(250, Settings::valor(SettingsCatalog::RAIO_CHECKIN));
        TenantContext::forget();

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $empresa->id,
            'key' => SettingsCatalog::RAIO_CHECKIN,
        ]);
        $this->assertDatabaseMissing('company_settings', ['company_id' => $vizinha->id]);

        $this->actingAs($outra)->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('value="600"', false);
    }

    /** @return array<string, mixed> O formulário inteiro: perfil + todas as preferências. */
    private function formulario(array $extras = []): array
    {
        $base = [
            'name' => 'Empresa alfa',
            'document' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'checkin_raio' => null,
            'financeiro_dias_alerta' => null,
        ];

        foreach (['varredura_ordens_atrasadas', 'varredura_vencimentos', 'varredura_agenda'] as $chave) {
            $base[$chave] = '1';
        }

        foreach ($extras as $chave => $valor) {
            if ($valor === null) {
                unset($base[$chave]);
            } else {
                $base[$chave] = $valor;
            }
        }

        return $base;
    }

    private function registroFinanceiro(Company $empresa, string $vencimento): FinancialRecord
    {
        TenantContext::set($empresa->id);
        $registro = FinancialRecord::query()->create([
            'type' => 'revenue',
            'category' => 'visita_tecnica',
            'description' => 'Visita técnica de vitrine',
            'amount' => 320,
            'due_date' => $vencimento,
            'occurred_at' => $vencimento,
            'status' => 'pending',
        ]);
        TenantContext::forget();

        return $registro;
    }

    private function ordemAtrasada(Company $empresa): ServiceOrder
    {
        TenantContext::set($empresa->id);
        $cliente = Client::query()->create(['name' => 'Restaurante Vitrine', 'status' => 'active']);
        $ordem = ServiceOrder::query()->create([
            'client_id' => $cliente->id,
            'number' => 'OS-2026-0801',
            'title' => 'Revisão da vitrine refrigerada',
            'priority' => 'normal',
            'status' => 'in_progress',
            'scheduled_ends_at' => now()->subHours(2),
        ]);
        TenantContext::forget();

        return $ordem;
    }

    private function semAviso(string $tipo): void
    {
        $this->assertSame(0, Notification::query()->anyCompany()->where('type', $tipo)->count());
    }

    private function temAviso(string $tipo, User $para): void
    {
        $this->assertGreaterThan(
            0,
            Notification::query()->anyCompany()
                ->where('type', $tipo)->where('user_id', $para->id)->count(),
        );
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    private function conta(Company $empresa, string $papel, string $email): User
    {
        TenantContext::set($empresa->id);
        $usuario = $this->makeUser($papel, $empresa, $email);
        TenantContext::forget();

        return $usuario;
    }
}
