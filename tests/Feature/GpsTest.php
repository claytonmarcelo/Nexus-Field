<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * O mapa do campo é uma leitura do que já está gravado: a posição da ficha e a
 * sequência de chegadas e saídas. Nenhum teste aqui aceita que uma coordenada
 * venha de outro técnico ou que a tela mostre a rota de outra empresa.
 */
class GpsTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_o_mapa_mostra_a_posicao_e_a_rota_dos_tecnicos_da_empresa(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $admin = $this->makeUser('administrator', $empresa, 'mapa@test.local');
        $tecnico = $this->tecnico($empresa, 'Técnico A', [
            'latitude' => -23.55,
            'longitude' => -46.63,
            'last_location_at' => now(),
        ]);
        $ordem = $this->ordem($empresa, 'OS-MAPA-0001');
        $this->visita($empresa, $ordem, $tecnico);

        $resposta = $this->actingAs($admin)->get(route('gps.index'))->assertOk();
        $html = $resposta->getContent();
        $tecnicos = $resposta->viewData('tecnicos');

        $this->assertCount(1, $tecnicos);
        $this->assertSame('Técnico A', $tecnicos[0]['nome']);
        $this->assertSame(-23.55, $tecnicos[0]['posicao']['lat']);
        $this->assertCount(1, $tecnicos[0]['rota'], 'A chegada medida tem de virar ponto da rota.');
        $this->assertSame('OS-MAPA-0001', $tecnicos[0]['rota'][0]['ordem']);
        $this->assertStringContainsString('nf-gps-mapa', $html);
        $this->assertStringContainsString('data-nf-gps-dados', $html);
        $this->assertStringContainsString('Técnico A', $html);
    }

    public function test_o_tecnico_enxerga_apenas_a_propria_rota(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $tecnico = $this->tecnico($empresa, 'Técnico A');
        $outro = $this->tecnico($empresa, 'Técnico B');
        $usuario = $this->makeUser('technician', $empresa, 'campo@test.local');
        $tecnico->update(['user_id' => $usuario->id]);

        $tecnicos = $this->actingAs($usuario->fresh())
            ->get(route('gps.index'))
            ->assertOk()
            ->viewData('tecnicos');

        $this->assertCount(1, $tecnicos);
        $this->assertSame('Técnico A', $tecnicos[0]['nome']);
        $this->assertSame($tecnico->id, $tecnicos[0]['id']);
        $this->assertNotSame($outro->id, $tecnicos[0]['id']);
    }

    public function test_a_conta_de_cliente_ve_apenas_quem_passou_pelas_ordens_da_carteira(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $cliente = $this->cliente($empresa, 'Cliente da carteira');
        $alheio = $this->cliente($empresa, 'Cliente de outro');

        $visivel = $this->tecnico($empresa, 'Técnico da carteira');
        $oculto = $this->tecnico($empresa, 'Técnico alheio');
        $this->visita($empresa, $this->ordem($empresa, 'OS-VISIVEL-0001', ['client_id' => $cliente->id]), $visivel);
        $this->visita($empresa, $this->ordem($empresa, 'OS-ALHEIA-0001', ['client_id' => $alheio->id]), $oculto);

        $conta = $this->makeUser('client', $empresa, 'cliente@test.local');
        $conta->forceFill(['client_id' => $cliente->id])->save();
        $tecnicos = $this->actingAs($conta->fresh())->get(route('gps.index'))->assertOk()->viewData('tecnicos');

        $this->assertCount(1, $tecnicos);
        $this->assertSame('Técnico da carteira', $tecnicos[0]['nome']);
    }

    public function test_a_posicao_so_e_escrita_na_ficha_de_quem_esta_logado(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $tecnico = $this->tecnico($empresa, 'Técnico A');
        $usuario = $this->makeUser('technician', $empresa, 'campo@test.local');
        $tecnico->update(['user_id' => $usuario->id]);

        $this->actingAs($usuario->fresh())
            ->postJson(route('gps.localizacao'), [
                'latitude' => -23.55052,
                'longitude' => -46.633308,
                'technician_id' => 999999,
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $tecnico->refresh();
        $this->assertSame('-23.5505200', (string) $tecnico->latitude);
        $this->assertNotNull($tecnico->last_location_at);

        $this->actingAs($this->makeUser('administrator', $empresa, 'escritorio@test.local'))
            ->postJson(route('gps.localizacao'), ['latitude' => 1, 'longitude' => 2])
            ->assertForbidden();
    }

    public function test_coordenada_invalida_e_recusada_e_a_rota_exige_login(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $tecnico = $this->tecnico($empresa, 'Técnico A');
        $usuario = $this->makeUser('technician', $empresa, 'campo@test.local');
        $tecnico->update(['user_id' => $usuario->id]);

        $this->actingAs($usuario->fresh())
            ->postJson(route('gps.localizacao'), ['latitude' => 91, 'longitude' => -200])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertNull($tecnico->fresh()->latitude);
        $middlewares = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->firstWhere('uri', 'gps/localizacao')
            ->gatherMiddleware();
        $this->assertContains('auth', $middlewares);
        $this->assertContains('throttle:30,1', $middlewares);
    }

    /** @param  array<string, mixed>  $extras */
    private function tecnico(Company $empresa, string $nome, array $extras = []): Technician
    {
        return Technician::query()->create($extras + [
            'company_id' => $empresa->id,
            'name' => $nome,
            'status' => 'available',
        ]);
    }

    private function cliente(Company $empresa, string $nome): Client
    {
        return Client::query()->create([
            'company_id' => $empresa->id,
            'name' => $nome,
            'status' => 'active',
        ]);
    }

    /** @param  array<string, mixed>  $extras */
    private function ordem(Company $empresa, string $numero, array $extras = []): ServiceOrder
    {
        return ServiceOrder::query()->create(array_merge([
            'company_id' => $empresa->id,
            'client_id' => $this->cliente($empresa, 'Cliente '.$numero)->id,
            'number' => $numero,
            'title' => 'Ordem '.$numero,
            'status' => 'open',
        ], $extras));
    }

    private function visita(Company $empresa, ServiceOrder $ordem, Technician $tecnico): ServiceOrderCheckin
    {
        return ServiceOrderCheckin::query()->create([
            'company_id' => $empresa->id,
            'service_order_id' => $ordem->id,
            'technician_id' => $tecnico->id,
            'checkin_at' => now()->subHour(),
            'checkin_latitude' => -23.55,
            'checkin_longitude' => -46.63,
            'status' => 'open',
        ]);
    }
}
