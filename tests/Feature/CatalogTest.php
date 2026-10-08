<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 11 é o catálogo da empresa aberta: serviço tem preço e tempo, produto tem
 * SKU e saldo, e o saldo não é coluna digitada — é a soma das movimentações com o
 * sinal de cada tipo. O que já cobrou ou já foi movimentado não sai do catálogo,
 * porque o preço praticado naquele trabalho deixaria de ter explicação.
 */
class CatalogTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_o_catalogo_traz_so_o_que_e_da_empresa_aberta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $this->servico($empresa, 'Instalação de câmara fria');
        $invasor = $this->servico($outra, 'Serviço de outra empresa');
        $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410']);
        $this->produto($outra, 'Produto de outra empresa', ['sku' => 'GS-X']);
        $this->categoria($outra, 'Categoria de outra empresa');

        $this->actingAs($usuario)->get(route('services.index'))
            ->assertOk()
            ->assertSee('Instalação de câmara fria')
            ->assertDontSee('Serviço de outra empresa');

        $this->actingAs($usuario)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Gás R-410a')
            ->assertDontSee('Produto de outra empresa');

        // A categoria do vizinho não aparece na tela nem no seletor de serviços.
        $this->actingAs($usuario)->get(route('categories.index'))
            ->assertOk()
            ->assertDontSee('Categoria de outra empresa');

        $this->actingAs($usuario)->get(route('services.show', $invasor))->assertNotFound();
        $this->actingAs($usuario)->get(route('products.edit', [
            'produto' => Product::anyCompany()->firstWhere('sku', 'GS-X'),
        ]))->assertNotFound();
    }

    public function test_filtro_por_busca_situacao_e_categoria_veem_do_banco(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $frio = $this->categoria($empresa, 'Refrigeração');
        $gas = $this->categoria($empresa, 'Gás e aquecedores');

        $camara = $this->servico($empresa, 'Instalação de câmara fria', ['service_category_id' => $frio->id]);
        $this->servico($empresa, 'Troca de resistência', [
            'service_category_id' => $gas->id,
            'status' => 'inactive',
        ]);

        $this->actingAs($usuario)->get(route('services.index', ['busca' => 'resistência']))
            ->assertOk()->assertSee('Troca de resistência')->assertDontSee('Instalação de câmara fria');

        $this->actingAs($usuario)->get(route('services.index', ['situacao' => 'inactive']))
            ->assertOk()->assertSee('Troca de resistência')->assertDontSee('Instalação de câmara fria');

        $porCategoria = $this->actingAs($usuario)->get(route('services.index', ['categoria' => $frio->id]));
        $porCategoria->assertOk()->assertSee('Instalação de câmara fria')->assertDontSee('Troca de resistência');

        // Os seletores são montados com o cadastro gravado, não com lista escrita na tela.
        $this->actingAs($usuario)->get(route('services.index'))
            ->assertSee('Refrigeração')
            ->assertSee('Gás e aquecedores')
            ->assertSee('2 serviços cadastrados');

        $camara->delete();

        $this->actingAs($usuario)->get(route('services.index', ['situacao' => 'excluidos']))
            ->assertOk()->assertSee('Instalação de câmara fria')->assertDontSee('Troca de resistência');

        $this->actingAs($usuario)->get(route('services.index'))
            ->assertOk()->assertDontSee('Instalação de câmara fria');
    }

    public function test_a_paginacao_e_a_contagem_do_catalogo_sao_da_consulta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        TenantContext::set($empresa->id);
        foreach (range(1, 12) as $n) {
            Service::query()->create([
                'name' => 'Serviço '.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'price' => 100,
                'status' => 'active',
            ]);
        }
        TenantContext::forget();

        $primeira = $this->actingAs($usuario)->get(route('services.index', ['por_pagina' => 10]));
        $segunda = $this->actingAs($usuario)->get(route('services.index', ['por_pagina' => 10, 'page' => 2]));

        $primeira->assertOk()->assertSee('Serviço 01')->assertDontSee('Serviço 11');
        $segunda->assertOk()->assertSee('Serviço 11')->assertSee('Serviço 12');
        $primeira->assertSee('12 serviços cadastrados');
    }

    public function test_o_servico_e_gravado_na_empresa_aberta_e_o_nome_só_vale_dentro_dela(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $estranha = $this->makeCompany('bravo');

        $this->servico($empresa, 'Instalação de câmara fria');
        $categoriaAlheia = $this->categoria($estranha, 'Cozinha industrial');

        $this->actingAs($usuario)->post(route('services.store'), [
            'name' => 'Manutenção preventiva',
            'code' => 'SVC-014',
            'price' => 890,
            'estimated_minutes' => 90,
            'status' => 'active',
            'service_category_id' => $categoriaAlheia->id,
        ])->assertSessionHasErrors('service_category_id');

        $this->actingAs($usuario)->post(route('services.store'), [
            'name' => 'Instalação de câmara fria',
            'price' => 1200,
            'status' => 'active',
        ])->assertSessionHasErrors('name');

        $this->actingAs($usuario)->post(route('services.store'), [
            'name' => 'Manutenção preventiva',
            'code' => 'SVC-014',
            'price' => 890,
            'estimated_minutes' => 90,
            'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $servico = Service::anyCompany()->firstWhere('name', 'Manutenção preventiva');
        $this->assertNotNull($servico);
        $this->assertSame($empresa->id, $servico->company_id, 'A empresa vem do contexto, não do formulário.');
        $this->assertSame('890.00', $servico->price);
        $this->assertNull($servico->service_category_id, 'Categoria de outra empresa não entra, mesmo no form.');

        // O mesmo nome em outra empresa é outro catálogo, e o schema permite.
        $this->servico($estranha, 'Manutenção preventiva');
        $this->assertSame(2, Service::anyCompany()->where('name', 'Manutenção preventiva')->count());

        $this->actingAs($usuario)->get(route('services.show', $servico))
            ->assertOk()
            ->assertSee('R$ 890,00')
            ->assertSee('1 h 30 min')
            ->assertSee('SVC-014');

        $servico->update(['estimated_minutes' => 45, 'price' => 910]);
        $this->actingAs($usuario)->get(route('services.show', $servico))
            ->assertOk()->assertSee('45 min')->assertSee('R$ 910,00');
    }

    public function test_a_categoria_nasce_com_slug_do_nome_e_segura_os_servicos_do_grupo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $this->actingAs($usuario)->post(route('categories.store'), ['name' => 'Automação e painel'])
            ->assertSessionHasNoErrors();

        $categoria = ServiceCategory::anyCompany()->firstWhere('slug', 'automacao-e-painel');
        $this->assertNotNull($categoria, 'O slug vem do nome; ninguém digita identificação.');

        $this->actingAs($usuario)->post(route('categories.store'), ['name' => 'Automação e painel'])
            ->assertSessionHasErrors('slug');

        $servico = $this->servico($empresa, 'Programação de CLP', ['service_category_id' => $categoria->id]);

        $lista = $this->actingAs($usuario)->get(route('categories.index'));
        $lista->assertOk()
            ->assertSee('1 categoria cadastrada')
            ->assertSee('1 serviço no catálogo')
            ->assertSee('automacao-e-painel');

        $this->from(route('categories.index'))->actingAs($usuario)
            ->delete(route('categories.destroy', $categoria))
            ->assertRedirect(route('categories.index', ['busca' => 'Automação e painel']))
            ->assertSessionHas('erro');
        $this->assertDatabaseHas('service_categories', ['id' => $categoria->id]);

        // Trocar o serviço de grupo é o caminho que a tela oferece antes de excluir.
        TenantContext::set($empresa->id);
        $servico->update(['service_category_id' => null]);
        TenantContext::forget();

        $this->actingAs($usuario)->delete(route('categories.destroy', $categoria))
            ->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('service_categories', ['id' => $categoria->id]);
    }

    public function test_o_servico_que_ja_cobrou_nao_sai_do_catalogo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $novo = $this->servico($empresa, 'Visita técnica');
        $usado = $this->servico($empresa, 'Instalação de câmara fria');
        $cobrado = $this->servico($empresa, 'Higienização de duto');

        $ordem = $this->ordem($empresa, $usado);
        ServiceOrderItem::query()->create([
            'service_order_id' => $ordem->id,
            'service_id' => $cobrado->id,
            'description' => 'Higienização de duto',
            'quantity' => 1,
            'unit_price' => 320,
        ]);

        foreach ([$usado, $cobrado] as $servico) {
            $this->from(route('services.show', $servico))->actingAs($usuario)
                ->delete(route('services.destroy', $servico))
                ->assertSessionHas('erro');
            $this->assertDatabaseHas('services', ['id' => $servico->id, 'deleted_at' => null]);
        }

        $this->actingAs($usuario)->delete(route('services.destroy', $novo))
            ->assertRedirect(route('services.index'));
        $this->assertSoftDeleted($novo);

        // A prova é a contagem que o banco faz: saiu um, restam dois no catálogo.
        $this->actingAs($usuario)->get(route('services.index'))
            ->assertOk()->assertSee('2 serviços cadastrados');

        $this->actingAs($usuario)->patch(route('services.restore', $novo))
            ->assertRedirect(route('services.show', $novo));
        $this->assertDatabaseHas('services', ['id' => $novo->id, 'deleted_at' => null]);
    }

    public function test_o_sku_e_unico_dentro_da_empresa_e_a_unidade_veio_do_catalogo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $estranha = $this->makeCompany('bravo');

        $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410']);
        $this->produto($estranha, 'Gás equivalente', ['sku' => 'GS-410']);

        $base = [
            'name' => 'Gás R-22',
            'sku' => 'GS-22',
            'unit' => 'kg',
            'cost' => 40,
            'price' => 95,
            'reorder_point' => 2,
            'status' => 'active',
        ];

        $this->actingAs($usuario)->post(route('products.store'), ['sku' => 'GS-410'] + $base)
            ->assertSessionHasErrors('sku');

        // Unidade fora do catálogo do projeto não entra, mesmo vinda do form.
        $this->actingAs($usuario)->post(route('products.store'), ['unit' => 'tonelada'] + $base)
            ->assertSessionHasErrors('unit');

        $this->actingAs($usuario)->post(route('products.store'), ['name' => 'Gás R-22'] + $base)
            ->assertRedirect()->assertSessionHasNoErrors();

        $produto = Product::anyCompany()->firstWhere('sku', 'GS-22');
        $this->assertNotNull($produto);
        $this->assertSame($empresa->id, $produto->company_id);
        $this->assertSame('40.00', $produto->cost);

        // O SKU repetido na outra empresa já existia antes: o cadastro é por tenant.
        $this->assertSame(2, Product::anyCompany()->where('sku', 'GS-410')->count());

        $this->actingAs($usuario)->get(route('products.show', $produto))
            ->assertOk()
            ->assertSee('GS-22')
            ->assertSee('R$ 95,00')
            ->assertSee('Quilograma');
    }

    public function test_o_saldo_central_e_a_soma_das_movimentacoes_com_o_sinal_de_cada_tipo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $gas = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410', 'reorder_point' => 6]);
        $oleo = $this->produto($empresa, 'Óleo refrigerante', ['sku' => 'OL-22', 'reorder_point' => 1]);

        // Entra 10 na compra, sai 4 na carga do técnico, o consumo de 2 não toca o
        // central e o ajuste de inventário baixa 1: 10 - 4 + 0 - 1 = 5.
        $this->movimento($empresa, $gas, 'purchase', 10);
        $this->movimento($empresa, $gas, 'load', 4);
        $this->movimento($empresa, $gas, 'consume', 2);
        $this->movimento($empresa, $gas, 'adjustment', -1);
        $this->movimento($empresa, $oleo, 'purchase', 20);

        $this->assertSame(5.0, $this->saldoCentral($gas), 'O consumo não mexe no estoque central.');
        $this->assertSame(20.0, $this->saldoCentral($oleo));

        $alerta = Product::query()->belowReorderPoint()->get();
        $this->assertSame([$gas->id], $alerta->modelKeys(), 'Só o que caiu do ponto de reposição entra no alerta.');

        $this->actingAs($usuario)->get(route('products.index', ['estoque' => 'abaixo']))
            ->assertOk()->assertSee('Gás R-410a')->assertDontSee('Óleo refrigerante');

        $this->actingAs($usuario)->get(route('products.index', ['estoque' => 'ok']))
            ->assertOk()->assertSee('Óleo refrigerante')->assertDontSee('Gás R-410a');

        $this->actingAs($usuario)->get(route('products.show', $gas))
            ->assertOk()
            ->assertSee('5,00')
            ->assertSee('abaixo do ponto de reposição')
            ->assertSee('Carga para o técnico')
            ->assertSee('Ajuste de inventário');

        // O saldo não é campo de formulário: ninguém digita o que o banco soma.
        $this->actingAs($usuario)->get(route('products.edit', $gas))
            ->assertOk()
            ->assertDontSee('central_balance');
    }

    public function test_a_listagem_de_produtos_filtra_por_unidade_situacao_e_busca(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410', 'unit' => 'kg']);
        $this->produto($empresa, 'Flange 3/4', ['sku' => 'FL-34', 'unit' => 'un', 'status' => 'inactive']);

        $this->actingAs($usuario)->get(route('products.index', ['unidade' => 'kg']))
            ->assertOk()->assertSee('Gás R-410a')->assertDontSee('Flange 3/4');

        $this->actingAs($usuario)->get(route('products.index', ['situacao' => 'inactive']))
            ->assertOk()->assertSee('Flange 3/4')->assertDontSee('Gás R-410a');

        $this->actingAs($usuario)->get(route('products.index', ['busca' => 'FL-34']))
            ->assertOk()->assertSee('Flange 3/4')->assertDontSee('Gás R-410a');

        // O seletor de unidade mostra só as unidades este cadastro realmente usa.
        $this->actingAs($usuario)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Quilograma')
            ->assertDontSee('Mililitro');
    }

    public function test_produto_movimentado_ou_cobrado_continua_no_catalogo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $novo = $this->produto($empresa, 'Filtro de linha', ['sku' => 'FT-10']);
        $movimentado = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410']);
        $cobrado = $this->produto($empresa, 'Flange 3/4', ['sku' => 'FL-34']);

        $this->movimento($empresa, $movimentado, 'purchase', 5);
        $ordem = $this->ordem($empresa);
        ServiceOrderItem::query()->create([
            'service_order_id' => $ordem->id,
            'product_id' => $cobrado->id,
            'description' => 'Flange 3/4',
            'quantity' => 2,
            'unit_price' => 18,
        ]);

        foreach ([$movimentado, $cobrado] as $produto) {
            $this->from(route('products.show', $produto))->actingAs($usuario)
                ->delete(route('products.destroy', $produto))
                ->assertSessionHas('erro');
            $this->assertDatabaseHas('products', ['id' => $produto->id, 'deleted_at' => null]);
        }

        $this->actingAs($usuario)->delete(route('products.destroy', $novo))
            ->assertRedirect(route('products.index'));
        $this->assertSoftDeleted($novo);

        $this->actingAs($usuario)->patch(route('products.restore', $novo))
            ->assertRedirect(route('products.show', $novo));
        $this->assertDatabaseHas('products', ['id' => $novo->id, 'deleted_at' => null]);
    }

    public function test_o_servidor_recusa_quem_nao_tem_a_permissao_do_modulo(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        $tecnico = $this->makeUser('technician', $empresa, 'campo@test.local');
        $funcionario = $this->makeUser('employee', $empresa, 'opc@test.local');
        $gestor = $this->makeUser('supervisor', $empresa, 'gestor@test.local');

        $servico = ['name' => 'Instalação', 'price' => 100, 'status' => 'active'];
        $produto = [
            'name' => 'Gás R-410a', 'sku' => 'GS-410', 'unit' => 'kg',
            'cost' => 40, 'price' => 95, 'reorder_point' => 2, 'status' => 'active',
        ];

        $this->actingAs($tecnico)->get(route('services.index'))->assertForbidden();
        $this->actingAs($tecnico)->post(route('services.store'), $servico)->assertForbidden();
        $this->actingAs($tecnico)->get(route('products.index'))->assertForbidden();
        $this->actingAs($tecnico)->post(route('products.store'), $produto)->assertForbidden();
        $this->actingAs($tecnico)->get(route('categories.index'))->assertForbidden();

        // O funcionário lê o catálogo e não escreve: nem serviço, nem produto, nem categoria.
        $this->actingAs($funcionario)->get(route('services.index'))->assertOk();
        $this->actingAs($funcionario)->get(route('services.create'))->assertForbidden();
        $this->actingAs($funcionario)->get(route('products.index'))->assertOk();
        $this->actingAs($funcionario)->post(route('products.store'), $produto)->assertForbidden();
        $this->actingAs($funcionario)->post(route('categories.store'), ['name' => 'Sem acesso'])->assertForbidden();

        $this->assertSame(0, Service::anyCompany()->count(), 'Recusa no servidor não grava nada.');
        $this->assertSame(0, Product::anyCompany()->count());
        $this->assertSame(0, ServiceCategory::anyCompany()->count());

        // O gestor cria e edita, mas apagar catálogo é do administrador.
        $this->actingAs($gestor)->get(route('services.create'))->assertOk();
        $this->actingAs($gestor)->post(route('services.store'), $servico)->assertSessionHasNoErrors();
        $criado = Service::anyCompany()->firstWhere('name', 'Instalação');
        $this->assertSame($empresa->id, $criado->company_id);
        $this->actingAs($gestor)->delete(route('services.destroy', $criado))->assertForbidden();
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /** @param  array<string, mixed>  $extras */
    private function servico(Company $empresa, string $nome, array $extras = []): Service
    {
        TenantContext::set($empresa->id);
        $servico = Service::query()->create($extras + [
            'name' => $nome,
            'price' => 100,
            'status' => 'active',
        ]);
        TenantContext::forget();

        return $servico;
    }

    private function categoria(Company $empresa, string $nome): ServiceCategory
    {
        TenantContext::set($empresa->id);
        $categoria = ServiceCategory::query()->create(['name' => $nome]);
        TenantContext::forget();

        return $categoria;
    }

    /** @param  array<string, mixed>  $extras */
    private function produto(Company $empresa, string $nome, array $extras = []): Product
    {
        TenantContext::set($empresa->id);
        $produto = Product::query()->create($extras + [
            'name' => $nome,
            'sku' => Str::slug($nome),
            'unit' => 'un',
            'cost' => 10,
            'price' => 25,
            'reorder_point' => 0,
            'status' => 'active',
        ]);
        TenantContext::forget();

        return $produto;
    }

    private function ordem(Company $empresa, ?Service $servico = null): ServiceOrder
    {
        TenantContext::set($empresa->id);

        $cliente = Client::query()->firstOrCreate(
            ['company_id' => $empresa->id, 'name' => 'Padaria Sant’Anna'],
            ['status' => 'active'],
        );

        $ordem = ServiceOrder::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'service_id' => $servico?->id,
            'number' => 'OS-'.str_pad((string) (ServiceOrder::anyCompany()->count() + 1), 4, '0', STR_PAD_LEFT),
            'title' => $servico?->name ?: 'Visita de verificação',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        TenantContext::forget();

        return $ordem;
    }

    private function movimento(Company $empresa, Product $produto, string $tipo, float $quantidade): StockMovement
    {
        TenantContext::set($empresa->id);
        $movimento = StockMovement::query()->create([
            'company_id' => $empresa->id,
            'product_id' => $produto->id,
            'type' => $tipo,
            'quantity' => $quantidade,
            'recorded_at' => now(),
        ]);
        TenantContext::forget();

        return $movimento;
    }

    /** O saldo que a listagem e a ficha desenham, lido pela mesma subquery do banco. */
    private function saldoCentral(Product $produto): float
    {
        $carregado = Product::query()->whereKey($produto->id)->withCentralBalance()->firstOrFail();

        return (float) $carregado->central_balance;
    }
}
