<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_sem_contexto_de_tenant_consolas_enxergam_todas_as_empresas(): void
    {
        $alfa = $this->makeCompany('alfa');
        $bravo = $this->makeCompany('bravo');

        TenantContext::forget();
        Client::create(['company_id' => $alfa->id, 'name' => 'Cliente alfa', 'status' => 'active']);
        Client::create(['company_id' => $bravo->id, 'name' => 'Cliente bravo', 'status' => 'active']);

        $this->assertSame(2, Client::query()->count());
    }

    public function test_contexto_filtra_os_registros_pela_empresa_atual(): void
    {
        $alfa = $this->makeCompany('alfa');
        $bravo = $this->makeCompany('bravo');

        TenantContext::set($alfa->id);
        Client::create(['name' => 'Cliente alfa', 'status' => 'active']);

        TenantContext::set($bravo->id);
        Client::create(['name' => 'Cliente bravo', 'status' => 'active']);
        Client::create(['name' => 'Cliente bravo 2', 'status' => 'active']);

        $this->assertSame(['Cliente bravo', 'Cliente bravo 2'], Client::query()->pluck('name')->all());
    }

    public function test_novo_registrado_recebe_a_empresa_do_contexto_sem_informar_id(): void
    {
        $company = $this->makeCompany('alfa');
        TenantContext::set($company->id);

        $client = Client::create(['name' => 'Cliente automático', 'status' => 'active']);

        $this->assertSame($company->id, $client->company_id);
    }

    public function test_any_company_permite_a_consulta_transversal_de_uso_interno(): void
    {
        $alfa = $this->makeCompany('alfa');
        $bravo = $this->makeCompany('bravo');

        TenantContext::set($alfa->id);
        Client::create(['name' => 'Cliente alfa', 'status' => 'active']);

        TenantContext::set($bravo->id);
        Client::create(['name' => 'Cliente bravo', 'status' => 'active']);

        $this->assertSame(2, Client::anyCompany()->count());
    }

    public function test_um_tenant_nao_consegue_escrever_na_empresa_de_outro_pelo_id(): void
    {
        $alfa = $this->makeCompany('alfa');
        $bravo = $this->makeCompany('bravo');

        TenantContext::set($alfa->id);
        $client = Client::create(['name' => 'Tentativa de invasão', 'status' => 'active', 'company_id' => $bravo->id]);

        $this->assertSame($alfa->id, $client->company_id, 'O contexto não pode ser sobrescrito pelo request.');
    }
}
