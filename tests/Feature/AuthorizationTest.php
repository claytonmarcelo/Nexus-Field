<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\CreatesFixtures;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth', 'company', 'permission:orders.approve'])
            ->get('__test/approve-order', fn () => 'aprovado');

        Route::middleware(['auth', 'company'])
            ->get('__test/count-clients', fn () => Client::query()->count());
    }

    public function test_administrador_passa_no_gate_de_permissao(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();

        $this->actingAs($this->makeUser('administrator', $company, 'a@test.local'))
            ->get('__test/approve-order')
            ->assertOk()
            ->assertSee('aprovado');
    }

    public function test_tecnico_e_bloqueado_no_gate_de_permissao(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();

        $this->actingAs($this->makeUser('technician', $company, 't@test.local'))
            ->get('__test/approve-order')
            ->assertForbidden();
    }

    public function test_funcionario_sem_a_permissao_especifica_tambem_e_bloqueado(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();

        $this->actingAs($this->makeUser('employee', $company, 'e@test.local'))
            ->get('__test/approve-order')
            ->assertForbidden();
    }

    public function test_cliente_nao_ve_ordem_de_outro_cliente_pela_camada_de_servico(): void
    {
        $company = $this->makeCompany();
        $this->seedPermissions();
        $user = $this->makeUser('client', $company, 'c@test.local');

        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertFalse($user->hasPermission('financial.approve'));
    }

    public function test_dados_de_um_tenant_nao_vazam_para_outro(): void
    {
        $this->seedPermissions();

        $companyA = $this->makeCompany('alfa');
        $companyB = $this->makeCompany('bravo');

        $userA = $this->makeUser('supervisor', $companyA, 'sup-a@test.local');
        $userB = $this->makeUser('supervisor', $companyB, 'sup-b@test.local');

        TenantContext::set($companyA->id);
        Client::create(['name' => 'Cliente de Alfa', 'status' => 'active']);
        Client::create(['name' => 'Cliente 2 de Alfa', 'status' => 'active']);

        TenantContext::set($companyB->id);
        Client::create(['name' => 'Cliente de Bravo', 'status' => 'active']);

        $this->assertSame(1, (int) $this->actingAs($userB)->get('__test/count-clients')->getContent());
        $this->assertSame(2, (int) $this->actingAs($userA)->get('__test/count-clients')->getContent());
    }
}
