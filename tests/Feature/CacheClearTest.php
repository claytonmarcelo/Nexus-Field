<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Permission;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * O botão de cache é global no sentido de alcançar a instalação inteira — e é
 * exatamente por isso que o administrador de uma empresa não o usa. O `*` do
 * papel dele vale dentro do tenant; cache compilado, rotas e views são
 * compartilhados por todas as empresas, então a porta é a conta raiz.
 */
class CacheClearTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_conta_raiz_limpa_os_caches_e_deixa_o_ato_na_auditoria(): void
    {
        Cache::put('nf-cache-de-teste', 'valor antigo', 60);
        $raiz = $this->raiz();

        $this->actingAs($raiz)
            ->post(route('system.cache.clear'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull(Cache::get('nf-cache-de-teste'),
            'O comando global não removeu o valor guardado no cache.');
        $this->assertStringContainsString('Cache limpo', (string) session('status'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cache limpa',
            'entity_type' => 'Aplicação',
            'user_id' => $raiz->id,
        ]);
    }

    public function test_limpar_cache_nao_apaga_nenhuma_informacao_escrita_da_empresa_ou_do_cliente(): void
    {
        $raiz = $this->raiz();
        $empresa = $raiz->company;

        $cliente = Client::create([
            'company_id' => $empresa->id,
            'name' => 'Cliente permanente',
            'document' => '00000000000191',
            'status' => 'active',
        ]);
        $ordem = ServiceOrder::create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'number' => 'OS-PERMANENTE-0001',
            'title' => 'Ordem que nao pode desaparecer',
            'description' => 'Relato escrito pelo escritorio antes da limpeza.',
            'status' => 'open',
        ]);

        $this->actingAs($raiz)
            ->post(route('system.cache.clear'))
            ->assertRedirect();

        $this->assertDatabaseHas('companies', [
            'id' => $empresa->id,
            'name' => $empresa->name,
        ]);
        $this->assertDatabaseHas('clients', [
            'id' => $cliente->id,
            'name' => 'Cliente permanente',
        ]);
        $this->assertDatabaseHas('service_orders', [
            'id' => $ordem->id,
            'number' => 'OS-PERMANENTE-0001',
        ]);
        $this->assertSame(
            'Relato escrito pelo escritorio antes da limpeza.',
            $ordem->fresh()->description,
            'Limpar artefatos compilados nao pode apagar texto escrito na ordem.'
        );
    }

    public function test_o_administrador_de_uma_empresa_nao_limpa_o_cache_global(): void
    {
        Cache::put('nf-cache-de-teste', 'valor antigo', 60);
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->post(route('system.cache.clear'))
            ->assertForbidden();

        $this->assertSame('valor antigo', Cache::get('nf-cache-de-teste'),
            'Uma recusa 403 não pode ter apagado o cache das outras empresas.');
        $this->assertDatabaseMissing('audit_logs', ['action' => 'cache limpa']);
    }

    public function test_quem_nao_esta_logado_nao_alcanca_a_rota_e_a_rota_nao_aceita_get(): void
    {
        $this->post(route('system.cache.clear'))->assertRedirect(route('login'));
        $this->get(route('system.cache.clear'))->assertMethodNotAllowed();
    }

    public function test_o_botao_so_aparece_para_a_raiz_e_vem_com_confirmacao_e_csrf(): void
    {
        $url = route('system.cache.clear');

        $htmlRaiz = $this->actingAs($this->raiz())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($url, $htmlRaiz);
        $this->assertStringContainsString('Limpar cache', $htmlRaiz);
        $this->assertStringContainsString('data-nf-confirm', $htmlRaiz);
        $this->assertStringContainsString('name="_token"', $htmlRaiz);
        $this->assertStringContainsString('btn btn-ghost btn-sm', $htmlRaiz,
            'O botão precisa sair do x-ui.button, não de uma peça paralela.');
        $this->assertStringContainsString('fa-broom', $htmlRaiz);
        $this->assertStringContainsString('nf-cache-toggle', $htmlRaiz);
        $this->assertStringContainsString('Nenhum dado de empresa, cliente, ordem ou arquivo será apagado', $htmlRaiz,
            'O modal precisa dizer explicitamente que nenhum dado de negócio é tocado.');

        $htmlAdmin = $this->actingAs($this->administrador())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($url, $htmlAdmin,
            'O `*` da empresa vazou para uma ação que não é dela.');
        $this->assertStringNotContainsString('Limpar cache', $htmlAdmin);
    }

    private function raiz(): User
    {
        $empresa = $this->makeCompany();
        $this->semearPermissoesUmaVez();

        $usuario = $this->makeUser('administrator', $empresa, 'raiz-teste@nexusfield.local');
        $usuario->forceFill(['is_root' => true])->save();

        return $usuario->fresh();
    }

    private function administrador(): User
    {
        $empresa = $this->makeCompany('outra-empresa');
        $this->semearPermissoesUmaVez();

        return $this->makeUser('administrator', $empresa, 'admin-teste@nexusfield.local');
    }

    private function semearPermissoesUmaVez(): void
    {
        if (Permission::query()->doesntExist()) {
            $this->seedPermissions();
        }
    }
}
