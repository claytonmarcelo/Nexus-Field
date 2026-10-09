<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A Fase 22 é a trilha virando tela. O que se cobra aqui não é enfeite: cada
 * empresa enxerga só os próprios atos, ver é degrau de gestão, exportar é
 * degrau próprio e o CSV sai da MESMA consulta filtrada da listagem. E a ficha
 * tem de devolver o diff como valor legível — tanto para as linhas gravadas de
 * hoje, depois que o `Auditor` parou de codificar duas vezes, quanto para as
 * antigas, que chegaram ao banco já embrulhadas em string JSON.
 */
class AuditTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_ver_a_trilha_e_degrau_de_gestao(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->makeUser('supervisor', $empresa, 'sup@test.local');
        $funcionaria = $this->makeUser('employee', $empresa, 'f@test.local');
        $tecnico = $this->makeUser('technician', $empresa, 't@test.local');

        $this->actingAs($admin)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Auditoria');

        $this->actingAs($supervisora)->get(route('audit.index'))->assertOk();
        $this->actingAs($funcionaria)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($tecnico)->get(route('audit.index'))->assertForbidden();
    }

    public function test_a_trilha_da_casa_vizinha_fica_do_outra_lado_do_muro(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');

        $this->ato($empresa, ['action' => 'ato da casa alfa']);
        $this->ato($vizinha, ['action' => 'ato da casa bravo']);

        $pagina = $this->actingAs($admin)->get(route('audit.index'))->assertOk();

        $pagina->assertSee('ato da casa alfa');
        $pagina->assertDontSee('ato da casa bravo');
    }

    public function test_filtros_de_busca_entidade_quem_e_periodo_refinam_a_trilha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $maria = $this->makeUser('supervisor', $empresa, 'maria@test.local');

        $this->ato($empresa, ['action' => 'cliente criado', 'entity_type' => 'Client', 'user_id' => $maria->id, 'user_name' => 'Maria']);
        $this->ato($empresa, ['action' => 'estoque ajustado', 'entity_type' => 'StockMovement', 'user_id' => $admin->id, 'user_name' => 'Admin', 'created_at' => now()->subDays(10)]);

        // Busca acha pelo texto da ação; a casa não filtra por coluna escondida.
        $this->actingAs($admin)->get(route('audit.index', ['busca' => 'estoque']))
            ->assertSee('estoque ajustado')
            ->assertDontSee('cliente criado');

        $this->actingAs($admin)->get(route('audit.index', ['entidade' => 'Client']))
            ->assertSee('cliente criado')
            ->assertDontSee('estoque ajustado');

        $this->actingAs($admin)->get(route('audit.index', ['quem' => $maria->id]))
            ->assertSee('cliente criado')
            ->assertDontSee('estoque ajustado');

        $esteMes = $this->actingAs($admin)->get(route('audit.index', [
            'inicio' => now()->subDays(1)->toDateString(),
            'fim' => now()->toDateString(),
        ]));
        $esteMes->assertSee('cliente criado');
        $esteMes->assertDontSee('estoque ajustado');
    }

    public function test_a_ficha_devolve_o_diff_como_antes_e_depois(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $ato = $this->ato($empresa, [
            'action' => 'configurações alteradas',
            'entity_type' => 'Company',
            'user_name' => 'Admin',
            'changes' => ['checkin_raio' => [250, 400], 'financeiro_dias_alerta' => [null, 7]],
        ]);

        $this->actingAs($admin)->get(route('audit.show', $ato))
            ->assertOk()
            ->assertSee('checkin_raio')
            ->assertSee('250')
            ->assertSee('400')
            ->assertSee('O que mudou');
    }

    public function test_linha_antiga_com_diff_duplamente_codificado_continua_legivel(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        // O jeito velho de gravar: json_encode na mão sobre uma coluna que já
        // tinha cast `array`. A trilha nova não codifica mais assim, mas o que
        // foi escrito daquele jeito continua legível na tela.
        $ato = $this->ato($empresa, [
            'action' => 'ato do tempo do codifica-duplo',
            'changes' => json_encode(['name' => ['Empresa Velha', 'Empresa Nova']]),
        ]);

        $this->actingAs($admin)->get(route('audit.show', $ato))
            ->assertOk()
            ->assertSee('Empresa Velha')
            ->assertSee('Empresa Nova');
    }

    public function test_ficha_do_ato_alheio_e_quatro_vezes_quatro_nao_existe(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $vizinha = $this->makeCompany('bravo');
        $atoAlheio = $this->ato($vizinha);

        $this->actingAs($admin)->get(route('audit.show', $atoAlheio))->assertNotFound();
    }

    public function test_exportar_e_degrau_proprio_e_o_csv_sai_dos_mesmos_filtros_da_tela(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $funcionaria = $this->makeUser('employee', $empresa, 'f@test.local');
        $vizinha = $this->makeCompany('bravo');

        $this->ato($empresa, ['action' => 'ordem aprovada', 'user_name' => 'Admin']);
        $this->ato($empresa, ['action' => 'estoque ajustado', 'user_name' => 'Admin']);
        $this->ato($vizinha, ['action' => 'ato da casa bravo', 'user_name' => 'Intrusa']);

        $this->actingAs($funcionaria)->get(route('audit.export'))->assertForbidden();

        $csv = $this->actingAs($admin)->get(route('audit.export'))->streamedContent();
        $this->assertStringContainsString('ordem aprovada', $csv);
        $this->assertStringContainsString('estoque ajustado', $csv);
        $this->assertStringNotContainsString('ato da casa bravo', $csv, 'A exportação respeita o tenant.');

        $filtrado = $this->actingAs($admin)->get(route('audit.export', ['busca' => 'ordem aprovada']))->streamedContent();
        $this->assertStringContainsString('ordem aprovada', $filtrado);
        $this->assertStringNotContainsString('estoque ajustado', $filtrado, 'A exportação respeita o filtro da tela.');
    }

    public function test_o_nome_do_autor_congela_no_ato_quem_muda_de_nome_depois(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->ato($empresa, ['action' => 'ato assinado', 'user_id' => $admin->id, 'user_name' => 'Congelado no Ato']);

        $admin->update(['name' => 'Renovado Hoje']);

        // A linha da trilha mostra o nome da hora do ato. O nome de hoje mora
        // no cadastro e no navbar — trocar um não reescreve o outro.
        $this->actingAs($admin)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Congelado no Ato');
    }

    public function test_o_auditor_grava_o_diff_como_array_de_verdade(): void
    {
        // Regressão da FASE 22: o `Auditor` codificava o JSON na mão sobre uma
        // coluna com cast `array` — a leitura voltava string dentro de string.
        // Agora quem codifica é o cast, e a ficha lê array sem malabarismo.
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'Empresa alfa',
            'checkin_raio' => '400',
            'varredura_ordens_atrasadas' => '1',
            'varredura_vencimentos' => '1',
            'varredura_agenda' => '1',
        ])->assertSessionHas('status');

        $carimbo = AuditLog::query()->where('action', 'configurações alteradas')->latest('id')->firstOrFail();

        $this->assertIsArray($carimbo->changes);
        $this->assertSame([250, 400], $carimbo->changes['checkin_raio']);
        $this->assertSame(
            ['checkin_raio' => [250, 400]],
            AuditLog::mudancas($carimbo),
        );
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $admin = $this->makeUser('administrator', $empresa, 'a@test.local');

        return [$empresa, $admin];
    }

    /**
     * Um ato na conta de uma empresa específica. O gancho de criação do modelo
     * obedece ao `TenantContext` — então o ato é escrito com o contexto da
     * casa certa, e a vizinha continua vizinha.
     */
    private function ato(Company $empresa, array $extra = []): AuditLog
    {
        TenantContext::set($empresa->id);

        try {
            return AuditLog::query()->create(array_merge([
                'user_name' => 'Mão na massa',
                'action' => 'ato de teste',
                'created_at' => now(),
            ], $extra));
        } finally {
            TenantContext::forget();
        }
    }
}
