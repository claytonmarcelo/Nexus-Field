<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * O usuário definiu quem pode apagar informação escrita: admin/diretor
 * (`administrator`) e gerente/coordenador (`supervisor`). Os demais papéis não
 * recebem nenhum verbo de exclusão, e toda tela destrutiva abre um modal com
 * texto próprio — nunca um `confirm()` cru do navegador.
 */
class ExclusaoResponsavelTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_todos_os_verbos_de_exclusao_sao_dos_papeis_de_gestao(): void
    {
        $deletes = collect(PermissionCatalog::all())
            ->filter(fn (array $meta) => $meta['action'] === 'delete')
            ->keys()
            ->all();

        $this->assertNotEmpty($deletes);

        $administrador = PermissionCatalog::byRole('administrator');
        $supervisor = PermissionCatalog::byRole('supervisor');

        $this->assertContains('*', $administrador);

        foreach ($deletes as $slug) {
            $this->assertContains($slug, $supervisor, "O supervisor precisa de {$slug}.");
        }

        foreach (['employee', 'technician', 'client'] as $papel) {
            $permitidos = PermissionCatalog::byRole($papel);

            foreach ($deletes as $slug) {
                $this->assertNotContains($slug, $permitidos, "O papel {$papel} não pode excluir por {$slug}.");
            }
        }
    }

    public function test_toda_exclusao_de_interface_tem_modal_e_texto_personalizado(): void
    {
        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        $formularios = 0;

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $codigo = (string) file_get_contents($arquivo->getPathname());
            $padrao = '/<x-ui\.action-form\b[\s\S]*?\/>/';

            preg_match_all($padrao, $codigo, $encontrados);

            foreach ($encontrados[0] as $formulario) {
                if (! str_contains($formulario, 'rotulo="Excluir"')) {
                    continue;
                }

                $formularios++;
                $this->assertStringContainsString(
                    'texto=',
                    $formulario,
                    'Todo botão Excluir precisa de texto próprio no modal: '.$arquivo->getFilename()
                );
                $this->assertStringContainsString(
                    'data-confirm-titulo',
                    (string) file_get_contents(resource_path('views/components/ui/action-form.blade.php')),
                    'O componente de ação precisa abrir o modal do design system.'
                );
            }
        }

        $this->assertGreaterThan(10, $formularios, 'A varredura não pode passar sem encontrar os formulários.');
    }

    public function test_gestor_exclui_cliente_com_historico_limpo_e_funcionario_nao(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $gestor = $this->makeUser('supervisor', $empresa, 'gestor@test.local');
        $funcionario = $this->makeUser('employee', $empresa, 'funcionario@test.local');
        $cliente = Client::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Cliente sem histórico',
            'status' => 'active',
        ]);

        $html = $this->actingAs($gestor)->get(route('clients.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-confirm-titulo="Excluir '.$cliente->name.'?"', $html);
        $this->assertStringContainsString(
            'data-confirm-texto="Só é possível excluir quem ainda não gerou ordem, chamado ou lançamento. Inativar preserva o histórico."',
            $html,
            'O modal do cliente precisa dizer o que impede a exclusão.'
        );

        $this->actingAs($funcionario)->delete(route('clients.destroy', $cliente))->assertForbidden();
        $this->assertDatabaseHas('clients', ['id' => $cliente->id]);

        $this->actingAs($gestor)->delete(route('clients.destroy', $cliente))
            ->assertRedirect(route('clients.index'));
        $this->assertSoftDeleted('clients', ['id' => $cliente->id]);
    }
}
