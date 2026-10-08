<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Token de CSRF não se herda do layout nem do componente: cada <form method="POST">
 * escrito à mão precisa emitir o próprio, ou o navegador leva 419 na primeira
 * gravação — e a suíte não percebe o buraco, porque o VerifyCsrfToken se isenta
 * enquanto roda como teste. A guarda é dupla: varre a fonte de toda view e ainda
 * confere o HTML servido, porque @csrf escrito dentro de um bloco que o Blade não
 * executa não salva ninguém.
 */
class CsrfTokenTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_todo_formulário_de_escrita_nas_views_emite_o_próprio_token(): void
    {
        $ofensas = [];
        $vistoriados = 0;

        foreach ($this->views() as $arquivo) {
            $fonte = preg_replace('!\{\{--.*?--\}\}!s', '', str_replace('\\', '/', file_get_contents($arquivo)));

            if (! preg_match_all('/^[ \t]*<form\b[^>]*method="POST"[^>]*>/mi', $fonte, $aberturas, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            foreach ($aberturas as $abertura) {
                $inicio = $abertura[0][1];
                $fechamento = stripos($fonte, '</form>', $inicio);
                $bloco = substr($fonte, $inicio, $fechamento === false ? null : $fechamento - $inicio);
                $vistoriados++;

                if (stripos($bloco, '@csrf') === false) {
                    $ofensas[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $arquivo)
                        .':'.(1 + substr_count(substr($fonte, 0, $inicio), "\n"));
                }
            }
        }

        $this->assertSame([], $ofensas, 'Formulário de escrita sem @csrf devolve 419 no navegador.');
        $this->assertGreaterThanOrEqual(30, $vistoriados, 'A varredura perdeu formulários POST: revise a regex.');
    }

    public function test_o_html_servido_emete_o_token_dentro_de_cada_formulário_que_grava(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        TenantContext::set($empresa->id);
        Product::query()->create([
            'name' => 'Gás R-410a', 'sku' => 'GS-410', 'unit' => 'kg',
            'cost' => 40, 'price' => 95, 'reorder_point' => 2, 'status' => 'active',
        ]);

        $telas = [
            'novo serviço' => route('services.create'),
            'novo cliente' => route('clients.create'),
            'registrar movimentação' => route('movements.create'),
        ];

        foreach ($telas as $rotulo => $url) {
            $html = $this->actingAs($usuario)->get($url)->assertOk()->getContent();

            preg_match_all('~(?is)<form[^>]*method="POST"[^>]*>(.*?)</form>~', $html, $formularios);

            $this->assertNotEmpty($formularios[1], $rotulo.': o HTML não trouxe nenhum formulário POST.');

            foreach ($formularios[1] as $corpo) {
                $this->assertStringContainsString(
                    'name="_token"',
                    $corpo,
                    $rotulo.': formulário servido sem o campo oculto do token.'
                );
            }
        }
    }

    /** @return list<string> */
    private function views(): array
    {
        $lista = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $arquivo) {
            if (str_ends_with($arquivo->getFilename(), '.blade.php')) {
                $lista[] = $arquivo->getPathname();
            }
        }

        $this->assertNotEmpty($lista, 'Nenhuma view foi encontrada para inspecionar.');

        return $lista;
    }

    /** @return array{Company, User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }
}
