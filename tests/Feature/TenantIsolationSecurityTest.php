<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * `Role` e `User` não usam `BelongsToCompany`: a coluna company_id existe, mas
 * nenhum escopo global a aplica. Esta classe fecha os pontos onde a interface
 * os listava, e os KPIs que contavam a plataforma inteira.
 */
class TenantIsolationSecurityTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_formularios_nao_listam_papeis_nem_contas_de_outra_empresa(): void
    {
        $this->seedPermissions();
        [$alfa, $bravo] = $this->duasEmpresas();

        $this->actingAs($alfa['admin'])->get(route('organization.positions.create'))
            ->assertOk()
            ->assertSee('Administrator')
            ->assertDontSee('Papel exclusivo Bravo');

        $this->actingAs($alfa['admin'])->get(route('technicians.create'))
            ->assertOk()
            ->assertSee('Administrador Alfa')
            ->assertDontSee('Administrador Bravo');

        $this->actingAs($alfa['admin'])->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Administrador Alfa')
            ->assertDontSee('Administrador Bravo');
    }

    public function test_kpis_de_usuarios_e_papeis_contam_somente_o_tenant_atual(): void
    {
        $this->seedPermissions();
        [$alfa, $bravo] = $this->duasEmpresas();

        TenantContext::set($alfa['empresa']->id);
        $painelAlfa = (new DashboardMetrics($alfa['admin']))->toArray();

        $this->assertSame(1, $this->valorBase($painelAlfa, 'Usuários com acesso'));
        $this->assertSame(1, $this->valorBase($painelAlfa, 'Papéis da empresa'));

        TenantContext::set($bravo['empresa']->id);
        $adminBravo = User::query()->where('email', 'admin-bravo@test.local')->firstOrFail()
            ->fresh('roles.permissions');
        $painelBravo = (new DashboardMetrics($adminBravo))->toArray();

        $this->assertSame(1, $this->valorBase($painelBravo, 'Usuários com acesso'));
        $this->assertSame(2, $this->valorBase($painelBravo, 'Papéis da empresa'));
    }

    /** @return array{0: array{empresa: \App\Models\Company, admin: User}, 1: array{empresa: \App\Models\Company, admin: User}} */
    private function duasEmpresas(): array
    {
        $alfa = $this->makeCompany('tenant-alfa');
        $bravo = $this->makeCompany('tenant-bravo');

        $adminAlfa = $this->makeUser('administrator', $alfa, 'admin-alfa@test.local');
        $adminAlfa->forceFill(['name' => 'Administrador Alfa'])->save();

        $adminBravo = $this->makeUser('administrator', $bravo, 'admin-bravo@test.local');
        $adminBravo->forceFill(['name' => 'Administrador Bravo'])->save();

        Role::query()->create([
            'company_id' => $bravo->id,
            'name' => 'Papel exclusivo Bravo',
            'slug' => 'papel-exclusivo-bravo',
            'is_system' => false,
        ]);

        return [
            ['empresa' => $alfa, 'admin' => $adminAlfa->fresh('roles.permissions')],
            ['empresa' => $bravo, 'admin' => $adminBravo->fresh('roles.permissions')],
        ];
    }

    /** @param array<string, mixed> $painel */
    private function valorBase(array $painel, string $rotulo): int
    {
        foreach ($painel['base'] as $kpi) {
            if ($kpi['label'] === $rotulo) {
                return (int) $kpi['value'];
            }
        }

        $this->fail("KPI “{$rotulo}” não apareceu no painel.");
    }
}
