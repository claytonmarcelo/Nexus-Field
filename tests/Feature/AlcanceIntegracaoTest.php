<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employment;
use App\Models\OrganizationScope;
use App\Models\User;
use App\Support\Alcance;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A política não pode ficar só na camada de modelo: folha de contas e trilha de
 * auditoria são leituras com alcance próprio. Estes testes fecham a porta na
 * tela e na ficha, com a MESMA régua da listagem.
 */
class AlcanceIntegracaoTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    /** @return array{0: Company, 1: User, 2: User, 3: User, 4: User} */
    private function empresaComGestor(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alcance-http');
        $admin = $this->makeUser('administrator', $empresa, 'admin@test.local');
        $gestor = $this->makeUser('supervisor', $empresa, 'gestor@test.local');
        $daEquipe = $this->makeUser('employee', $empresa, 'equipe@test.local');
        $fora = $this->makeUser('technician', $empresa, 'fora@test.local');

        TenantContext::set($empresa->id, $empresa);

        try {
            $filial = Branch::create([
                'company_id' => $empresa->id, 'name' => 'Matriz',
                'slug' => 'matriz', 'type' => 'matriz',
            ]);
            $setor = Department::create([
                'company_id' => $empresa->id, 'branch_id' => $filial->id,
                'name' => 'Operacao', 'slug' => 'operacao',
            ]);

            foreach ([$gestor, $daEquipe] as $usuario) {
                Employment::create([
                    'company_id' => $empresa->id,
                    'user_id' => $usuario->id,
                    'branch_id' => $filial->id,
                    'department_id' => $setor->id,
                    'employee_code' => 'HTTP-'.$usuario->id,
                    'status' => 'ativo',
                    'started_at' => now()->toDateString(),
                ]);
            }

            OrganizationScope::create([
                'company_id' => $empresa->id,
                'user_id' => $gestor->id,
                'scope' => Alcance::SETOR,
                'scope_id' => $setor->id,
            ]);
        } finally {
            TenantContext::forget();
        }

        return [$empresa, $admin, $gestor, $daEquipe, $fora];
    }

    private function ato(Company $empresa, array $extra): AuditLog
    {
        TenantContext::set($empresa->id, $empresa);

        try {
            return AuditLog::query()->create(array_merge([
                'user_name' => 'Autor de teste',
                'action' => 'ato de teste',
                'created_at' => now(),
            ], $extra));
        } finally {
            TenantContext::forget();
        }
    }

    public function test_folha_de_contas_do_gestor_so_traz_quem_esta_no_alcance(): void
    {
        [, $admin, $gestor, $daEquipe, $fora] = $this->empresaComGestor();

        $this->actingAs($gestor)->get(route('users.index'))
            ->assertOk()
            ->assertSee($daEquipe->email)
            ->assertDontSee($fora->email);

        // Sem estrutura nova, o administrador continua vendo a empresa inteira.
        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee($fora->email);
    }

    public function test_trilha_do_gestor_so_traz_atos_do_alcance_e_fecha_a_ficha(): void
    {
        [$empresa, , $gestor, $daEquipe, $fora] = $this->empresaComGestor();

        $dentro = $this->ato($empresa, [
            'user_id' => $daEquipe->id,
            'user_name' => 'Da equipe',
            'action' => 'ato dentro do alcance',
        ]);
        $foraDoAlcance = $this->ato($empresa, [
            'user_id' => $fora->id,
            'user_name' => 'Fora da equipe',
            'action' => 'ato fora do alcance',
        ]);

        $this->actingAs($gestor)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('ato dentro do alcance')
            ->assertDontSee('ato fora do alcance');

        $this->actingAs($gestor)->get(route('audit.show', $dentro))->assertOk();
        $this->actingAs($gestor)->get(route('audit.show', $foraDoAlcance))->assertNotFound();
    }
}
