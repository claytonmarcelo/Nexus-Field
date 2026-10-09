<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\TechnicianStock;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 16 é o livro-caixa do estoque. O que se prende aqui é o que faz dele um
 * livro e não um campo editável: o saldo sai da soma das linhas com o sinal de cada
 * tipo, nenhuma linha nasce do relógio ou da vontade de quem postou, nenhum saldo
 * vira negativo, e quem tem ficha mas não responde pelo inventário fala da própria
 * mala — no registro, na listagem, no CSV e no painel.
 */
class StockTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_o_saldo_central_e_a_conta_das_linhas_e_o_campo_de_quantidade_nao_existe(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ficha = $this->tecnico($empresa, 'Ana Field');
        $produto = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410', 'unit' => 'kg', 'cost' => 40]);
        $ordem = $this->ordem($empresa, 'OS-100', 'Instalação', ['technician_id' => $ficha->id]);

        // Não existe coluna para digitar: se alguém adicionar uma, ela passa a viver
        // ao lado do saldo real e alguém vai acreditar nela.
        $this->assertFalse(Schema::hasColumn('products', 'quantity'), 'O saldo não é coluna do cadastro.');
        $this->assertFalse(Schema::hasColumn('products', 'stock'), 'O saldo não é coluna do cadastro.');

        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '10'])
            ->assertSessionHasNoErrors();
        $this->assertSame(10.0, $produto->saldoCentralAtual());

        // A carga sai do central e entra na mala: os dois saldos se movem juntos.
        $this->movimentar($admin, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '3', 'tecnico_id' => $ficha->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(7.0, $produto->saldoCentralAtual());
        $this->assertSame(3.0, (float) $this->carga($ficha, $produto)->quantity);

        // No ajuste o sinal mora na quantidade, não em um botão à parte.
        $this->movimentar($admin, ['tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '-2'])
            ->assertSessionHasNoErrors();
        $this->assertSame(5.0, $produto->saldoCentralAtual());

        // O consumo não toca o central: a unidade já saiu quando virou carga.
        $this->movimentar($admin, [
            'tipo' => 'consume', 'produto_id' => $produto->id, 'quantidade' => '1',
            'tecnico_id' => $ficha->id, 'ordem_id' => $ordem->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(5.0, $produto->saldoCentralAtual(), 'Consumo baixaria duas vezes a mesma unidade.');
        $this->assertSame(2.0, (float) $this->carga($ficha, $produto)->quantity);

        $this->assertSame(4, $this->linhas($produto)->count());
    }

    public function test_carga_que_passa_do_central_e_consumo_que_passa_da_mala_nao_chegam_a_existir(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ficha = $this->tecnico($empresa, 'Ana Field');
        $produto = $this->produto($empresa, 'Correia dentada', ['sku' => 'VC-300']);
        $ordem = $this->ordem($empresa, 'OS-101', 'Troca de correia', ['technician_id' => $ficha->id]);

        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '2'])
            ->assertSessionHasNoErrors();

        // Tem 2 no central: carregar 5 é inventar estoque.
        $this->movimentar($admin, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '5', 'tecnico_id' => $ficha->id,
        ])->assertSessionHasErrors('quantidade');

        $this->assertSame(1, $this->linhas($produto)->count(), 'Recusa no servidor não deixa linha.');
        $this->assertSame(2.0, $produto->saldoCentralAtual());
        $this->assertSame(0, $this->cargas($ficha, $produto)->count(), 'A linha de carga travada volta com a transação.');

        // Recompra e carrega 2 dos 5 que há: sobra 3 no central e 2 na mala, e os dois
        // saldos ficam visíveis ao mesmo tempo — é assim que se vê qual deles a recusa
        // protegeu.
        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '3'])
            ->assertSessionHasNoErrors();
        $this->movimentar($admin, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '2', 'tecnico_id' => $ficha->id,
        ])->assertSessionHasNoErrors();

        // Na mala há 2: gastar 3 devolve erro no dono da mala, não no produto.
        $this->movimentar($admin, [
            'tipo' => 'consume', 'produto_id' => $produto->id, 'quantidade' => '3',
            'tecnico_id' => $ficha->id, 'ordem_id' => $ordem->id,
        ])->assertSessionHasErrors('tecnico_id');

        $this->assertSame(3.0, $produto->saldoCentralAtual(), 'Nem a recusa nem o consumo baixam o central.');
        $this->assertSame(2.0, (float) $this->carga($ficha, $produto)->quantity);

        // A devolução que passa do que ele carrega cai pela mesma regra.
        $this->movimentar($admin, [
            'tipo' => 'return', 'produto_id' => $produto->id, 'quantidade' => '4', 'tecnico_id' => $ficha->id,
        ])->assertSessionHasErrors('tecnico_id');
        $this->assertSame(3, $this->linhas($produto)->count(), 'As três recusas não deixaram rastro no livro.');
    }

    public function test_a_linha_nasce_com_a_hora_e_o_autor_de_cá_e_nada_do_que_veio_no_pedido_muda_isso(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');
        $colega = $this->makeUser('administrator', $outra, 'b@test.local');
        $ficha = $this->tecnico($empresa, 'Ana Field');
        $produto = $this->produto($empresa, 'Resistência 4400W', ['sku' => 'RS-44']);

        $this->movimentar($admin, [
            'tipo' => 'purchase',
            'produto_id' => $produto->id,
            'quantidade' => '4',
            // Os quatro campos abaixo são a forgia inteira: data escolhida no
            // navegador, autor de outra empresa, empresa de outro tenant e técnico
            // num tipo que não tem técnico. Nenhum deles tem caminho até a linha.
            'recorded_at' => '2020-01-01 08:00:00',
            'user_id' => $colega->id,
            'company_id' => $outra->id,
            'technician_id' => $ficha->id,
        ])->assertSessionHasNoErrors();

        $linha = $this->linhas($produto)->firstOrFail();

        $this->assertSame($empresa->id, (int) $linha->company_id, 'A empresa vem do contexto, não do pedido.');
        $this->assertSame($admin->id, (int) $linha->user_id, 'O autor é quem está logado.');
        $this->assertNull($linha->technician_id, 'Compra é fato do estoque central: não tem mala.');
        $this->assertSame(now()->year, $linha->recorded_at->year, 'A hora gravada é a de cá.');
        $this->assertTrue($linha->recorded_at->isToday(), 'Nada se registra em 2020 a partir de hoje.');

        // A trilha conta o ato com o número que o banco passou a ter.
        $trilha = AuditLog::query()->withoutGlobalScopes()
            ->where('action', 'movimentação de estoque')
            ->latest('id')
            ->first();

        $this->assertNotNull($trilha, 'Movimentação sem rastro de auditoria é fato sem testemunha.');
        $this->assertStringContainsString('Resistência 4400W', (string) $trilha->description);
        $this->assertStringContainsString('4,00 un', (string) $trilha->description);
    }

    public function test_ajuste_de_inventário_e_de_quem_responde_pelo_inventário(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $funcionario = $this->makeUser('employee', $empresa, 'e@test.local');
        $gestor = $this->makeUser('supervisor', $empresa, 's@test.local');
        $cliente = $this->makeUser('client', $empresa, 'c@test.local');
        $produto = $this->produto($empresa, 'Fluxômetro', ['sku' => 'FL-10']);

        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '8'])
            ->assertSessionHasNoErrors();

        // O funcionário movimenta (stock.move) e não responde pelo inventário: o tipo
        // nem está na lista que o servidor aceita, então o pedido cai no campo.
        $this->movimentar($funcionario, ['tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '-1'])
            ->assertSessionHasErrors('tipo');
        $this->assertSame(8.0, $produto->saldoCentralAtual());

        // Ler e registrar pedem permissão do módulo: a conta de cliente não passa da
        // rota, seja para ver o livro seja para escrever nele.
        $this->actingAs($cliente)->get(route('movements.index'))->assertForbidden();
        $this->actingAs($cliente)->post(route('movements.store'), [
            'tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '1',
        ])->assertForbidden();

        // Ajuste que não mexe em nada não é ajuste.
        $this->movimentar($gestor, ['tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '0'])
            ->assertSessionHasErrors('quantidade');

        $this->movimentar($gestor, ['tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '-3'])
            ->assertSessionHasNoErrors();
        $this->assertSame(5.0, $produto->saldoCentralAtual());

        $ajuste = $this->linhas($produto)->where('type', 'adjustment')->firstOrFail();
        $this->assertSame(-3.0, (float) $ajuste->quantity, 'O sinal do ajuste mora na quantidade.');
        $this->assertSame('estoque central', $ajuste->escopoDa());

        // E o ajuste pode achar meia unidade que o banco não tinha registrado — no
        // mesmo formato decimal que o `input type="number"` envia em todo o app.
        $this->movimentar($gestor, ['tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '0.5'])
            ->assertSessionHasNoErrors();
        $this->assertSame(5.5, $produto->saldoCentralAtual());

        // Vírgula não é número para o validador: o que chega é recusado, não virar
        // `'0,5'` → `(float) 0`, que registraria um ajuste silencioso de nada.
        $this->movimentar($gestor, ['tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '0,5'])
            ->assertSessionHasErrors('quantidade');
        $this->assertSame(5.5, $produto->saldoCentralAtual());
    }

    public function test_o_estoque_nao_oferece_rota_para_editar_apagar_ou_estornar_uma_linha(): void
    {
        $rotas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($rota) => Str::startsWith($rota->uri(), 'estoque'))
            ->map(fn ($rota) => implode('|', $rota->methods()).' /'.$rota->uri())
            ->sort()
            ->values()
            ->all();

        // Livro-caixa não tem caneta para riscar: o que estava errado se responde com
        // outra linha, e as duas continuam contando o saldo.
        $this->assertSame([
            'GET|HEAD /estoque',
            'GET|HEAD /estoque/exportar',
            'GET|HEAD /estoque/registrar',
            'POST /estoque',
        ], $rotas);
    }

    public function test_quem_tem_ficha_mas_nao_responde_pelo_inventário_fala_da_própria_mala(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $minha = $this->tecnico($empresa, 'Ana Field');
        $alheia = $this->tecnico($empresa, 'Bruno Oficina');
        $conta = $this->contaDeTécnico($empresa, $minha);
        $produto = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410']);

        $minhaNota = 'Carga que eu mesmo retirei.';
        $alheiaNota = 'Carga do Bruno, registrada pelo escritório.';
        $compraNota = 'Compra da empresa inteira.';

        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '20'])
            ->assertSessionHasNoErrors();
        $this->movimentar($admin, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '4',
            'tecnico_id' => $minha->id, 'observacao' => $minhaNota,
        ])->assertSessionHasNoErrors();
        $this->movimentar($admin, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '6',
            'tecnico_id' => $alheia->id, 'observacao' => $alheiaNota,
        ])->assertSessionHasNoErrors();
        $this->movimentar($admin, [
            'tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '5', 'observacao' => $compraNota,
        ])->assertSessionHasNoErrors();

        // A listagem dele tem as linhas em que o nome dele aparece, e nada além.
        $pagina = $this->actingAs($conta)->get(route('movements.index'))->assertOk();
        $pagina->assertSee($minhaNota);
        $pagina->assertDontSee($alheiaNota, false);
        $pagina->assertDontSee($compraNota, false);

        // A tela nem oferece o campo de técnico, nem os tipos que não são da mala dele.
        $form = $this->actingAs($conta)->get(route('movements.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('name="tecnico_id"', $form, 'A carga dele não é escolha.');
        $this->assertStringNotContainsString('value="purchase"', $form);
        $this->assertStringNotContainsString('value="adjustment"', $form);

        // Ainda assim, forjar no POST o técnico de outro nome não move a mala alheia.
        $this->movimentar($conta, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '1', 'tecnico_id' => $alheia->id,
        ])->assertSessionHasNoErrors();

        $linha = StockMovement::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame($minha->id, (int) $linha->technician_id, 'A conta restrita responde pela própria ficha.');
        $this->assertSame(5.0, (float) $this->carga($minha, $produto)->quantity);
        $this->assertSame(6.0, (float) $this->carga($alheia, $produto)->quantity);

        // O tipo que não está na lista dele também não entra pelo POST.
        $this->movimentar($conta, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '9'])
            ->assertSessionHasErrors('tipo');

        // Exportar é de quem exporta: o alcance de leitura não entrega o CSV do vizinho.
        $this->actingAs($conta)->get(route('movements.export'))->assertForbidden();
    }

    public function test_o_consumo_ped_a_ordem_em_que_a_unidade_foi_gasta(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ficha = $this->tecnico($empresa, 'Ana Field');
        $colega = $this->tecnico($empresa, 'Bruno Oficina');
        $produto = $this->produto($empresa, 'Sensor de pressão', ['sku' => 'SP-90']);

        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '10'])
            ->assertSessionHasNoErrors();
        $this->movimentar($admin, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '4', 'tecnico_id' => $ficha->id,
        ])->assertSessionHasNoErrors();

        // Sem ordem o gasto não tem para onde ir.
        $this->movimentar($admin, [
            'tipo' => 'consume', 'produto_id' => $produto->id, 'quantidade' => '1', 'tecnico_id' => $ficha->id,
        ])->assertSessionHasErrors('ordem_id');

        // Rascunho ainda não saiu da mesa e cancelada não aconteceu.
        foreach (['draft' => 'rascunho', 'canceled' => 'cancelada'] as $status => $motivo) {
            $estaOrdem = $this->ordem($empresa, 'OS-'.strtoupper($status), 'Ordem '.$motivo, [
                'status' => $status,
                'technician_id' => $ficha->id,
            ]);

            $this->movimentar($admin, [
                'tipo' => 'consume', 'produto_id' => $produto->id, 'quantidade' => '1',
                'tecnico_id' => $ficha->id, 'ordem_id' => $estaOrdem->id,
            ])->assertSessionHasErrors('ordem_id');

            $this->assertStringContainsString(
                $motivo,
                $this->erroFlash('ordem_id'),
                'A recusa diz por que a ordem '.$motivo.' não gasta estoque.'
            );
        }

        // Ordem de outra empresa nem existe para o pedido.
        $estranha = $this->ordem($this->makeCompany('bravo'), 'OS-900', 'Ordem de outra empresa');
        $this->movimentar($admin, [
            'tipo' => 'consume', 'produto_id' => $produto->id, 'quantidade' => '1',
            'tecnico_id' => $ficha->id, 'ordem_id' => $estranha->id,
        ])->assertSessionHasErrors('ordem_id');

        // Gastar na ordem do colega é assinar lançamento feito no lugar errado.
        $doColega = $this->ordem($empresa, 'OS-200', 'Manutenção do Bruno', ['technician_id' => $colega->id]);
        $this->movimentar($admin, [
            'tipo' => 'consume', 'produto_id' => $produto->id, 'quantidade' => '1',
            'tecnico_id' => $ficha->id, 'ordem_id' => $doColega->id,
        ])->assertSessionHasErrors('ordem_id');

        $this->assertSame(4.0, (float) $this->carga($ficha, $produto)->quantity);
        $this->assertSame(6.0, $produto->saldoCentralAtual(), 'Das 10 compradas, os 4 da carga já saíram do central.');

        // Na ordem em que ele está no quadro de comissão, passa — pelo caminho de sempre.
        $comissionada = $this->ordem($empresa, 'OS-300', 'Revisão em dupla');
        $this->actingAs($admin)->post(route('orders.assignments.store', $comissionada), ['tecnico_id' => $ficha->id])
            ->assertSessionHasNoErrors();

        $this->movimentar($admin, [
            'tipo' => 'consume', 'produto_id' => $produto->id, 'quantidade' => '2',
            'tecnico_id' => $ficha->id, 'ordem_id' => $comissionada->id,
        ])->assertSessionHasNoErrors();

        $consumo = $this->linhas($produto)->where('type', 'consume')->firstOrFail();
        $this->assertSame($comissionada->id, (int) $consumo->service_order_id);
        $this->assertSame(2.0, (float) $this->carga($ficha, $produto)->quantity);
        $this->assertSame(6.0, $produto->saldoCentralAtual(), 'Consumo não baixa o central de novo: baixou na carga.');
    }

    public function test_o_aviso_de_reposicao_sai_no_momento_em_que_o_saldo_cruza_o_ponto(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ficha = $this->tecnico($empresa, 'Ana Field');
        $produto = $this->produto($empresa, 'Óleo para bomba de vácuo', ['sku' => 'OL-33', 'reorder_point' => 5]);

        $estaResposta = $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '6']);
        $estaResposta->assertSessionHasNoErrors();
        $this->assertNull($this->flash('aviso'), 'Com 6 no central contra um mínimo de 5, não há o que avisar.');

        // 6 no central, carrega 2: a linha cruzou o ponto agora, não na semana em que
        // alguém abrir o painel.
        $cruzou = $this->movimentar($admin, [
            'tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '2', 'tecnico_id' => $ficha->id,
        ]);
        $aviso = $this->flash('aviso');

        $this->assertNotNull($aviso, 'Atravessar o ponto de reposição tem de aparecer na resposta que baixou o saldo.');
        $this->assertStringContainsString('Óleo para bomba de vácuo', strval($aviso));
        $this->assertStringContainsString('4,00 contra o mínimo de 5,00 un', strval($aviso));

        // E a recompra que devolve o saldo acima do ponto devolve o silêncio: o aviso
        // não é carimbado em toda linha do livro.
        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '4'])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->flash('aviso'), 'Voltou acima do ponto: o aviso se cala.');
        $this->assertSame(8.0, $produto->saldoCentralAtual());
    }

    public function test_a_listagem_filtra_o_que_o_banco_calcula_e_o_csv_devolve_o_mesmo_ponto(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ficha = $this->tecnico($empresa, 'Ana Field');
        $gas = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410']);
        $correia = $this->produto($empresa, 'Correia dentada', ['sku' => 'VC-300']);

        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $gas->id, 'quantidade' => '10', 'observacao' => 'Compra do mês do gás.'])
            ->assertSessionHasNoErrors();
        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $correia->id, 'quantidade' => '4', 'observacao' => 'Compra do mês das correias.'])
            ->assertSessionHasNoErrors();
        $this->movimentar($admin, ['tipo' => 'load', 'produto_id' => $gas->id, 'quantidade' => '2', 'tecnico_id' => $ficha->id, 'observacao' => 'Carga da escala de hoje.'])
            ->assertSessionHasNoErrors();

        $porTipo = $this->actingAs($admin)->get(route('movements.index', ['tipo' => 'load']))->assertOk();
        $porTipo->assertSee('Carga da escala de hoje.');
        $porTipo->assertDontSee('Compra do mês do gás.', false);

        $this->actingAs($admin)->get(route('movements.index', ['produto' => $gas->id]))->assertOk()
            ->assertDontSee('Compra do mês das correias.', false);

        $this->actingAs($admin)->get(route('movements.index', ['busca' => 'VC-300']))->assertOk()
            ->assertSee('Compra do mês das correias.')
            ->assertDontSee('Carga da escala de hoje.', false);

        $this->actingAs($admin)->get(route('movements.index', ['tecnico' => $ficha->id]))->assertOk()
            ->assertSee('Carga da escala de hoje.')
            ->assertDontSee('Compra do mês do gás.', false);

        // Fora da janela não há linha: o período é do recorded_at, não do cadastro.
        $this->actingAs($admin)->get(route('movements.index', [
            'inicio' => now()->addDay()->toDateString(),
            'fim' => now()->addWeek()->toDateString(),
        ]))->assertOk()->assertSee('Nenhuma movimentação com estes filtros');

        $csv = $this->actingAs($admin)->get(route('movements.export', ['tipo' => 'purchase']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Sem BOM o Excel abre os acentos errados.');
        $this->assertStringContainsString('Compra do mês do gás.', $csv);
        $this->assertStringNotContainsString('Carga da escala de hoje.', $csv, 'O CSV respeita o filtro da tela.');
        $this->assertStringContainsString(StatusCatalog::label('movement', 'purchase'), $csv);
        $this->assertStringContainsString('Gás R-410a', $csv);
    }

    public function test_o_painel_conta_o_estoque_para_quem_pode_ler_e_no_mesmo_alcance_da_listagem(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ficha = $this->tecnico($empresa, 'Ana Field');
        $alheia = $this->tecnico($empresa, 'Bruno Oficina');
        $conta = $this->contaDeTécnico($empresa, $ficha);
        $cliente = $this->makeUser('client', $empresa, 'c@test.local');
        $produto = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410', 'reorder_point' => 5]);

        // Sem stock.view o bloco não consulta o banco — e a tela não desenha o cartão.
        $this->assertSame([], $this->painel($cliente)['estoque'], 'Sem a permissão, nada sai do banco.');
        $this->actingAs($cliente)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Últimas movimentações', false);

        $this->movimentar($admin, ['tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '3'])
            ->assertSessionHasNoErrors();
        $this->movimentar($admin, ['tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '2', 'tecnico_id' => $ficha->id])
            ->assertSessionHasNoErrors();
        $this->movimentar($admin, ['tipo' => 'load', 'produto_id' => $produto->id, 'quantidade' => '1', 'tecnico_id' => $alheia->id])
            ->assertSessionHasNoErrors();

        $painel = $this->painel($admin);
        $hoje = collect($painel['kpis'])->firstWhere('label', 'Movimentações de hoje');
        $this->assertNotNull($hoje, 'O painel tem de responder o que se moveu hoje.');
        $this->assertSame(3, (int) $hoje['value']);
        $this->assertCount(3, $painel['estoque']['movimentacoes']);
        $this->assertCount(1, $painel['estoque']['reposicao'], '3 comprados e 3 baixados contra o mínimo de 5 é reposição.');

        // O mesmo alcance da tela: o técnico conta a própria carga, não a empresa.
        $doTécnico = $this->painel($conta);
        $this->assertSame(
            1,
            (int) collect($doTécnico['kpis'])->firstWhere('label', 'Movimentações de hoje')['value'],
            'O painel não pode mostrar mais do que a listagem mostra.'
        );
        $this->assertCount(1, $doTécnico['estoque']['movimentacoes']);

        // E os cartões existem na tela de quem lê: o do painel e a mala na ficha do técnico.
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Últimas movimentações')
            ->assertSee('Ver o livro-caixa')
            ->assertSee('Estoque para repor');

        $this->actingAs($admin)->get(route('technicians.show', $ficha))->assertOk()
            ->assertSee('Carga no nome dele')
            ->assertSee('Gás R-410a');

        // Quem tem a ficha não abre a tela do vizinho: o cartão não é porta de entrada.
        $this->actingAs($conta)->get(route('technicians.show', $alheia))->assertForbidden();
    }

    /** @param  array<string, mixed>  $campos  */
    private function movimentar(User $quem, array $campos): TestResponse
    {
        return $this->actingAs($quem)->post(route('movements.store'), $campos);
    }

    /**
     * A mensagem flash da última resposta, lida antes do próximo pedido: o Laravel só
     * aposenta o flash no fim da requisição seguinte, então a leitura tem de ser na
     * ordem dos fatos para a mensagem ser a de quem a escreveu.
     */
    private function flash(string $chave): mixed
    {
        return app('session.store')->get($chave);
    }

    /** O texto que a pessoa lê no campo — não apenas o fato de haver erro ali. */
    private function erroFlash(string $campo): string
    {
        $bolsa = app('session.store')->get('errors');

        return strval($bolsa?->getBag('default')->first($campo));
    }

    /**
     * O painel é montado fora de request, então o tenant vem da conta que o lê —
     * exatamente como o middleware faz quando a tela é pedida por HTTP.
     */
    private function painel(User $usuario): array
    {
        TenantContext::resolveFromUser($usuario);

        return (new DashboardMetrics($usuario))->toArray();
    }

    /** @return Builder<StockMovement> */
    private function linhas(Product $produto): Builder
    {
        return StockMovement::query()->withoutGlobalScopes()->where('product_id', $produto->id);
    }

    private function carga(Technician $ficha, Product $produto): TechnicianStock
    {
        return $this->cargas($ficha, $produto)->firstOrFail();
    }

    /** @return Builder<TechnicianStock> */
    private function cargas(Technician $ficha, Product $produto): Builder
    {
        return TechnicianStock::query()->withoutGlobalScopes()
            ->where('technician_id', $ficha->id)
            ->where('product_id', $produto->id);
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    private function contaDeTécnico(Company $empresa, Technician $ficha): User
    {
        TenantContext::set($empresa->id);
        $usuario = $this->makeUser('technician', $empresa, 'campo-'.$ficha->id.'@test.local');
        TenantContext::forget();

        $ficha->update(['user_id' => $usuario->id]);

        return $usuario->fresh();
    }

    /** @param  array<string, mixed>  $extras  */
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

    /** @param  array<string, mixed>  $extras  */
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

    /** @param  array<string, mixed>  $extras  */
    private function cliente(Company $empresa, string $nome, array $extras = []): Client
    {
        TenantContext::set($empresa->id);
        $cliente = Client::query()->create($extras + ['name' => $nome, 'status' => 'active']);
        TenantContext::forget();

        return $cliente;
    }

    /** @param  array<string, mixed>  $extras  */
    private function tecnico(Company $empresa, string $nome, array $extras = []): Technician
    {
        TenantContext::set($empresa->id);
        $ficha = Technician::query()->create($extras + ['name' => $nome, 'status' => 'available']);
        TenantContext::forget();

        return $ficha;
    }
}
