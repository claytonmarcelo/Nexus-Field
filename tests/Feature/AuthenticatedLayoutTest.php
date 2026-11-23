<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\CreatesFixtures;
use Tests\TestCase;

class AuthenticatedLayoutTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_convidado_e_mandado_para_o_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_mostra_a_sessao_e_a_empresa_que_existem(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'a@test.local');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Usuário administrator')
            ->assertSee('Empresa alfa')
            ->assertSee('Plano alfa')
            ->assertSee('a@test.local')
            ->assertSee('Permissões no catálogo');

        $this->assertStringContainsString(
            '<strong class="nf-mono">'.Permission::query()->count().'</strong>',
            $response->getContent(),
            'A contagem de permissões na tela tem ser a do banco, não um número escrito no Blade.'
        );
    }

    public function test_contagem_de_usuarios_da_empresa_e_real(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'a@test.local');

        User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Segunda conta',
            'email' => 'outra@test.local',
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertStringContainsString(
            '<span>Usuários com acesso</span> <strong class="nf-mono">2</strong>',
            preg_replace('/\s+/', ' ', $response->getContent())
        );
    }

    public function test_nenhum_link_do_menu_aponta_para_rota_inexistente(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'a@test.local');

        $this->assertNotEmpty(Navigation::for($user), 'O menu administrativo não pode estar vazio.');

        foreach (Navigation::sections() as $section) {
            foreach ($section['items'] as $item) {
                $this->assertTrue(
                    Route::has($item['route']),
                    "O menu anuncia a rota [{$item['route']}], que não existe."
                );

                $this->assertArrayHasKey(
                    $item['permission'],
                    PermissionCatalog::all(),
                    "O menu pede a permissão [{$item['permission']}], que não está no catálogo."
                );
            }
        }
    }

    public function test_todos_os_icones_do_menu_existem_no_fontawesome_instalado(): void
    {
        $css = (string) file_get_contents(
            base_path('node_modules/@fortawesome/fontawesome-free/css/fontawesome.min.css')
        );

        $this->assertNotSame('', $css, 'O CSS do FontAwesome precisou ser instalado para validar os ícones.');

        foreach (Navigation::sections() as $section) {
            foreach ($section['items'] as $item) {
                $classes = array_values(array_filter(
                    explode(' ', $item['icon']),
                    fn (string $class) => str_starts_with($class, 'fa-'),
                ));

                $this->assertNotEmpty($classes, "O item [{$item['label']}] não tem glifo declarado.");

                foreach ($classes as $class) {
                    $this->assertStringContainsString(
                        '.'.$class,
                        $css,
                        "O ícone [{$class}] do item [{$item['label']}] não existe no FontAwesome instalado."
                    );
                }
            }
        }

        $visitas = collect(Navigation::sections())
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('route', 'checkins.index');

        $this->assertSame('fa-solid fa-route', $visitas['icon'],
            'Visitas de campo precisa do glifo de rota, alinhado aos demais itens do menu.');
    }

    public function test_item_sem_permissao_sai_do_menu_e_do_gate(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');

        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Sem clientes',
            'slug' => 'sem-clientes',
            'is_system' => false,
        ]);
        $role->permissions()->attach(Permission::where('slug', 'dashboard.view')->value('id'));

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Conta sem clientes',
            'email' => 'nd@test.local',
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);
        $user = $user->fresh('roles.permissions');

        $this->assertTrue($user->hasPermission('dashboard.view'));
        $this->assertFalse($user->hasPermission('clients.view'));

        $rotulos = collect(Navigation::for($user))
            ->flatMap(fn (array $secao) => array_column($secao['items'], 'label'))
            ->all();
        $this->assertSame(['Dashboard'], $rotulos);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('clients.index'))->assertForbidden();
    }

    public function test_sair_e_um_post_com_csrf_e_derruba_a_sessao(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('supervisor', $company, 's@test.local');

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        $this->assertMatchesRegularExpression(
            '~<form method="POST" action="'.preg_quote(route('logout'), '~').'">~',
            $html
        );
        $this->assertStringContainsString('name="_token"', $html);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('welcome'));
        $this->assertGuest();
    }

    public function test_o_color_mode_do_adminlte_fica_desligado_e_o_menu_de_atalhos_e_em_portugues(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'a@test.local');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('data-lte-color-mode="off"', false)
            ->assertSee('class="hold-transition sidebar-mini sidebar-expand-lg layout-fixed"', false)
            ->assertSee('Ir para o conteúdo', false)
            ->assertSee('Ir para a navegação', false)
            ->assertDontSee('Skip to main content', false)
            ->assertSee('data-lte-toggle="sidebar"', false)
            ->assertSee('data-lte-toggle="treeview"', false)
            // O menu não declara tema próprio: ele herda o `data-bs-theme` do <html>,
            // e é isso que o mantém claro no dia claro.
            ->assertSee('<aside class="app-sidebar">', false);
    }

    /**
     * A pílula de tema é a única chave do projeto e `[data-nf-theme-toggle]` é o
     * contrato que resources/js/nexusfield/theme.js escuta: se o Blade mudar de
     * markup sem isso, o botão para de funcionar em silêncio.
     */
    public function test_chave_de_tema_mantem_o_contrato_do_js_e_o_pe_mostra_a_empresa_da_sessao(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'a@test.local');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-nf-theme-toggle', $html);
        $this->assertStringContainsString('nf-theme-toggle-knob', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('nf-footer-chip-tenant', $html);
        $this->assertStringContainsString('Empresa alfa', $html);
    }

    public function test_rodape_autenticado_assina_o_mesmo_texto_da_pagina_publica(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'a@test.local');

        $publica = $this->get(route('welcome'))->getContent();

        // A marca continua no rodapé público, mas em tinta neutra: o verde fica só
        // no cabeçalho. O pé público traz apenas o texto literal pedido, ao lado
        // da logo; o crédito segue só no rodapé autenticado.
        $this->assertStringContainsString('nf-wordmark-neutro', $publica);
        $this->assertStringNotContainsString('nf-signature-brand', $publica);
        $this->assertStringContainsString('© 2026 NEXUS-FIELD', $publica);
        $this->assertStringNotContainsString('Clayton Marcelo', $publica);

        $autenticada = $this->actingAs($user)->get(route('dashboard'))->getContent();

        $this->assertStringNotContainsString('nf-signature-brand', $autenticada);
        $this->assertStringContainsString('Clayton Marcelo', $autenticada);
        $this->assertStringContainsString('nf-signature', $autenticada);
    }
}
