<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Prontidão de deploy: quando a casa responde mal, ela responde com a própria
 * cara. As cinco portas de erro (403, 404, 419, 500, 503) desenham a casca
 * pública em vez da página crua do Symfony, e o 500 não vaza uma linha de
 * stack — nem o nome da exceção que ele mesmo registrou.
 */
class ErrorPagesTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private function semDebug(): void
    {
        // Em produção o .env vem com APP_DEBUG=false; no teste, é aqui que se
        // prova que a tela de erro se veste sozinha, sem depender do whoops.
        $this->app['config']->set('app.debug', false);
    }

    public function test_o_quatro_quatro_nao_e_pagina_crua(): void
    {
        $this->semDebug();

        $pagina = $this->get('/enderecos-que-a-casa-nao-tem')
            ->assertNotFound()
            ->assertSee('Página não encontrada')
            ->assertSee('Entrar na plataforma');

        // A 404 do roteador sabe o nome da rota pedida. A tela não repete.
        $pagina->assertDontSee('The route');
        $pagina->assertDontSee('enderecos-que-a-casa-nao-tem');
    }

    public function test_o_quatro_tres_mostra_a_recusa_sem_espiar_a_stack(): void
    {
        $this->semDebug();

        [, $admin] = $this->empresaComAdminParaErro();
        $funcionaria = $this->makeUser('employee', $admin->company, 'f@test.local');

        $pagina = $this->actingAs($funcionaria)->get(route('audit.index'))
            ->assertForbidden()
            ->assertSee('Acesso negado');

        // O motivo que o servidor escolheu contar chega à tela — foi assim antes
        // da casca própria e continua assim depois dela.
        $pagina->assertSee('Você não tem permissão para esta ação.');

        $pagina->assertDontSee('Symfony');
        $pagina->assertDontSee('Stack trace');
    }

    public function test_o_quatro_um_nove_confirma_que_nada_foi_gravado(): void
    {
        $this->semDebug();

        // Em teste o framework dispensa a conferência de origem (o middleware pula
        // quando a env é `testing`), então o POST sem token passaria reto pelo
        // login. O que se prova aqui é a porta de erro: a mesma exceção que o
        // middleware lança, jogada na cara do manipulador, tem de virar 419
        // vestida — e o texto inglês do framework fica do lado de fora.
        Route::get('__sondagem_419', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $this->get('/__sondagem_419')
            ->assertStatus(419)
            ->assertSee('O formulário expirou')
            ->assertSee('nada foi gravado')
            ->assertDontSee('CSRF token');
    }

    public function test_o_quinhentos_fala_do_lado_de_dentro_sem_vazar_o_motivo(): void
    {
        $this->semDebug();

        Route::get('__sondagem_500', function () {
            throw new RuntimeException('motivo interno que a tela nao pode contar');
        });

        $this->get('/__sondagem_500')
            ->assertServerError()
            ->assertSee('Algo quebrou do lado de dentro')
            ->assertDontSee('motivo interno que a tela nao pode contar')
            ->assertDontSee('RuntimeException');
    }

    public function test_a_manutencao_fica_de_pe_sem_rota_sem_sessao_e_sem_vite(): void
    {
        $this->semDebug();

        Artisan::call('down');

        try {
            $pagina = $this->get('/')->assertStatus(503)
                ->assertSee('A casa está em manutenção');

            // A página da ponte levantada não pode depender de nada que a
            // manutenção derruba: nem asset do Vite, nem url de rota.
            $pagina->assertDontSee('/build/');
            $pagina->assertDontSee('login');
        } finally {
            Artisan::call('up');
        }
    }

    /**
     * O par empresa + administrador que as demais suítes montam, só que com a
     * conta já em mão — aqui não há ato de trilha a semear.
     *
     * @return array{0: Company, 1: User}
     */
    private function empresaComAdminParaErro(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('gama');
        $admin = $this->makeUser('administrator', $empresa, 'a@test.local');

        return [$empresa, $admin];
    }
}
