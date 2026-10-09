<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\ServiceOrderStatusHistory;
use App\Models\Technician;
use App\Models\User;
use App\Services\Orders\FluxoDeOrdem;
use App\Services\Orders\RegistroDePresenca;
use App\Services\Recusa;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A camada de serviço é a escrita; a tela é a fachada.
 *
 * O desenho pedido — interface → serviço → backend → dados — só vale se o degrau do
 * meio for o dono da caneta. Estes testes chamam o serviço sem passar por HTTP, e
 * depois olham para o controller: quem abre transação, troca estado e grava carimbo
 * é o serviço, e a apresentação não escreve nada. É a trava contra o atalho de
 * sempre — no dia em que alguém pôr `DB::transaction` de volta num controller, ou
 * mover a regra de recusa para o HTML, este arquivo para de passar.
 */
class CamadaDeServicosTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_o_servico_barra_o_salto_e_nao_deixa_rastro_na_ficha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ordem = $this->ordem($empresa, 'OS-2026-0401', 'Instalação que ainda não começou');

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            app(FluxoDeOrdem::class)->mudarStatus($ordem, $admin, 'completed');
            $this->fail('O serviço deixou a ordem pular de aberta para concluída.');
        } catch (Recusa $recusa) {
            $this->assertSame('erro', $recusa->tom());
            $this->assertStringContainsString('não pode ir para', $recusa->getMessage());
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('open', $ordem->fresh()->status);
        $this->assertSame(0, ServiceOrderStatusHistory::query()->where('to_status', 'completed')->count());
    }

    public function test_o_passo_que_pediu_aprovacao_volta_como_recusa_e_nao_como_500(): void
    {
        [$empresa, $campo] = $this->empresaComTecnico();
        $ordem = $this->ordem($empresa, 'OS-2026-0402', 'Ordem de rascunho do campo', ['status' => 'draft']);

        $this->actingAs($campo);
        TenantContext::set($empresa->id);

        try {
            app(FluxoDeOrdem::class)->mudarStatus($ordem, $campo, 'open');
            $this->fail('Quem não tem a aprovação tirou um rascunho do papel.');
        } catch (Recusa $recusa) {
            $this->assertStringContainsString('permissão de aprovação', $recusa->getMessage());
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('draft', $ordem->fresh()->status);
    }

    public function test_a_chegada_do_servico_abre_a_execucao_pela_passagem_e_pelo_carimbo(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $ordem = $this->ordem($empresa, 'OS-2026-0403', 'Reparo no balcão frio', [
            'technician_id' => $ana->id,
            'latitude' => -23.55052,
            'longitude' => -46.633308,
        ]);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $visita = app(RegistroDePresenca::class)->chega($ordem, $ana, $admin, [
                'latitude' => -23.5506,
                'longitude' => -46.6333,
                'observacao' => 'Equipamento desligado na tomada.',
            ]);
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('open', $visita->status);
        $this->assertSame('in_progress', $ordem->fresh()->status);
        $this->assertNotNull($visita->checkin_distance);

        $passagem = ServiceOrderStatusHistory::query()
            ->where('service_order_id', $ordem->id)
            ->where('to_status', 'in_progress')
            ->sole();

        $this->assertSame('Chegada registrada em campo pelo check-in.', $passagem->note);
        $this->assertSame($admin->id, $passagem->user_id);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ServiceOrderCheckin',
            'entity_id' => (string) $visita->id,
            'action' => 'chegada em campo',
            'user_id' => $admin->id,
        ]);
    }

    public function test_a_segunda_chegada_e_aviso_de_quem_ja_esta_la_e_nao_duplicata(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $ordem = $this->ordem($empresa, 'OS-2026-0404', 'Manutenção em andamento', ['technician_id' => $ana->id]);

        $servico = app(RegistroDePresenca::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $servico->chega($ordem, $ana, $admin, []);

            try {
                $servico->chega($ordem, $ana, $admin, []);
                $this->fail('A segunda chegada passou como se fosse a primeira.');
            } catch (Recusa $recusa) {
                $this->assertSame('aviso', $recusa->tom());
                $this->assertStringContainsString('já está em campo', $recusa->getMessage());
            }
        } finally {
            TenantContext::forget();
        }

        $this->assertSame(1, ServiceOrderCheckin::query()->count());
    }

    public function test_a_saida_repetida_devolve_aviso_e_preserva_o_primeiro_carimbo(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $ordem = $this->ordem($empresa, 'OS-2026-0405', 'Vitrine que voltou a gelar', ['technician_id' => $ana->id]);

        $servico = app(RegistroDePresenca::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $visita = $servico->chega($ordem, $ana, $admin, []);
            $servico->sai($visita, $ordem, ['observacao' => 'Cliente assinou a ordem.']);
            $saida = $visita->fresh()->checkout_at->toDateTimeString();

            try {
                $servico->sai($visita->fresh(), $ordem->fresh(), ['observacao' => 'Riscando o que já aconteceu']);
                $this->fail('A saída repetida reescreveu o carimbo.');
            } catch (Recusa $recusa) {
                $this->assertSame('aviso', $recusa->tom());
            }
        } finally {
            TenantContext::forget();
        }

        $atualizada = $visita->fresh();

        $this->assertSame($saida, $atualizada->checkout_at->toDateTimeString());
        $this->assertSame('Cliente assinou a ordem.', $atualizada->observation);
        $this->assertSame('closed', $atualizada->status);
    }

    public function test_a_fachada_apresenta_e_o_servico_escreve(): void
    {
        $ordens = File::get(base_path('app/Http/Controllers/Orders/OrderController.php'));
        $chegadas = File::get(base_path('app/Http/Controllers/Orders/CheckinController.php'));

        // Nenhum dos dois abre transação, cria linha, grava passagem de estado,
        // toca sino nem escreve na trilha: quem faz isso é o serviço, e a tela só
        // pede e traduz a resposta. Chamar `$this->fluxo->mudarStatus()` daqui é o
        // pedido; escrever o estado é de `FluxoDeOrdem`.
        foreach (['DB::transaction', 'ServiceOrderStatusHistory', 'Auditor::gravar', 'Notifier::', 'ServiceOrder::create', 'ServiceOrderCheckin::query()->create'] as $proibida) {
            $this->assertStringNotContainsString($proibida, $ordens, "A escrita {$proibida} voltou para o controller de ordens.");
            $this->assertStringNotContainsString($proibida, $chegadas, "A escrita {$proibida} voltou para o controller de chegadas.");
        }

        $this->assertStringContainsString('FluxoDeOrdem', $ordens);
        $this->assertStringContainsString('RegistroDePresenca', $chegadas);
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComTecnico(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('beta');

        return [$empresa, $this->makeUser('technician', $empresa, 'campo@test.local')];
    }

    /** @param  array<string, mixed>  $extras */
    private function ordem(Company $empresa, string $numero, string $titulo, array $extras = []): ServiceOrder
    {
        if (! array_key_exists('client_id', $extras)) {
            $extras['client_id'] = $this->cliente($empresa, 'Cliente '.$numero)->id;
        }

        TenantContext::set($empresa->id);
        $ordem = ServiceOrder::query()->create(array_merge([
            'company_id' => $empresa->id,
            'number' => $numero,
            'title' => $titulo,
            'priority' => 'normal',
            'status' => 'open',
        ], $extras));
        TenantContext::forget();

        return $ordem;
    }

    /** @param  array<string, mixed>  $extras */
    private function cliente(Company $empresa, string $nome, array $extras = []): Client
    {
        TenantContext::set($empresa->id);
        $cliente = Client::query()->create($extras + ['name' => $nome, 'status' => 'active']);
        TenantContext::forget();

        return $cliente;
    }

    /** @param  array<string, mixed>  $extras */
    private function tecnico(Company $empresa, string $nome, array $extras = []): Technician
    {
        TenantContext::set($empresa->id);
        $tecnico = Technician::query()->create($extras + ['name' => $nome, 'status' => 'available']);
        TenantContext::forget();

        return $tecnico;
    }
}
