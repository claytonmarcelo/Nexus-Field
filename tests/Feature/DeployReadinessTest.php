<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guardrails do deploy na Vercel: o cron precisa existir, ser rigorosamente
 * autenticado e casar com o manifesto, e a configuração não pode depender de
 * segredo escrito em arquivo versionado.
 */
class DeployReadinessTest extends TestCase
{
    /** @return array<string, string> */
    private function autorizarCron(): array
    {
        config()->set('deploy.cron_secret', 'segredo-de-teste');

        return ['Authorization' => 'Bearer segredo-de-teste'];
    }

    public function test_rota_do_cron_dispara_o_agendador_com_segredo_valido(): void
    {
        $headers = $this->autorizarCron();
        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);

        $this->get('/cron/schedule', $headers)
            ->assertOk()
            ->assertSee('OK');
    }

    public function test_cron_sem_segredo_configurado_falha_fechada(): void
    {
        config()->set('deploy.cron_secret', null);
        Log::spy();

        $this->get('/cron/schedule')->assertStatus(401);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_cron_com_token_errado_e_recusado_e_registrado(): void
    {
        $this->autorizarCron();
        Log::spy();
        Artisan::spy();

        $this->get('/cron/schedule', ['Authorization' => 'Bearer errado'])
            ->assertStatus(401);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $mensagem, array $contexto): bool => $mensagem === 'cron.schedule.recusado'
                && $contexto['motivo'] === 'credencial_invalida');
        Artisan::shouldNotHaveReceived('call');
    }

    public function test_cron_exige_o_formato_authorization_bearer(): void
    {
        $this->autorizarCron();

        $this->get('/cron/schedule', ['Authorization' => 'segredo-de-teste'])
            ->assertStatus(401);
    }

    public function test_cron_tem_limite_de_taxa_por_ip(): void
    {
        $headers = $this->autorizarCron();
        Artisan::shouldReceive('call')->andReturn(0);

        for ($i = 0; $i < 10; $i++) {
            $this->get('/cron/schedule', $headers)->assertOk();
        }

        $this->get('/cron/schedule', $headers)->assertStatus(429);
    }

    public function test_manifesto_da_vercel_casa_com_a_rota_do_cron(): void
    {
        $manifesto = json_decode(
            (string) file_get_contents(base_path('vercel.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('container', $manifesto['framework']);
        $this->assertSame(['gru1', 'iad1', 'fra1'], $manifesto['regions']);
        $this->assertSame('/cron/schedule', $manifesto['crons'][0]['path']);
        $this->assertSame('0 10 * * *', $manifesto['crons'][0]['schedule']);
        $this->assertTrue(Route::has('cron.schedule'));
    }
}
