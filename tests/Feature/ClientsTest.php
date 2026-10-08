<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A Fase 9 só fecha quando o CRUD, o filtro, a página e a permissão obedecem ao
 * banco desta empresa — não ao que a tela mostra. Estes testes conferem os dois
 * lados: o que aparece na listagem e o que o servidor aceita gravar.
 */
class ClientsTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_listagem_traz_so_os_clientes_da_empresa_aberta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $this->cliente($empresa, 'Padaria Sant’Anna');
        $this->cliente($empresa, 'Mercado do Zé');
        $this->cliente($outra, 'Cliente de outra empresa');

        $resposta = $this->actingAs($usuario)->get(route('clients.index'));

        $resposta->assertOk()
            ->assertSee('Padaria Sant’Anna')
            ->assertSee('Mercado do Zé')
            ->assertDontSee('Cliente de outra empresa');
    }

    public function test_a_busca_procura_nome_fantasia_documento_telefone_no_banco(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $this->cliente($empresa, 'Padaria Sant’Anna', ['document' => '12.345.678/0001-90']);
        $this->cliente($empresa, 'Mercado do Zé', ['document' => '98.765.432/0001-10']);

        $resposta = $this->actingAs($usuario)->get(route('clients.index', ['busca' => 'sant’anna']));

        $resposta->assertOk()->assertSee('Padaria Sant’Anna')->assertDontSee('Mercado do Zé');
    }

    public function test_filtro_por_cidade_ve_de_endereco_gravado_e_nao_de_texto_da_tela(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $saoPaulo = $this->cliente($empresa, 'Padaria Sant’Anna');
        $campinas = $this->cliente($empresa, 'Mercado do Zé');

        $this->endereco($empresa, $saoPaulo, ['city' => 'São Paulo', 'state' => 'SP']);
        $this->endereco($empresa, $campinas, ['city' => 'Campinas', 'state' => 'SP']);

        $pagina = $this->actingAs($usuario)->get(route('clients.index', ['cidade' => 'Campinas']));

        $pagina->assertOk()->assertSee('Mercado do Zé')->assertDontSee('Padaria Sant’Anna');

        // O select de cidade é montado com as cidades reais, não com uma lista fixa.
        $this->actingAs($usuario)->get(route('clients.index'))
            ->assertOk()->assertSee('São Paulo')->assertSee('Campinas');
    }

    public function test_o_filtro_de_situacao_mostra_so_quem_esta_na_quela_situacao(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $this->cliente($empresa, 'Padaria Sant’Anna');
        $this->cliente($empresa, 'Obra parada', ['status' => 'inactive']);

        // Sem filtro a carteira é a carteira: os dois aparecem.
        $this->actingAs($usuario)->get(route('clients.index'))
            ->assertOk()->assertSee('Padaria Sant’Anna')->assertSee('Obra parada');

        $this->actingAs($usuario)->get(route('clients.index', ['situacao' => 'inactive']))
            ->assertOk()->assertSee('Obra parada')->assertDontSee('Padaria Sant’Anna');

        $this->actingAs($usuario)->get(route('clients.index', ['situacao' => 'active']))
            ->assertOk()->assertSee('Padaria Sant’Anna')->assertDontSee('Obra parada');
    }

    public function test_a_paginacao_propria_anda_para_pagina_do_banco(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        for ($i = 1; $i <= 12; $i++) {
            $this->cliente($empresa, 'Cliente '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $primeira = $this->actingAs($usuario)->get(route('clients.index', ['por_pagina' => 10]));
        $segunda = $this->actingAs($usuario)->get(route('clients.index', ['por_pagina' => 10, 'page' => 2]));

        $primeira->assertOk()->assertSee('Cliente 01')->assertDontSee('Cliente 11');
        $segunda->assertOk()->assertSee('Cliente 11')->assertSee('Cliente 12');

        // A contagem da tela é a do banco, não o tamanho da página.
        $primeira->assertSee('12 clientes cadastrados');
    }

    public function test_excluir_quem_ainda_nao_gerou_nada_deixa_o_cliente_fora_da_listagem(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $this->actingAs($usuario)->delete(route('clients.destroy', $cliente))
            ->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted($cliente);

        // A prova de que sumiu é o estado vazio vindo da consulta, não o nome
        // ausente no HTML: o recado de exclusão bem-sucedida cita o cliente.
        $this->actingAs($usuario)->get(route('clients.index'))
            ->assertSee('Nenhum cliente cadastrado nesta empresa');
    }

    public function test_o_servidor_recusa_excluir_quem_ja_tem_historico(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        ServiceOrder::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'number' => 'OS-0001',
            'title' => 'Troca da resistência',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // O botão de excluir vive na ficha, então é de lá que a recusa devolve o
        // usuário — e o motivo da recusa chega como recado, não como tela muda.
        $this->from(route('clients.show', $cliente))->actingAs($usuario)
            ->delete(route('clients.destroy', $cliente))
            ->assertRedirect(route('clients.show', $cliente))
            ->assertSessionHas('erro');

        $this->assertDatabaseHas('clients', ['id' => $cliente->id, 'deleted_at' => null]);
    }

    public function test_excluido_temporariamente_aparece_so_no_filtro_de_excluidos(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $cliente->delete();

        $this->actingAs($usuario)->get(route('clients.index'))->assertDontSee('Padaria Sant’Anna');

        $this->actingAs($usuario)->get(route('clients.index', ['situacao' => 'excluidos']))
            ->assertOk()->assertSee('Padaria Sant’Anna');

        $this->actingAs($usuario)->patch(route('clients.restore', $cliente))
            ->assertRedirect(route('clients.show', $cliente));

        $this->assertDatabaseHas('clients', ['id' => $cliente->id, 'deleted_at' => null]);
    }

    public function test_criar_cliente_grava_na_empresa_do_contexto_sem_id_no_form(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $this->actingAs($usuario)->post(route('clients.store'), [
            'name' => 'Cantina da Nonna',
            'document' => '11.222.333/0001-44',
            'email' => 'contato@cantina.local',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $novo = Client::anyCompany()->firstWhere('name', 'Cantina da Nonna');

        $this->assertNotNull($novo);
        $this->assertSame($empresa->id, $novo->company_id, 'A empresa vem do contexto, não do formulário.');
        $this->assertDatabaseHas('clients', ['email' => 'contato@cantina.local']);
        $this->actingAs($usuario)->post(route('clients.store'), ['status' => 'active'])
            ->assertSessionHasErrors('name');
    }

    public function test_documento_so_precisa_ser_unico_dentro_da_mesma_empresa(): void
    {
        [$alfa, $usuarioAlfa] = $this->empresaComAdmin();
        $bravo = $this->makeCompany('bravo');

        $this->cliente($alfa, 'Padaria Sant’Anna', ['document' => '12.345.678/0001-90']);
        $usuarioBravo = $this->makeUser('administrator', $bravo, 'bravo@test.local');

        $recusado = $this->actingAs($usuarioAlfa)->post(route('clients.store'), [
            'name' => 'Duplicado da empresa',
            'document' => '12.345.678/0001-90',
            'status' => 'active',
        ]);
        $recusado->assertSessionHasErrors('document');

        $aceito = $this->actingAs($usuarioBravo)->post(route('clients.store'), [
            'name' => 'Mesmo documento, outra empresa',
            'document' => '12.345.678/0001-90',
            'status' => 'active',
        ]);
        $aceito->assertSessionHasNoErrors();
    }

    public function test_contato_principal_nunca_fica_duplo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $primeiro = ClientContact::query()->create([
            'client_id' => $cliente->id,
            'name' => 'Dona Rosa',
            'is_primary' => true,
        ]);

        $this->actingAs($usuario)->post(route('clients.contacts.store', $cliente), [
            'contato_nome' => 'Seu Joaquim',
            'contato_principal' => '1',
        ])->assertSessionHasNoErrors();

        $novo = ClientContact::query()->where('name', 'Seu Joaquim')->firstOrFail();

        $this->assertTrue($novo->is_primary);
        $this->assertFalse($primeiro->fresh()->is_primary, 'Promover um contato despromove os demais.');
    }

    public function test_endereco_so_pode_ser_editado_pela_ficha_do_dono_certo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $dono = $this->cliente($empresa, 'Padaria Sant’Anna');
        $vizinho = $this->cliente($empresa, 'Mercado do Zé');

        $endereco = $this->endereco($empresa, $dono, ['city' => 'São Paulo']);

        $this->actingAs($usuario)->put(route('clients.addresses.update', [$vizinho, $endereco]), [
            'endereco_tipo' => 'service',
            'endereco_logradouro' => 'Rua Engenheiro Roberto Zuccolo',
            'endereco_cidade' => 'Campinas',
            'endereco_uf' => 'SP',
        ])->assertNotFound();

        $this->assertSame('São Paulo', $endereco->fresh()->city);
    }

    public function test_quem_nao_tem_a_permissao_recebe_recusa_do_servidor(): void
    {
        [$empresa] = $this->empresaComAdmin();
        $tecnico = $this->makeUser('technician', $empresa, 'tecnico@test.local');
        $visitante = $this->makeUser('client', $empresa, 'visitante@test.local');

        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        // `clients.view` o técnico tem; criar, editar, excluir e exportar não.
        $this->actingAs($tecnico)->get(route('clients.show', $cliente))->assertOk();
        $this->actingAs($tecnico)->get(route('clients.create'))->assertForbidden();
        $this->actingAs($tecnico)->post(route('clients.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->actingAs($tecnico)->put(route('clients.update', $cliente), ['name' => 'Y', 'status' => 'active'])->assertForbidden();
        $this->actingAs($tecnico)->delete(route('clients.destroy', $cliente))->assertForbidden();
        $this->actingAs($tecnico)->get(route('clients.export'))->assertForbidden();

        // O papel `client` não enxerga a carteira inteira.
        $this->actingAs($visitante)->get(route('clients.index'))->assertForbidden();
    }

    public function test_exportar_csv_devolve_as_linhas_desta_empresa_com_os_filtros(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna', ['document' => '12.345.678/0001-90']);
        $this->cliente($empresa, 'Mercado do Zé');
        $this->cliente($outra, 'Cliente de outra empresa');
        $this->endereco($empresa, $padaria, ['city' => 'São Paulo', 'state' => 'SP']);

        $resposta = $this->actingAs($usuario)->get(route('clients.export', ['cidade' => 'São Paulo']));

        $resposta->assertOk();
        $this->assertStringContainsString('attachment', $resposta->headers->get('content-disposition'));

        $csv = $resposta->streamedContent();
        $this->assertStringContainsString('Padaria Sant’Anna', $csv);
        $this->assertStringNotContainsString('Mercado do Zé', $csv, 'A exportação respeita o filtro da tela.');
        $this->assertStringNotContainsString('Cliente de outra empresa', $csv, 'A exportação respeita o tenant.');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Sem BOM o Excel abre os acentos errados.');
    }

    public function test_um_cliente_de_outra_empresa_nao_e_alcancavel_pela_rota(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $estranha = $this->cliente($this->makeCompany('bravo'), 'Cliente invasor');

        $this->actingAs($usuario)->get(route('clients.show', $estranha))->assertNotFound();
        $this->actingAs($usuario)->delete(route('clients.destroy', $estranha))->assertNotFound();
    }

    /** @return array{0: Company, 1: \App\Models\User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
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
    private function endereco(Company $empresa, Client $cliente, array $extras = []): Address
    {
        TenantContext::set($empresa->id);
        $endereco = $cliente->addresses()->create($extras + [
            'type' => 'service',
            'street' => 'Rua das Flores',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);
        TenantContext::forget();

        return $endereco;
    }
}
