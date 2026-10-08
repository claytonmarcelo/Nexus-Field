<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderAssignment;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderStatusHistory;
use App\Models\Technician;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 12 é o documento do trabalho: a ordem nasce sem número digitado, carrega o
 * endereço do dia, cobra linha por linha vinda do catálogo com preço congelado e
 * só muda de estado pelo caminho que registra quem fez, quando e por quê. Ordem
 * encerrada é conta fechada — nem item, nem quadro de comissão se mexe.
 */
class OrdersTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_listagem_traz_so_as_ordens_da_empresa_aberta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'), [
            'number' => 'OS-2026-0001',
            'title' => 'Manutenção das câmaras frias',
        ]);
        $alheia = $this->ordem($outra, $this->cliente($outra, 'Cliente de outra empresa'), [
            'number' => 'OS-2026-9001',
            'title' => 'Ordem que não é desta empresa',
        ]);

        $lista = $this->actingAs($usuario)->get(route('orders.index'));

        $lista->assertOk()
            ->assertSee('OS-2026-0001')
            ->assertSee('Manutenção das câmaras frias')
            ->assertDontSee('OS-2026-9001')
            ->assertDontSee('Ordem que não é desta empresa')
            ->assertSee('1 ordem encontrada');

        $this->actingAs($usuario)->get(route('orders.show', $alheia))->assertNotFound();
        $this->actingAs($usuario)->get(route('orders.edit', $alheia))->assertNotFound();
        $this->actingAs($usuario)->put(route('orders.status', $alheia), ['estado' => 'in_progress'])->assertNotFound();

        $this->assertSame('open', $alheia->fresh()->status);
    }

    public function test_o_tecnico_ve_as_ordens_dele_e_a_conta_de_cliente_ve_a_carteira_dele(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $gelateria = $this->cliente($empresa, 'Gelato Bella Manteiga');

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');

        $dele = $this->ordem($empresa, $padaria, ['number' => 'OS-2026-0001', 'technician_id' => $ricardo->id]);
        $comissionado = $this->ordem($empresa, $gelateria, ['number' => 'OS-2026-0002', 'technician_id' => $ana->id]);
        $this->comissao($comissionado, $ricardo);
        $esta = $this->ordem($empresa, $padaria, ['number' => 'OS-2026-0003', 'technician_id' => $ana->id]);

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        TenantContext::forget();

        $this->actingAs($campo->fresh())->get(route('orders.index'))
            ->assertOk()
            ->assertSee('OS-2026-0001')
            ->assertSee('OS-2026-0002')
            ->assertDontSee('OS-2026-0003')
            ->assertSee('2 ordens encontradas')
            // O seletor de técnico e de cliente sai da tela: filtrar o que ele já não vê seria teatro.
            ->assertDontSee('Qualquer técnico da escala')
            ->assertSee('As ordens que são suas aparecem aqui');

        // No campo ele é apontado pela coluna da ordem e também pelo quadro de comissão.
        $this->actingAs($campo->fresh())->get(route('orders.show', $dele))
            ->assertOk()->assertSee('Ricardo Prado');
        $this->actingAs($campo->fresh())->get(route('orders.show', $comissionado))
            ->assertOk()->assertSee('Ana Beltrão')->assertSee('Quadro de comissão');

        $fora = $this->ordem($empresa, $gelateria, ['number' => 'OS-2026-0004', 'technician_id' => $ana->id]);
        $this->actingAs($campo->fresh())->get(route('orders.show', $fora))->assertNotFound();
        $this->actingAs($campo->fresh())
            ->put(route('orders.status', $fora), ['estado' => 'on_hold'])->assertNotFound();
        $this->assertSame('open', $fora->fresh()->status);

        // A conta de cliente enxerga a carteira dela, e nada além dela.
        TenantContext::set($empresa->id);
        $contaCliente = $this->makeUser('client', $empresa, 'cliente@test.local');
        $contaCliente->update(['client_id' => $padaria->id]);
        TenantContext::forget();

        $this->ordem($empresa, $padaria, ['number' => 'OS-2026-0005']);
        $foraDaCarteira = $this->ordem($empresa, $gelateria, ['number' => 'OS-2026-0006']);

        $this->actingAs($contaCliente->fresh())->get(route('orders.index'))
            ->assertOk()
            ->assertSee('OS-2026-0001')
            ->assertSee('OS-2026-0005')
            ->assertDontSee('OS-2026-0002')
            ->assertSee('3 ordens encontradas');

        $this->actingAs($contaCliente->fresh())->get(route('orders.show', $foraDaCarteira))->assertNotFound();
        $this->actingAs($contaCliente->fresh())->get(route('orders.show', $esta))->assertOk();

        $this->assertSame(6, ServiceOrder::anyCompany()->count());
        $this->assertSame($usuario->company_id, $empresa->id);
    }

    public function test_o_numero_nasce_da_sequencia_do_ano_dentro_da_empresa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');
        $adminOutra = $this->makeUser('administrator', $outra, 'b@test.local');

        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $clienteOutra = $this->cliente($outra, 'Cliente vizinho');

        $this->ordem($empresa, $cliente, ['number' => 'OS-'.now()->format('Y').'-0007']);
        $this->ordem($outra, $clienteOutra, ['number' => 'OS-'.now()->format('Y').'-0031']);

        $base = ['title' => 'Manutenção preventiva', 'priority' => 'normal', 'status' => 'draft'];

        $this->actingAs($usuario)->post(route('orders.store'), ['client_id' => $cliente->id] + $base)
            ->assertSessionHasNoErrors();
        $this->actingAs($usuario)->post(route('orders.store'), ['client_id' => $cliente->id, 'title' => 'Segunda ordem'] + $base)
            ->assertSessionHasNoErrors();

        $numeros = ServiceOrder::anyCompany()->where('company_id', $empresa->id)->orderBy('id')->pluck('number')->all();

        $this->assertSame([
            'OS-'.now()->format('Y').'-0007',
            'OS-'.now()->format('Y').'-0008',
            'OS-'.now()->format('Y').'-0009',
        ], $numeros, 'A sequência continua a do ano, e a empresa do lado não interfere.');

        // O escritório da outra empresa tem sequência própria.
        TenantContext::set($outra->id);
        $this->actingAs($adminOutra)->post(route('orders.store'), ['client_id' => $clienteOutra->id] + $base)
            ->assertSessionHasNoErrors();
        TenantContext::forget();

        $this->assertSame(
            'OS-'.now()->format('Y').'-0032',
            ServiceOrder::anyCompany()->where('company_id', $outra->id)->latest('id')->first()->number
        );

        // Número não é campo de tela: ninguém digita a identidade da ordem.
        $this->actingAs($usuario)->get(route('orders.create'))
            ->assertOk()
            ->assertDontSee('name="number"');
    }

    public function test_a_edicao_nao_oferece_estado_e_o_servidor_ignora_quem_envia_pelo_form(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $ordem = $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'));

        $this->actingAs($usuario)->get(route('orders.edit', $ordem))
            ->assertOk()
            ->assertDontSee('name="status"')
            ->assertSee('mudá-lo é pelo botão da ficha', false);

        $this->actingAs($usuario)->put(route('orders.update', $ordem), [
            'client_id' => $ordem->client_id,
            'title' => 'Título corrigido',
            'priority' => 'high',
            'status' => 'completed',
            'completed_at' => now()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $ordem->refresh();

        $this->assertSame('Título corrigido', $ordem->title);
        $this->assertSame('high', $ordem->priority);
        $this->assertSame('open', $ordem->status, 'Estado não é campo de edição: só o botão de estado escreve.');
        $this->assertNull($ordem->completed_at);
        $this->assertSame(1, ServiceOrderStatusHistory::query()->where('service_order_id', $ordem->id)->count());
    }

    public function test_a_ordem_copia_o_endereco_do_cliente_e_o_fim_vem_do_tempo_estimado(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        TenantContext::set($empresa->id);
        $cliente->addresses()->create([
            'company_id' => $empresa->id,
            'type' => 'service',
            'street' => 'Rua Aurora',
            'number' => '210',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01011000',
            'latitude' => -23.5475,
            'longitude' => -46.6361,
            'is_primary' => true,
        ]);
        TenantContext::forget();

        $servico = $this->servico($empresa, 'Instalação de câmara fria', ['estimated_minutes' => 90]);

        $this->actingAs($usuario)->get(route('orders.create', ['cliente' => $cliente->id]))
            ->assertOk()
            ->assertSee('Rua Aurora')
            ->assertSee('Centro')
            ->assertSee('23.5475000');

        $this->actingAs($usuario)->post(route('orders.store'), [
            'client_id' => $cliente->id,
            'service_id' => $servico->id,
            'title' => 'Instalação de câmara fria',
            'priority' => 'normal',
            'status' => 'open',
            'scheduled_starts_at' => '2026-10-10T08:00',
            'street' => 'Rua Aurora',
            'number_address' => '210',
            'city' => 'São Paulo',
            'state' => 'sp',
        ])->assertSessionHasNoErrors();

        $ordem = ServiceOrder::anyCompany()->firstWhere('title', 'Instalação de câmara fria');

        $this->assertSame('2026-10-10 09:30', $ordem->scheduled_ends_at->format('Y-m-d H:i'),
            'Fim previsto sai da duração estimada do serviço, não de mais um campo digitado.');
        $this->assertSame('SP', $ordem->state);

        // O endereço é snapshot: mudar a ficha do cliente amanhã não reescreve a ordem de hoje.
        TenantContext::set($empresa->id);
        $cliente->enderecoPrincipal()->update(['street' => 'Rua Que Se Mudou']);
        TenantContext::forget();

        $this->assertSame('Rua Aurora', $ordem->fresh()->street);

        // E o painel do técnico lê a ordem que é dele, com o local gravado nela.
        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()
            ->assertSee('Rua Aurora')
            ->assertSee('Aberta');
    }

    public function test_o_estado_muda_pelo_botao_com_carimbo_e_passagem_registrada(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $ordem = $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'), ['number' => 'OS-2026-0001']);

        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()
            ->assertSee('Em execução')
            ->assertSee('Ordem aberta na tela de ordens.')
            ->assertSee('Passagens registradas');

        $this->from(route('orders.show', $ordem))->actingAs($usuario)
            ->put(route('orders.status', $ordem), ['estado' => 'in_progress', 'nota' => 'Técnico chegou ao local.'])
            ->assertRedirect(route('orders.show', $ordem))
            ->assertSessionHas('status', 'Ordem OS-2026-0001: Aberta → Em execução.');

        $ordem->refresh();

        $this->assertSame('in_progress', $ordem->status);
        $this->assertNotNull($ordem->started_at, 'Entrar em execução carimba o início real.');
        $this->assertNull($ordem->completed_at);

        $passagem = ServiceOrderStatusHistory::query()
            ->where('service_order_id', $ordem->id)
            ->where('to_status', 'in_progress')
            ->first();

        $this->assertNotNull($passagem);
        $this->assertSame('open', $passagem->from_status);
        $this->assertSame($usuario->id, $passagem->user_id, 'A passagem responde quem mudou o estado.');
        $this->assertSame('Técnico chegou ao local.', $passagem->note);

        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()
            ->assertSee('Concluída')
            ->assertSee('Aberta → ', false)
            ->assertSee('por Usuário administrator', false);

        $this->actingAs($usuario)->put(route('orders.status', $ordem), ['estado' => 'completed'])
            ->assertSessionHas('status', 'Ordem OS-2026-0001: Em execução → Concluída.');

        $ordem->refresh();
        $this->assertSame('completed', $ordem->status);
        $this->assertNotNull($ordem->completed_at);
    }

    public function test_o_fluxo_recusa_o_salto_e_o_cancelamento_sem_motivo(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $rascunho = $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0001', 'status' => 'draft']);
        $andamento = $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0002', 'status' => 'in_progress']);

        $this->from(route('orders.show', $rascunho))->actingAs($usuario)
            ->put(route('orders.status', $rascunho), ['estado' => 'completed'])
            ->assertSessionHas('erro');
        $this->assertStringContainsString('não pode ir para “Concluída”', (string) session('erro'));
        $this->assertSame('draft', $rascunho->fresh()->status, 'Rascunho não pula para concluída.');

        // Cancelar sem motivo registrado não é cancelamento: a validação pede o texto.
        $this->actingAs($usuario)->put(route('orders.status', $andamento), ['estado' => 'canceled'])
            ->assertSessionHasErrors('nota');
        $this->assertSame('in_progress', $andamento->fresh()->status);

        $this->actingAs($usuario)->put(route('orders.status', $andamento), [
            'estado' => 'canceled', 'nota' => 'Cliente remarcou para o mês que vem.',
        ])->assertSessionHasNoErrors();

        $andamento->refresh();
        $this->assertSame('canceled', $andamento->status);
        $this->assertNotNull($andamento->cancelled_at);
        $this->assertSame('Cliente remarcou para o mês que vem.', $andamento->cancellation_reason);

        // Estado terminal não tem volta: reabrir ordem cancelada não é caminho.
        $this->actingAs($usuario)->put(route('orders.status', $andamento), ['estado' => 'in_progress'])
            ->assertSessionHas('erro');
        $this->assertSame('canceled', $andamento->fresh()->status);

        $this->actingAs($usuario)->get(route('orders.show', $andamento))
            ->assertOk()
            ->assertSee('Nenhum estado à disposição')
            ->assertSee('Cancelada');

        // Estado que não existe no cadastro do módulo não passa pela regra.
        $this->actingAs($usuario)->put(route('orders.status', $rascunho), ['estado' => 'festa'])
            ->assertSessionHasErrors('estado');
    }

    public function test_abrir_e_cancelar_pedem_quem_tem_a_permissao_de_aprovacao(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $operario = $this->makeUser('employee', $empresa, 'opc@test.local');
        $gestor = $this->makeUser('supervisor', $empresa, 'gestor@test.local');

        $rascunho = $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'), [
            'number' => 'OS-2026-0001', 'status' => 'draft',
        ]);

        $this->actingAs($operario)->get(route('orders.show', $rascunho))
            ->assertOk()
            ->assertSee('Nenhum estado à disposição')
            ->assertDontSee('name="estado"');

        $this->from(route('orders.show', $rascunho))->actingAs($operario)
            ->put(route('orders.status', $rascunho), ['estado' => 'open'])
            ->assertSessionHas('erro', 'Tirar um rascunho do papel e cancelar uma ordem pedem a permissão de aprovação.');
        $this->assertSame('draft', $rascunho->fresh()->status);

        // Quem aprova tira o rascunho do papel, e a tela de cadastro dele oferece os dois estados.
        $this->actingAs($gestor)->get(route('orders.create'))
            ->assertOk()->assertSee('Aberta')->assertSee('Rascunho');
        $this->actingAs($operario)->get(route('orders.create'))
            ->assertOk()->assertDontSee('>Aberta<', false);

        $this->actingAs($gestor)->put(route('orders.status', $rascunho), ['estado' => 'open'])
            ->assertSessionHasNoErrors();
        $this->assertSame('open', $rascunho->fresh()->status);

        // E o operário que executa segue a fila dele: execução e espera são do dia a dia.
        $this->actingAs($operario)->put(route('orders.status', $rascunho), ['estado' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $rascunho->fresh()->status);

        // O funcionário não cria ordem no estado que não lhe foi dado.
        $this->actingAs($operario)->post(route('orders.store'), [
            'client_id' => $rascunho->client_id,
            'title' => 'Ordem além do permitido',
            'priority' => 'normal',
            'status' => 'canceled',
        ])->assertSessionHasErrors('status');
    }

    public function test_a_linha_cobrada_congela_o_preco_e_a_conta_soma_no_banco(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'), ['number' => 'OS-2026-0001']);
        $servico = $this->servico($empresa, 'Instalação de câmara fria', ['price' => 1200]);
        $produto = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410', 'price' => 18]);

        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()->assertSee('A ordem ainda não cobra nada');

        $this->actingAs($usuario)->post(route('orders.items.store', $ordem), [
            'item_servico' => $servico->id,
            'item_quantidade' => 2,
        ])->assertSessionHasNoErrors();

        $this->actingAs($usuario)->post(route('orders.items.store', $ordem), [
            'item_produto' => $produto->id,
            'item_quantidade' => 3,
            'item_desconto' => 6,
            'item_descricao' => 'Gás R-410a recuperado',
        ])->assertSessionHasNoErrors();

        // O catálogo muda de preço depois; a linha cobrada naquele dia continua a linha daquele dia.
        TenantContext::set($empresa->id);
        $servico->update(['price' => 999]);
        $produto->update(['price' => 21]);
        TenantContext::forget();

        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()
            ->assertSee('Gás R-410a recuperado')
            ->assertSee('GS-410')
            ->assertSee('R$ 1.200,00')
            ->assertSee('R$ 2.400,00')
            ->assertSee('R$ 2.454,00')
            ->assertSee('R$ 2.448,00')
            ->assertSee('2 linhas cobradas')
            ->assertDontSee('R$ 999,00');

        $carregada = $ordem->fresh()->load('items');
        $this->assertSame(2454.0, $carregada->totais()['bruto']);
        $this->assertSame(2448.0, $carregada->totais()['liquido']);
        $this->assertSame('1200.00', (string) $carregada->items->firstWhere('service_id', $servico->id)->unit_price);

        // A listagem desenha o mesmo número que a ficha, lido direto do SQL.
        $this->actingAs($usuario)->get(route('orders.index'))
            ->assertOk()->assertSee('R$ 2.448,00');

        // O desconto da ordem tem teto: o que os itens cobram.
        $this->actingAs($usuario)->put(route('orders.update', $ordem), [
            'client_id' => $ordem->client_id,
            'title' => $ordem->title,
            'priority' => 'normal',
            'discount' => 9000,
        ])->assertSessionHasErrors('discount');

        $this->actingAs($usuario)->put(route('orders.update', $ordem), [
            'client_id' => $ordem->client_id,
            'title' => $ordem->title,
            'priority' => 'normal',
            'discount' => 448,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2000.0, $ordem->fresh()->load('items')->totais()['total']);
        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()->assertSee('R$ 2.000,00');

        // Editar linha mexe no valor dela, nunca em quem é o item do catálogo.
        $linha = $ordem->fresh()->load('items')->items->firstWhere('product_id', $produto->id);

        $this->actingAs($usuario)->put(route('orders.items.update', [$ordem, $linha]), [
            'item_quantidade' => 4,
            'item_valor' => 18,
            'item_desconto' => 0,
            'item_descricao' => 'Gás R-410a',
            'item_produto' => $servico->id,
        ])->assertSessionHasNoErrors();

        $linha->refresh();
        $this->assertSame(72.0, $linha->total());
        $this->assertSame($produto->id, $linha->product_id, 'A origem da linha não é trocada pela edição.');
        $this->assertNull($linha->service_id);
    }

    public function test_a_linha_e_servico_ou_produto_e_o_desconto_nao_passa_dela(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'));
        $servico = $this->servico($empresa, 'Instalação de câmara fria', ['price' => 1200]);
        $produto = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410', 'price' => 18]);
        $estranha = $this->servico($this->makeCompany('bravo'), 'Serviço de outra empresa');

        $this->actingAs($usuario)->post(route('orders.items.store', $ordem), [
            'item_servico' => $servico->id,
            'item_produto' => $produto->id,
            'item_quantidade' => 1,
        ])->assertSessionHasErrors(['item_servico', 'item_produto']);

        $this->actingAs($usuario)->post(route('orders.items.store', $ordem), ['item_quantidade' => 1])
            ->assertSessionHasErrors(['item_servico', 'item_produto']);

        $this->actingAs($usuario)->post(route('orders.items.store', $ordem), [
            'item_servico' => $estranha->id,
            'item_quantidade' => 1,
        ])->assertSessionHasErrors('item_servico');

        $this->actingAs($usuario)->post(route('orders.items.store', $ordem), [
            'item_produto' => $produto->id,
            'item_quantidade' => 0,
        ])->assertSessionHasErrors('item_quantidade');

        $this->actingAs($usuario)->post(route('orders.items.store', $ordem), [
            'item_produto' => $produto->id,
            'item_quantidade' => 2,
            'item_valor' => 18,
            'item_desconto' => 50,
        ])->assertSessionHasErrors('item_desconto');

        $this->assertSame(0, $ordem->items()->count(), 'Nenhuma linha entra pela porta do erro de validação.');

        $linha = $this->item($ordem, $produto, 2, 18);
        $this->actingAs($usuario)->delete(route('orders.items.destroy', [$ordem, $linha]))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $ordem->items()->count());

        // Linha de outra ordem não é removida pela rota desta, mesmo dentro da empresa.
        $outraOrdem = $this->ordem($empresa, $ordem->client, ['number' => 'OS-2026-0002']);
        $alheia = $this->item($outraOrdem, $produto, 1, 18);

        $this->actingAs($usuario)->delete(route('orders.items.destroy', [$ordem, $alheia]))->assertNotFound();
        $this->assertDatabaseHas('service_order_items', ['id' => $alheia->id]);
    }

    public function test_ordem_encerrada_nao_aceita_linha_nem_quadro_novos(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'), [
            'number' => 'OS-2026-0001', 'status' => 'completed', 'completed_at' => now()->subHour(),
        ]);
        $produto = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410']);
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');

        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()
            ->assertSee('Ordem encerrada')
            ->assertSee('conta fechada', false)
            ->assertDontSee('item_quantidade')
            ->assertDontSee('tecnico_id');

        $this->from(route('orders.show', $ordem))->actingAs($usuario)
            ->post(route('orders.items.store', $ordem), ['item_produto' => $produto->id, 'item_quantidade' => 1])
            ->assertSessionHas('erro',
                'A ordem OS-2026-0001 está concluída e por isso não aceita mudança de itens. Abra outra ordem para corrigir o que foi cobrado.');
        $this->assertSame(0, $ordem->items()->count());

        $this->actingAs($usuario)->post(route('orders.assignments.store', $ordem), ['tecnico_id' => $ricardo->id])
            ->assertSessionHas('erro',
                'A ordem OS-2026-0001 está concluída: o quadro de comissão dela é histórico e não se mexe mais.');
        $this->assertSame(0, $ordem->assignments()->count());

        $this->actingAs($usuario)->delete(route('orders.assignments.destroy', [$ordem, $ricardo]))
            ->assertSessionHas('erro');

        // A tela de edição não oferece estado, e o servidor também não deixa reabrir.
        $this->actingAs($usuario)->put(route('orders.update', $ordem), [
            'client_id' => $ordem->client_id, 'title' => 'Reescrita', 'priority' => 'normal', 'status' => 'open',
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $ordem->fresh()->status);
    }

    public function test_o_quadro_preserva_a_passagem_e_passa_a_responsabilidade(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'), [
            'number' => 'OS-2026-0001', 'technician_id' => null,
        ]);
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $invasor = $this->tecnico($this->makeCompany('bravo'), 'Técnico de outra empresa');

        $this->actingAs($usuario)->post(route('orders.assignments.store', $ordem), ['tecnico_id' => $invasor->id])
            ->assertSessionHasErrors('tecnico_id');
        $this->assertSame(0, $ordem->assignments()->count());

        $this->actingAs($usuario)->post(route('orders.assignments.store', $ordem), [
            'tecnico_id' => $ricardo->id, 'comissao_nota' => 'Suporte de refrigeração.',
        ])->assertSessionHasNoErrors();

        // Sem responsável, quem entra no quadro assume a ordem.
        $this->assertSame($ricardo->id, $ordem->fresh()->technician_id);

        $this->actingAs($usuario)->post(route('orders.assignments.store', $ordem), ['tecnico_id' => $ana->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($usuario)->get(route('orders.show', $ordem))
            ->assertOk()
            ->assertSee('Ricardo Prado')
            ->assertSee('Ana Beltrão')
            ->assertSee('Suporte de refrigeração.')
            ->assertSee('responsável', false);

        $this->actingAs($usuario)->post(route('orders.assignments.store', $ordem), ['tecnico_id' => $ana->id])
            ->assertSessionHas('aviso', 'Este técnico já está no quadro desta ordem.');
        $this->assertSame(2, $ordem->assignments()->count());

        // Liberar o responsável passa a ordem ao próximo que ficou dentro.
        $this->actingAs($usuario)->delete(route('orders.assignments.destroy', [$ordem, $ricardo]))
            ->assertSessionHasNoErrors();

        $this->assertSame($ana->id, $ordem->fresh()->technician_id);
        $saiu = $ordem->assignments()->where('technician_id', $ricardo->id)->first();
        $this->assertNotNull($saiu->released_at, 'A passagem fica na ficha com a data de saída.');
        $this->assertSame(2, $ordem->assignments()->count(), 'Liberar não risca ninguém do histórico.');

        $antes = $saiu->id;
        $this->actingAs($usuario)->post(route('orders.assignments.store', $ordem), ['tecnico_id' => $ricardo->id])
            ->assertSessionHasNoErrors();

        $reaberta = $ordem->assignments()->where('technician_id', $ricardo->id)->sole();
        $this->assertSame($antes, $reaberta->id, 'Voltar reabre a mesma passagem, não cria segunda linha.');
        $this->assertNull($reaberta->released_at);

        $this->actingAs($usuario)->delete(route('orders.assignments.destroy', [$ordem, $ana]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $ordem->assignments()->whereNull('released_at')->count());
        $this->assertSame($ricardo->id, $ordem->fresh()->technician_id);

        // Ficha sem ninguém no quadro é o estado honesto de uma ordem que ficou vaga.
        $this->actingAs($usuario)->delete(route('orders.assignments.destroy', [$ordem, $ricardo]))
            ->assertSessionHasNoErrors();
        $this->assertNull($ordem->fresh()->technician_id);
    }

    public function test_somente_o_rascunho_que_nunca_virou_trabalho_pode_ser_apagado(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $rascunho = $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0001', 'status' => 'draft']);
        $aberta = $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0002']);

        $this->actingAs($usuario)->get(route('orders.show', $rascunho))
            ->assertOk()->assertSee('Apagar rascunho');
        $this->actingAs($usuario)->get(route('orders.show', $aberta))
            ->assertOk()->assertDontSee('Apagar rascunho');

        $this->from(route('orders.show', $aberta))->actingAs($usuario)
            ->delete(route('orders.destroy', $aberta))
            ->assertRedirect(route('orders.show', $aberta))
            ->assertSessionHas('erro');
        $this->assertStringContainsString('Cancele-a com o motivo registrado.', (string) session('erro'));
        $this->assertDatabaseHas('service_orders', ['id' => $aberta->id]);

        $this->actingAs($usuario)->delete(route('orders.destroy', $rascunho))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('status', 'Rascunho OS-2026-0001 apagado.');
        $this->assertDatabaseMissing('service_orders', ['id' => $rascunho->id]);

        $this->actingAs($usuario)->get(route('orders.index'))
            ->assertOk()->assertSee('1 ordem encontrada');
    }

    public function test_filtros_ordenacao_paginacao_e_csv_sao_a_mesma_consulta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $gelateria = $this->cliente($empresa, 'Gelato Bella Manteiga');
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');

        TenantContext::set($empresa->id);
        foreach (range(1, 12) as $n) {
            ServiceOrder::query()->create([
                'company_id' => $empresa->id,
                'client_id' => $n % 3 === 0 ? $gelateria->id : $padaria->id,
                'technician_id' => $n % 2 === 0 ? $ricardo->id : null,
                'number' => 'OS-2026-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'title' => 'Manutenção preventiva '.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'status' => $n % 4 === 0 ? 'completed' : 'open',
                'priority' => $n % 5 === 0 ? 'urgent' : 'normal',
                'scheduled_starts_at' => '2026-10-'.str_pad((string) (4 + $n), 2, '0', STR_PAD_LEFT).' 08:00',
                'scheduled_ends_at' => '2026-10-'.str_pad((string) (4 + $n), 2, '0', STR_PAD_LEFT).' 10:00',
            ]);
        }
        TenantContext::forget();

        $this->ordem($outra, $this->cliente($outra, 'Cliente de outra empresa'), ['number' => 'OS-2026-8888']);

        $esta = $this->actingAs($usuario)
            ->get(route('orders.index', ['por_pagina' => 10, 'ordena' => 'number', 'direcao' => 'asc']));
        $esta->assertOk()->assertSee('OS-2026-0001')->assertDontSee('OS-2026-0011')->assertSee('12 ordens encontradas');

        $this->actingAs($usuario)
            ->get(route('orders.index', ['por_pagina' => 10, 'page' => 2, 'ordena' => 'number', 'direcao' => 'asc']))
            ->assertOk()->assertSee('OS-2026-0011')->assertSee('OS-2026-0012');

        $this->actingAs($usuario)->get(route('orders.index', ['situacao' => 'completed']))
            ->assertOk()->assertSee('OS-2026-0004')->assertDontSee('OS-2026-0001');

        $this->actingAs($usuario)->get(route('orders.index', ['prioridade' => 'urgent']))
            ->assertOk()->assertSee('OS-2026-0005')->assertDontSee('OS-2026-0001');

        // A carteira filtrada é contada no SQL: 3 de 12 são da Gelateria, 6 têm técnico na escala.
        $this->actingAs($usuario)->get(route('orders.index', ['cliente' => $gelateria->id]))
            ->assertOk()->assertSee('Gelato Bella Manteiga')->assertSee('4 ordens encontradas');

        $this->actingAs($usuario)->get(route('orders.index', ['tecnico' => $ricardo->id]))
            ->assertOk()->assertSee('Ricardo Prado')->assertSee('6 ordens encontradas');

        $this->actingAs($usuario)->get(route('orders.index', ['busca' => 'OS-2026-0007']))
            ->assertOk()->assertSee('OS-2026-0007')->assertDontSee('OS-2026-0008');

        $this->actingAs($usuario)->get(route('orders.index', ['inicio' => '2026-10-14', 'fim' => '2026-10-16']))
            ->assertOk()->assertSee('OS-2026-0010')->assertDontSee('OS-2026-0001');

        // A busca que não acha nada devolve o estado de filtro, não a lista vazia padrão.
        $this->actingAs($usuario)->get(route('orders.index', ['busca' => 'ordem inexistente']))
            ->assertOk()->assertSee('Nenhuma ordem com estes filtros');

        $csv = $this->actingAs($usuario)->get(route('orders.export', ['situacao' => 'completed']));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $conteudo = $csv->streamedContent();
        $linhas = array_filter(explode("\n", trim($conteudo)));

        $this->assertStringContainsString('Número;Título;Cliente', $conteudo);
        $this->assertCount(4, $linhas, 'Três concluídas no filtro mais o cabeçalho: o CSV é a mesma consulta da tela.');
        $this->assertStringContainsString('OS-2026-0004', $conteudo);
        $this->assertStringContainsString('Concluída', $conteudo);
        $this->assertStringNotContainsString('OS-2026-8888', $conteudo);
        $this->assertStringNotContainsString('Cliente de outra empresa', $conteudo);
    }

    public function test_o_painel_do_tecnico_nao_conta_a_fila_dos_outros(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');

        $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0001', 'technician_id' => $ricardo->id]);
        $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0002', 'technician_id' => $ana->id]);
        $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0003', 'technician_id' => $ana->id, 'status' => 'draft']);

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        TenantContext::forget();

        TenantContext::set($empresa->id);
        $painel = (new DashboardMetrics($campo->fresh()))->toArray();
        $doEscritorio = (new DashboardMetrics($usuario))->toArray();
        TenantContext::forget();

        $this->assertSame(1, $this->kpi($painel, 'Ordens abertas'),
            'A KPI de abertas responde pela fila de quem está no campo, não pela operação inteira.');
        $this->assertSame(2, $this->kpi($doEscritorio, 'Ordens abertas'));
    }

    public function test_o_servidor_recusa_quem_nao_tem_a_permissao_do_modulo(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        $contaCliente = $this->makeUser('client', $empresa, 'cliente@test.local');
        $tecnico = $this->makeUser('technician', $empresa, 'campo@test.local');
        $gestor = $this->makeUser('supervisor', $empresa, 'gestor@test.local');

        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        TenantContext::set($empresa->id);
        $contaCliente->update(['client_id' => $cliente->id]);
        TenantContext::forget();

        $ordem = $this->ordem($empresa, $cliente, ['number' => 'OS-2026-0001']);
        $payload = ['client_id' => $cliente->id, 'title' => 'Ordem que não vai nascer', 'priority' => 'normal', 'status' => 'draft'];

        // A conta de cliente lê a carteira dela e não escreve nada.
        $this->actingAs($contaCliente)->get(route('orders.index'))->assertOk();
        $this->actingAs($contaCliente)->get(route('orders.show', $ordem))->assertOk();
        $this->actingAs($contaCliente)->get(route('orders.create'))->assertForbidden();
        $this->actingAs($contaCliente)->post(route('orders.store'), $payload)->assertForbidden();
        $this->actingAs($contaCliente)->put(route('orders.status', $ordem), ['estado' => 'on_hold'])->assertForbidden();
        $this->actingAs($contaCliente)->post(route('orders.items.store', $ordem), ['item_quantidade' => 1])->assertForbidden();
        $this->actingAs($contaCliente)->get(route('orders.export'))->assertForbidden();
        $this->assertSame('open', $ordem->fresh()->status);

        // O técnico executa a ordem dele, mas não abre ordem nova nem apaga.
        TenantContext::set($empresa->id);
        $ficha = $this->tecnico($empresa, 'Ricardo Prado', ['user_id' => $tecnico->id]);
        TenantContext::forget();
        $ordem->update(['technician_id' => $ficha->id]);

        $this->actingAs($tecnico)->get(route('orders.create'))->assertForbidden();
        $this->actingAs($tecnico)->post(route('orders.store'), $payload)->assertForbidden();
        $this->actingAs($tecnico)->delete(route('orders.destroy', $ordem))->assertForbidden();
        $this->actingAs($tecnico)->put(route('orders.status', $ordem), ['estado' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $ordem->fresh()->status);

        // O gestor aprova e exporta, mas apagar ordem é do administrador.
        $this->actingAs($gestor)->get(route('orders.export'))->assertOk();
        $this->actingAs($gestor)->delete(route('orders.destroy', $ordem))->assertForbidden();
        $this->assertDatabaseHas('service_orders', ['id' => $ordem->id]);

        $this->assertSame(1, ServiceOrder::anyCompany()->count(), 'Recusa no servidor não cria ordem.');
        $this->assertSame(0, $ordem->items()->count());
    }

    /**
     * O painel entrega os KPIs dos blocos numa lista única, então a prova procura
     * pelo rótulo que a tela mostra — não pela posição, que mudaria com o próximo
     * módulo.
     *
     * @param  array<string, mixed>  $painel
     */
    private function kpi(array $painel, string $rotulo): int
    {
        $kpi = collect($painel['kpis'])->firstWhere('label', $rotulo);
        $this->assertNotNull($kpi, "O painel não desenhou o KPI “{$rotulo}”.");

        return (int) $kpi['value'];
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /** @param  array<string, mixed>  $extras */
    private function ordem(Company $empresa, Client $cliente, array $extras = []): ServiceOrder
    {
        TenantContext::set($empresa->id);

        $ordem = ServiceOrder::query()->create($extras + [
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'number' => 'OS-'.now()->format('Y').'-'.str_pad((string) (ServiceOrder::anyCompany()->count() + 1), 4, '0', STR_PAD_LEFT),
            'title' => 'Visita de verificação',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        ServiceOrderStatusHistory::query()->create([
            'service_order_id' => $ordem->id,
            'user_id' => $ordem->company->users()->orderBy('id')->first()?->id,
            'from_status' => null,
            'to_status' => $ordem->status,
            'note' => 'Ordem aberta na tela de ordens.',
            'created_at' => $ordem->created_at,
        ]);

        TenantContext::forget();

        return $ordem;
    }

    private function comissao(ServiceOrder $ordem, Technician $tecnico): ServiceOrderAssignment
    {
        TenantContext::set($ordem->company_id);
        $comissao = ServiceOrderAssignment::query()->create([
            'service_order_id' => $ordem->id,
            'technician_id' => $tecnico->id,
            'assigned_at' => now()->subDay(),
        ]);
        TenantContext::forget();

        return $comissao;
    }

    private function item(ServiceOrder $ordem, Product $produto, float $quantidade, float $unitario): ServiceOrderItem
    {
        TenantContext::set($ordem->company_id);
        $item = $ordem->items()->create([
            'product_id' => $produto->id,
            'description' => $produto->name,
            'quantity' => $quantidade,
            'unit_price' => $unitario,
        ]);
        TenantContext::forget();

        return $item;
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

    /** @param  array<string, mixed>  $extras */
    private function servico(Company $empresa, string $nome, array $extras = []): Service
    {
        TenantContext::set($empresa->id);
        $servico = Service::query()->create($extras + ['name' => $nome, 'price' => 100, 'status' => 'active']);
        TenantContext::forget();

        return $servico;
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
}
