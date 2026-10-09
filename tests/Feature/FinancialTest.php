<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 17 é a carteira, e uma só regra segura ela em pé: estado não se digita, se
 * deriva. Estes testes amarram a regra pelos dois lados — nenhum pedido escreve
 * `status`, `occurred_at` ou a empresa, e nenhum dinheiro entra sem reescrever o
 * estado a partir da soma que o banco devolve com a linha travada. Em volta disso
 * fica o que a tela promete: vencido é relação com hoje, categoria é vocabulário,
 * despesa não tem cliente, e cobrar uma ordem é somar as linhas dela no banco, não
 * o valor que alguém digitou.
 */
class FinancialTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_o_módulo_nonão_oferece_rota_para_marcar_conta_como_paga(): void
    {
        $rotas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($rota) => str_starts_with($rota->uri(), 'financeiro'))
            ->map(fn ($rota) => $rota->methods()[0].' /'.$rota->uri())
            ->unique()
            ->sort(SORT_STRING)
            ->values()
            ->all();

        // As únicas escritas de estado são o dinheiro e as duas decisões que o
        // substituem. Se alguém adicionar "PUT /financeiro/{registro}/pago", este
        // teste grita antes de a tela nascer com a caneta que riscava o extrato.
        $this->assertSame([
            'DELETE /financeiro/{registro}',
            'DELETE /financeiro/{registro}/pagamentos/{pagamento}',
            'GET /financeiro',
            'GET /financeiro/exportar',
            'GET /financeiro/nova',
            'GET /financeiro/{registro}',
            'GET /financeiro/{registro}/editar',
            'PATCH /financeiro/{registro}/cancelar',
            'PATCH /financeiro/{registro}/reabrir',
            'PATCH /financeiro/{registro}/restaurar',
            'POST /financeiro',
            'POST /financeiro/{registro}/pagamentos',
            'PUT /financeiro/{registro}',
        ], $rotas);
    }

    public function test_nenhum_pedido_escreve_o_estado_a_data_do_fato_ou_a_empresa(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estranha = $this->makeCompany('bravo');
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        // O formulário de abertura não oferece estado nem data de ocorrência: quem
        // abre a conta não pode escolher "quitado" antes de haver um centavo.
        $formulario = $this->semEspaços($this->actingAs($admin)->get(route('financial.create'))->assertOk()->getContent());
        $this->assertStringNotContainsString('name="status"', $formulario, 'O estado não é campo de formulário.');
        $this->assertStringNotContainsString('name="occurred_at"', $formulario, 'A data do fato nasce do pagamento.');

        $this->abrindoConta($admin, [
            'descricao' => 'Cobrança da OS-700',
            'cliente_id' => $cliente->id,
            // A forgia inteira: estado quitado, data em 2020 e empresa de fora.
            'status' => FinancialRecord::PAID,
            'occurred_at' => '2020-01-01',
            'company_id' => $estranha->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $conta = $this->contaPorDescricao('Cobrança da OS-700');

        $this->assertSame(FinancialRecord::PENDING, $conta->status, 'Conta nasce em aberto: ainda não há dinheiro.');
        $this->assertNull($conta->occurred_at, 'O que não aconteceu não tem data de ocorrência.');
        $this->assertSame($empresa->id, (int) $conta->company_id, 'A empresa vem do contexto, não do pedido.');
        $this->assertStringContainsString(
            'ainda não há nenhum',
            strval($this->flash('status')),
            'A resposta já diz de onde vem o estado da conta.'
        );

        // A trilha conta a abertura com o número que o banco passou a ter.
        $trilha = $this->ultimaTrilha('lançamento financeiro');
        $this->assertStringContainsString('R$ 200,00', $trilha);
        $this->assertStringContainsString('Cobrança da OS-700', $trilha);
    }

    public function test_o_dinheiro_registrado_é_o_que_move_a_conta_e_a_data_do_fato_é_a_do_último(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $conta = $this->novaConta($empresa, ['amount' => 500]);

        $this->recebendo($admin, $conta, ['valor' => '200.00', 'data' => '2026-10-01'])
            ->assertSessionHasNoErrors()->assertRedirect(route('financial.show', $conta));

        $estaConta = $this->recarregando($conta);
        $this->assertSame(FinancialRecord::PARTIAL, $estaConta->status);
        $this->assertSame('2026-10-01', $estaConta->occurred_at->toDateString(), 'A data do fato é a do dinheiro que entrou.');
        $this->assertSame(300.0, $estaConta->saldo());

        // Meio paga: a ficha mostra o que falta, e é este número — não os 500 previstos
        // — que o painel soma em "a receber".
        $ficha = $this->semEspaços($this->actingAs($admin)->get(route('financial.show', $conta))->assertOk()->getContent());
        $this->assertStringContainsString('Recebido em parte', $ficha);
        $this->assertStringContainsString('<span>Recebido</span> <strong class="nf-mono">R$ 200,00</strong>', $ficha);
        $this->assertStringContainsString('<span>Saldo</span> <strong class="nf-mono">R$ 300,00</strong>', $ficha);

        $this->recebendo($admin, $conta, ['valor' => '300.00', 'data' => '2026-10-04', 'metodo' => 'credit_card'])
            ->assertSessionHasNoErrors();

        $estaConta = $this->recarregando($conta);
        $this->assertSame(FinancialRecord::PAID, $estaConta->status);
        $this->assertSame('2026-10-04', $estaConta->occurred_at->toDateString(), 'Duas linhas: a data do fato é o max(), não o primeiro pagamento.');
        $this->assertSame(0.0, $estaConta->saldo());
        $this->assertSame(500.0, $estaConta->pagoNoBanco());

        $ficha = $this->semEspaços($this->actingAs($admin)->get(route('financial.show', $conta))->assertOk()->getContent());
        $this->assertStringContainsString('Conta quitada', $ficha);
        $this->assertStringContainsString('PIX', $ficha);
        $this->assertStringContainsString('Cartão de crédito', $ficha);
        $this->assertStringNotContainsString('name="valor"', $ficha, 'Conta quitada não oferece lançamento a mais.');
        $this->assertStringContainsString('<span>Pagamentos registrados</span> <strong class="nf-mono">2</strong>', $ficha);
    }

    public function test_dinheiro_acima_do_saldo_conta_quitada_cancelada_e_data_fora_do_caixa_são_recusados(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $conta = $this->novaConta($empresa, ['amount' => 500]);

        // Passa do valor: a resposta devolve quanto falta, em vez de aceitar a
        // generosidade e deixar saldo negativo na carteira.
        $estaRecusa = $this->recebendo($admin, $conta, ['valor' => '600.00']);
        $estaRecusa->assertSessionHasErrors('valor');
        $this->assertStringContainsString('Faltam R$ 500,00', $this->erroFlash('valor'));
        $this->assertSame(0, $this->pagamentosDa($conta)->count(), 'Recusa no servidor não deixa linha.');

        $this->recebendo($admin, $conta, ['valor' => '200.00'])->assertSessionHasNoErrors();

        // Sobram 300: 400 não entra.
        $this->recebendo($admin, $conta, ['valor' => '400.00'])->assertSessionHasErrors('valor');
        $this->assertStringContainsString('Faltam R$ 300,00', $this->erroFlash('valor'));
        $this->assertSame(1, $this->pagamentosDa($conta)->count());

        $this->recebendo($admin, $conta, ['valor' => '300.00'])->assertSessionHasNoErrors();

        // Quitada não recebe nada: dinheiro a mais pertence a outra conta.
        $this->recebendo($admin, $conta, ['valor' => '10.00'])->assertSessionHasErrors('valor');
        $this->assertStringContainsString('já está quitada', $this->erroFlash('valor'));

        // Data futura é previsão, e exercício virado não se reabre por aqui.
        $this->recebendo($admin, $conta, ['data' => '2026-10-09'])->assertSessionHasErrors('data');
        $this->assertStringContainsString('ainda não mudou de mão', $this->erroFlash('data'));
        $this->recebendo($admin, $conta, ['data' => '2020-01-01'])->assertSessionHasErrors('data');

        // Vírgula não é número: recusado, não virar `'2,50'` → 2 no silêncioso.
        $this->recebendo($admin, $conta, ['valor' => '2,50'])->assertSessionHasErrors('valor');

        // Método fora do catálogo não existe no extrato de ninguém.
        $this->recebendo($admin, $conta, ['metodo' => 'boleto'])->assertSessionHasErrors('metodo');

        $this->assertSame(2, $this->pagamentosDa($conta)->count(), 'Nenhuma das recusas escreveu linha.');
        $this->assertSame(500.0, $this->recarregando($conta)->pagoNoBanco());

        // Conta cancelada não recebe pagamento: o caminho honesto é reabrir.
        $cancelada = $this->novaConta($empresa, [
            'description' => 'Cobrança anulada',
            'amount' => 90,
            'status' => FinancialRecord::CANCELED,
        ]);

        $estaRecusa = $this->recebendo($admin, $cancelada, ['valor' => '90.00']);
        $estaRecusa->assertSessionHasErrors('valor');
        $this->assertStringContainsString('reabra antes', $this->erroFlash('valor'));
        $this->assertSame(0, $this->pagamentosDa($cancelada)->count());
    }

    public function test_estornar_é_degrau_de_quem_responde_pelo_caixa_e_devolve_a_conta_ao_estado_anterior(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $gestor = $this->makeUser('supervisor', $empresa, 's@test.local');
        $funcionaria = $this->makeUser('employee', $empresa, 'e@test.local');
        $conta = $this->novaConta($empresa, ['amount' => 500]);
        $colega = $this->novaConta($empresa, ['amount' => 120, 'description' => 'Cobrança do colega']);

        $this->recebendo($gestor, $conta, ['valor' => '500.00', 'data' => '2026-10-02', 'referencia' => 'AUT-9911'])
            ->assertSessionHasNoErrors();
        $this->assertSame(FinancialRecord::PAID, $this->recarregando($conta)->status);

        $pagamento = $this->pagamentosDa($conta)->firstOrFail();

        // Quem tem a permissão de registrar não tem a de desfazer: o estorno é o
        // degrau acima, porque some um fato do caixa.
        $this->actingAs($funcionaria)
            ->delete(route('financial.payments.destroy', [$conta, $pagamento]))
            ->assertForbidden();
        $this->assertSame(1, $this->pagamentosDa($conta)->count(), 'O 403 não desfaz nada.');

        // Pagamento de outra conta é 404 antes de qualquer escrita: estornar a linha
        // do colega é a mesma chave que escreve na carteira errada.
        $this->recebendo($gestor, $colega, ['valor' => '40.00'])->assertSessionHasNoErrors();
        $linhaDoColega = $this->pagamentosDa($colega)->firstOrFail();

        $this->actingAs($gestor)->delete(route('financial.payments.destroy', [$conta, $linhaDoColega]))
            ->assertNotFound();
        $this->assertSame(1, $this->pagamentosDa($colega)->count(), 'O 404 não desfez a linha do colega.');

        $this->actingAs($gestor)->delete(route('financial.payments.destroy', [$conta, $pagamento]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('financial.show', $conta));

        $estaConta = $this->recarregando($conta);
        $this->assertSame(0, $this->pagamentosDa($conta)->count());
        $this->assertSame(FinancialRecord::PENDING, $estaConta->status, 'Sem linha de dinheiro, a conta volta a estar em aberto.');
        $this->assertNull($estaConta->occurred_at, 'O estorno puxa a data do fato junto.');
        $this->assertStringContainsString('voltou a ficar em aberto', strval($this->flash('status')));

        $trilha = $this->ultimaTrilha('estorno de pagamento');
        $this->assertStringContainsString('R$ 500,00', $trilha);
        $this->assertStringContainsString('PIX', $trilha);
        $this->assertStringContainsString('Usuário supervisor', $trilha, 'O estorno guarda quem desfez.');

        // A linha do colega seguiu viva, e é assinada por quem a registrou.
        $this->assertSame($gestor->id, (int) $linhaDoColega->user_id);

        $this->recebendo($admin, $colega, ['valor' => '80.00', 'referencia' => 'AUT-1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(
            $admin->id,
            (int) $this->pagamentosDa($colega)->last()->user_id,
            'O pagamento é assinado por quem está logado, não por quem abriu a conta.'
        );
        $this->assertSame(FinancialRecord::PAID, $this->recarregando($colega)->status, '40 e 80 somam a conta de 120.');
    }

    public function test_conta_com_dinheiro_nonão_mexe_em_valor_tipo_nem_se_cancela_ou_apaga(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $conta = $this->novaConta($empresa, [
            'amount' => 500,
            'client_id' => $this->cliente($empresa, 'Vinha d’Uva')->id,
        ]);

        $this->recebendo($admin, $conta, ['valor' => '150.00'])->assertSessionHasNoErrors();

        // Mexer no valor de uma conta meio paga é mover a régua debaixo do dinheiro.
        $this->editando($admin, $conta, ['valor' => '600.00'])->assertSessionHasErrors('valor');
        $this->assertStringContainsString('estorne o pagamento', $this->erroFlash('valor'));
        $this->assertSame(500.0, (float) $this->recarregando($conta)->amount);

        $this->editando($admin, $conta, ['tipo' => FinancialRecord::EXPENSE, 'categoria' => 'deslocamento'])
            ->assertSessionHasErrors('tipo');
        $this->assertSame(FinancialRecord::REVENUE, $this->recarregando($conta)->type);

        // O que não mexe no caixa, mexe: descrição e vencimento seguem editáveis.
        $this->editando($admin, $conta, ['descricao' => 'Cobrança renegociada', 'vencimento' => '2026-11-30'])
            ->assertSessionHasNoErrors();
        $estaConta = $this->recarregando($conta);
        $this->assertSame('Cobrança renegociada', $estaConta->description);
        $this->assertSame('2026-11-30', $estaConta->due_date->toDateString());
        $this->assertSame(FinancialRecord::PARTIAL, $estaConta->status, 'Editar conta não reescreve estado.');

        // Com dinheiro registrado, cancelar e excluir estão fora de cogitação.
        $this->actingAs($admin)->patch(route('financial.cancel', $conta), ['motivo' => 'Acordo com o cliente.'])
            ->assertSessionHasErrors('motivo');
        $this->assertStringContainsString('Estorne o pagamento antes de cancelar', $this->erroFlash('motivo'));
        $this->assertSame(FinancialRecord::PARTIAL, $this->recarregando($conta)->status);

        $this->actingAs($admin)->delete(route('financial.destroy', $conta))->assertSessionHas('erro');
        $this->assertStringContainsString('sem perder o dinheiro', strval($this->flash('erro')));
        $this->assertNull($this->recarregando($conta)->deleted_at, 'Conta com dinheiro não sai do banco por exclusão.');

        // Esvaziada a conta, o cancelamento passa a ser decisão legítima — com motivo.
        $this->actingAs($admin)->delete(route('financial.payments.destroy', [
            $conta,
            $this->pagamentosDa($conta)->firstOrFail(),
        ]))->assertSessionHasNoErrors();

        // Três letras não explicam um número que sumiu do caixa: o mínimo é cinco.
        $this->actingAs($admin)->patch(route('financial.cancel', $conta), ['motivo' => 'sim'])->assertSessionHasErrors('motivo');
        $this->assertStringContainsString('pelo menos cinco', $this->erroFlash('motivo'));

        $this->actingAs($admin)->patch(route('financial.cancel', $conta), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->assertStringContainsString('sem dizer por quê', $this->erroFlash('motivo'));

        $this->actingAs($admin)->patch(route('financial.cancel', $conta), ['motivo' => 'Serviço não realizado, cliente desfez o contrato.'])
            ->assertSessionHasNoErrors();

        $estaConta = $this->recarregando($conta);
        $this->assertSame(FinancialRecord::CANCELED, $estaConta->status);
        $this->assertNull($estaConta->occurred_at, 'Conta cancelada não tem data de fato.');
        $this->assertStringContainsString('Serviço não realizado', strval($estaConta->notes));

        $this->assertStringContainsString('Cancelamento: Serviço não realizado', $this->semEspaços(
            $this->actingAs($admin)->get(route('financial.show', $conta))->assertOk()->getContent()
        ));

        // Reabrir o que ninguém cancelou não é reabrir.
        $aberta = $this->novaConta($empresa, ['description' => 'Conta que ninguém cancelou']);
        $this->actingAs($admin)->patch(route('financial.reopen', $aberta))->assertSessionHas('erro');
        $this->assertStringContainsString('Só se reabre', strval($this->flash('erro')));

        // Reabrir a cancelada devolve a conta à derivação: sem pagamento, em aberto.
        $this->actingAs($admin)->patch(route('financial.reopen', $conta))->assertSessionHasNoErrors();
        $this->assertSame(FinancialRecord::PENDING, $this->recarregando($conta)->status);
        $this->assertStringContainsString('reaberta e de novo a receber', strval($this->flash('status')), 'A resposta descreve o estado derivado, não a intenção de quem clicou.');

        // A funcionária cobra, mas não responde pelo caixa: nem cancela nem reabre.
        $funcionaria = $this->makeUser('employee', $empresa, 'e@test.local');
        $this->actingAs($funcionaria)->patch(route('financial.cancel', $aberta), ['motivo' => 'Sem a permissão.'])->assertForbidden();
        $this->actingAs($funcionaria)->patch(route('financial.reopen', $aberta))->assertForbidden();
        $this->assertSame(FinancialRecord::PENDING, $this->recarregando($aberta)->status);
    }

    public function test_categoria_é_vocabulário_do_tipo_e_despesa_nonão_tem_cliente(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $vinha = $this->cliente($empresa, 'Vinha d’Uva');
        $beta = $this->makeCompany('bravo');

        // Categoria é o vocabulário pelo qual o relatório de fase 18 soma: a do outro
        // lado do caixa não serve, e a do legado ('servico') não passa.
        $estaRecusa = $this->abrindoConta($admin, ['categoria' => 'combustivel', 'cliente_id' => $padaria->id]);
        $estaRecusa->assertSessionHasErrors('categoria');
        $this->assertStringContainsString('não existe no catálogo', $this->erroFlash('categoria'));

        $this->abrindoConta($admin, ['tipo' => FinancialRecord::EXPENSE, 'categoria' => 'mao_de_obra_e_pecas'])
            ->assertSessionHasErrors('categoria');
        $this->abrindoConta($admin, ['categoria' => 'servico', 'cliente_id' => $padaria->id])
            ->assertSessionHasErrors('categoria');

        // Receita se cobra de alguém; despesa tem fornecedor, e fornecedor não está no
        // schema desta casa — o client_id que chegar numa despesa é descartado.
        $this->abrindoConta($admin, ['cliente_id' => ''])->assertSessionHasErrors('cliente_id');

        $this->abrindoConta($admin, [
            'tipo' => FinancialRecord::EXPENSE,
            'categoria' => 'deslocamento',
            'descricao' => 'Deslocamento do técnico',
            'valor' => '80.00',
            'cliente_id' => $padaria->id,
        ])->assertSessionHasNoErrors();

        $despesa = $this->contaPorDescricao('Deslocamento do técnico');
        $this->assertNull($despesa->client_id, 'A despesa descartou o cliente: nem a tela oferece, nem o servidor aplica.');
        $ficha = $this->semEspaços($this->actingAs($admin)->get(route('financial.show', $despesa))->assertOk()->getContent());
        $this->assertStringContainsString('Despesa não se cobra de cliente', $ficha);

        // Cliente inativo é cadastro velho, e cliente de outra empresa nem existe.
        $inativo = $this->cliente($empresa, 'Depósito Velho', ['status' => 'inactive']);
        $this->abrindoConta($admin, ['cliente_id' => $inativo->id])->assertSessionHasErrors('cliente_id');
        $this->abrindoConta($admin, ['cliente_id' => $this->cliente($beta, 'Cliente Beta')->id])
            ->assertSessionHasErrors('cliente_id');

        // Ordem e cliente apontam para carteiras diferentes: a validação de campo vê
        // cada um isolado, e é aqui que o par é conferido.
        $ordem = $this->ordem($empresa, 'OS-800', 'Refrigeração da Padaria', ['client_id' => $padaria->id]);

        $cruzada = $this->abrindoConta($admin, [
            'descricao' => 'Cobrança trocada',
            'cliente_id' => $vinha->id,
            'ordem_id' => $ordem->id,
        ]);
        $cruzada->assertSessionHasErrors('ordem_id');
        $this->assertStringContainsString('pertence a Padaria Sant’Anna', $this->erroFlash('ordem_id'));
        $this->assertSame(0, $this->contandoContas('Cobrança trocada'));

        // Conta de zero e valor que não cabem na coluna ficam na validação.
        $this->abrindoConta($admin, ['valor' => '0', 'cliente_id' => $padaria->id])->assertSessionHasErrors('valor');
        $this->abrindoConta($admin, ['valor' => '9999999999999.00', 'cliente_id' => $padaria->id])->assertSessionHasErrors('valor');
        $this->abrindoConta($admin, ['valor' => '12,50', 'cliente_id' => $padaria->id])->assertSessionHasErrors('valor');
    }

    public function test_cobrar_a_ordem_só_acontece_com_o_serviço_terminado_e_o_valor_vem_da_soma_das_linhas(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $gestor = $this->makeUser('supervisor', $empresa, 's@test.local');
        $campo = $this->makeUser('technician', $empresa, 'c@test.local');
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $vinha = $this->cliente($empresa, 'Vinha d’Uva');

        $ordem = $this->ordem($empresa, 'OS-900', 'Câmara fria da Padaria', [
            'status' => 'completed',
            'client_id' => $padaria->id,
            'discount' => 40,
        ]);
        $this->linhasDaOrdem($ordem, [
            ['servico', 'Manutenção da câmara fria', 2, 700],
            ['produto', 'Válvula expansionista', 1, 120],
        ]);

        // O request traz vencimento e categoria; valor e cliente são lidos da ordem.
        $estaCobranca = $this->cobrando($gestor, $ordem, [
            'vencimento' => '2026-10-25',
            'categoria' => 'mao_de_obra_e_pecas',
            'valor' => '900.00',
            'cliente_id' => $vinha->id,
            'company_id' => $this->makeCompany('bravo')->id,
        ]);
        $estaCobranca->assertSessionHasNoErrors()->assertRedirect();

        $conta = $this->contaPorDescricao('Cobrança da OS-900 — Câmara fria da Padaria');
        $this->assertSame(1480.00, (float) $conta->amount, 'Cobrar é somar as linhas no banco: 2x700 + 120 - 40.');
        $this->assertSame($padaria->id, (int) $conta->client_id, 'O cliente é o da ordem, não o do formulário.');
        $this->assertSame($empresa->id, (int) $conta->company_id);
        $this->assertSame(FinancialRecord::PENDING, $conta->status);
        $this->assertStringContainsString('não do formulário', strval($this->flash('status')));

        // Cobrança duplicada não é segunda via.
        $this->cobrando($gestor, $ordem, ['vencimento' => '2026-11-05', 'categoria' => 'visita_tecnica'])->assertSessionHas('erro');
        $this->assertStringContainsString('já tem a cobrança', strval($this->flash('erro')));
        $this->assertSame(1, $this->contandoContas('Cobrança da OS-900%'));

        // Ordem que ainda não terminou não fecha conta, e cancelada não aconteceu.
        $aberta = $this->ordem($empresa, 'OS-901', 'Coifa em aberto', ['client_id' => $padaria->id]);
        $this->linhasDaOrdem($aberta, [['servico', 'Limpeza da coifa', 1, 200]]);
        $this->cobrando($gestor, $aberta, ['vencimento' => '2026-10-25', 'categoria' => 'visita_tecnica'])->assertSessionHas('erro');
        $this->assertStringContainsString('ainda não terminou', strval($this->flash('erro')));

        $cancelada = $this->ordem($empresa, 'OS-902', 'Instalação desfeita', [
            'status' => 'canceled',
            'client_id' => $padaria->id,
        ]);
        $this->cobrando($gestor, $cancelada, ['vencimento' => '2026-10-25', 'categoria' => 'visita_tecnica'])->assertSessionHas('erro');
        $this->assertStringContainsString('foi cancelada', strval($this->flash('erro')));

        // Concluída sem linhas: a conta de R$ 0,00 não nasce.
        $semLinhas = $this->ordem($empresa, 'OS-903', 'Visita sem registro', ['status' => 'completed', 'client_id' => $padaria->id]);
        $this->cobrando($gestor, $semLinhas, ['vencimento' => '2026-10-25', 'categoria' => 'visita_tecnica'])->assertSessionHas('erro');
        $this->assertStringContainsString('não tem o que cobrar', strval($this->flash('erro')));
        $this->assertSame(0, $this->contandoContas('Cobrança da OS-903%'));

        // Cancelar a cobrança libera emitir outra: cancelamento não é duplicata.
        $this->actingAs($gestor)->patch(route('financial.cancel', $conta), ['motivo' => 'Emitida com a categoria errada.'])
            ->assertSessionHasNoErrors();
        $this->cobrando($gestor, $ordem, ['vencimento' => '2026-12-01', 'categoria' => 'visita_tecnica'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $this->contandoContas('Cobrança da OS-900%'));

        // A ponte é escrita no financeiro: quem executa a ordem e não responde pela
        // conta não emite cobrança — e nem chega perto da carteira.
        $this->cobrando($campo, $ordem, ['vencimento' => '2026-12-10', 'categoria' => 'visita_tecnica'])->assertForbidden();
        $this->actingAs($campo)->get(route('financial.index'))->assertForbidden();

        // A ficha da ordem só mostra a ponte para quem lê a carteira: quem executa o
        // serviço vê o total previsto, mas nem a cobrança existente nem o formulário
        // de emissão chegam perto dele.
        $fichaDoTecnico = $this->semEspaços(
            $this->actingAs($campo)->get(route('orders.show', $ordem))->assertOk()->getContent()
        );
        $this->assertStringNotContainsString('Cobrança desta ordem na carteira', $fichaDoTecnico);
        $this->assertStringNotContainsString('Emitir a cobrança', $fichaDoTecnico);
        $this->assertStringNotContainsString('name="vencimento"', $fichaDoTecnico);
        $this->assertStringContainsString('Total a cobrar', $fichaDoTecnico, 'O técnico continua lendo a conta prevista da ordem.');

        $ficha = $this->semEspaços($this->actingAs($gestor)->get(route('orders.show', $ordem))->assertOk()->getContent());
        $this->assertStringContainsString('Cobrança desta ordem na carteira', $ficha);
        $this->assertStringContainsString('R$ 1.480,00', $ficha);
    }

    public function test_a_carteira_filtra_no_banco_e_o_csv_devolve_o_mesmo_saldo_da_ficha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $funcionaria = $this->makeUser('employee', $empresa, 'e@test.local');
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $vinha = $this->cliente($empresa, 'Vinha d’Uva');

        $vencida = $this->novaConta($empresa, [
            'description' => 'Cobrança com atraso', 'amount' => 500, 'due_date' => '2026-09-25',
            'client_id' => $padaria->id, 'category' => 'contrato_mensal',
        ]);
        $this->recebendo($admin, $vencida, ['valor' => '200.00', 'data' => '2026-09-26'])->assertSessionHasNoErrors();

        $this->novaConta($empresa, [
            'description' => 'Cobrança a vencer', 'amount' => 300, 'due_date' => '2026-11-05', 'client_id' => $vinha->id,
        ]);
        $quitada = $this->novaConta($empresa, [
            'description' => 'Cobrança recebida', 'amount' => 120, 'due_date' => '2026-10-01', 'client_id' => $vinha->id,
        ]);
        $this->recebendo($admin, $quitada, ['valor' => '120.00', 'data' => '2026-10-02'])->assertSessionHasNoErrors();

        $this->novaConta($empresa, [
            'description' => 'Aluguel da base', 'amount' => 80, 'due_date' => '2026-10-30',
            'type' => FinancialRecord::EXPENSE, 'category' => 'instalacao',
        ]);

        $tela = $this->semEspaços($this->actingAs($admin)->get(route('financial.index'))->assertOk()->getContent());

        // Os quatro números do rodapé são a soma do conjunto exibido, e o vencido
        // obedece ao recorte a mais: em aberto com o prazo para trás.
        $this->assertStringContainsString('<span>Valor das contas</span> <strong class="nf-mono">R$ 1.000,00</strong>', $tela);
        $this->assertStringContainsString('<span>Já mudou de mão</span> <strong class="nf-mono">R$ 320,00</strong>', $tela);
        $this->assertStringContainsString('Em aberto</dt> <dd>R$ 680,00</dd>', $tela);
        $this->assertStringContainsString('Vencido <span class="nf-text-muted-2"> (1 conta) </span> </dt> <dd class="nf-status-canceled"> R$ 300,00', $tela);

        // A coluna "Pago" e o "Saldo" da linha leem a mesma expressão da ficha: 200 e
        // 300 na vencida meio paga, não 500 e zero.
        $this->assertStringContainsString('13 dias em atraso', $tela);
        $this->assertStringContainsString('Recebido em parte', $tela);
        $this->assertStringContainsString('ocorreu em 02/10/2026', $tela, 'Conta quitada mostra a data do fato, não o atraso.');
        $this->assertStringContainsString('Fornecedor não cadastrado', $tela);

        $soVencidas = $this->semEspaços($this->actingAs($admin)->get(route('financial.index', ['estado' => 'overdue']))->assertOk()->getContent());
        $this->assertStringContainsString('Cobrança com atraso', $soVencidas);
        foreach (['Cobrança a vencer', 'Cobrança recebida', 'Aluguel da base'] as $fora) {
            $this->assertStringNotContainsString($fora, $soVencidas, "O filtro de vencido deixou entrar [{$fora}].");
        }

        $soDespesas = $this->semEspaços($this->actingAs($admin)->get(route('financial.index', ['tipo' => FinancialRecord::EXPENSE]))->assertOk()->getContent());
        $this->assertStringContainsString('Aluguel da base', $soDespesas);
        $this->assertStringNotContainsString('Cobrança com atraso', $soDespesas);

        $this->actingAs($admin)->get(route('financial.index', ['categoria' => 'contrato_mensal']))->assertOk()
            ->assertSee('Cobrança com atraso')->assertDontSee('Aluguel da base', false);

        $this->actingAs($admin)->get(route('financial.index', ['cliente' => $vinha->id]))->assertOk()
            ->assertSee('Cobrança recebida')->assertDontSee('Cobrança com atraso', false);

        $this->actingAs($admin)->get(route('financial.index', ['busca' => 'atraso']))->assertOk()
            ->assertSee('Cobrança com atraso')->assertDontSee('Cobrança recebida', false);

        // O período é do vencimento previsto, não da data de cadastro.
        $janela = $this->semEspaços($this->actingAs($admin)->get(route('financial.index', [
            'inicio' => '2026-10-08', 'fim' => '2026-11-30',
        ]))->assertOk()->getContent());
        $this->assertStringContainsString('Cobrança a vencer', $janela);
        $this->assertStringContainsString('Aluguel da base', $janela);
        $this->assertStringNotContainsString('Cobrança com atraso', $janela);
        $this->assertStringNotContainsString('Cobrança recebida', $janela);

        $this->actingAs($admin)->get(route('financial.index', ['estado' => FinancialRecord::PAID]))->assertOk()
            ->assertSee('Cobrança recebida')->assertDontSee('Cobrança com atraso', false);

        // CSV: as mesmas colunas da tela, o mesmo saldo e o mesmo filtro.
        $csv = $this->actingAs($admin)->get(route('financial.export', ['estado' => 'overdue']))
            ->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Sem BOM o Excel abre os acentos errados.');
        $this->assertStringContainsString(
            'Vencimento;"Ocorrido em";Tipo;Categoria;Descrição;Cliente;Ordem;Valor;Pago;"Em aberto";Estado;"Dias em atraso";Observações',
            $csv
        );
        $this->assertStringContainsString('Contrato mensal', $csv);
        $this->assertStringContainsString('"R$ 500,00";"R$ 200,00";"R$ 300,00"', $csv, 'Pago e em aberto do CSV são os da tabela.');
        $this->assertStringContainsString('"Recebido em parte";13', $csv);
        $this->assertStringNotContainsString('Cobrança recebida', $csv, 'O CSV respeita o filtro da tela.');
        $this->assertStringNotContainsString('Aluguel da base', $csv);

        // Ler a carteira não entrega o CSV do vizinho: exportar é permissão própria.
        $this->actingAs($funcionaria)->get(route('financial.index'))->assertOk();
        $this->actingAs($funcionaria)->get(route('financial.export'))->assertForbidden();
    }

    public function test_a_carteira_de_fora_nonão_existe_e_sem_a_permissão_não_se_chega_na_tela(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $funcionaria = $this->makeUser('employee', $empresa, 'e@test.local');
        $campo = $this->makeUser('technician', $empresa, 'c@test.local');
        $cliente = $this->makeUser('client', $empresa, 'cl@test.local');

        $estranha = $this->makeCompany('bravo');
        $laFora = $this->novaConta($estranha, ['description' => 'Cobrança de outra empresa']);
        $donaDeFora = $this->makeUser('administrator', $estranha, 'b@test.local');
        TenantContext::set($estranha->id);
        $pagamentoFora = Payment::query()->create([
            'financial_record_id' => $laFora->id,
            'user_id' => $donaDeFora->id,
            'amount' => 10,
            'method' => 'pix',
            'paid_at' => '2026-10-01',
        ]);
        TenantContext::forget();

        $minha = $this->novaConta($empresa, ['description' => 'Cobrança da casa']);

        // Rota com id de outra empresa: o escopo resolve o binding e a linha some
        // antes de qualquer escrita — nem 403, que contaria que ela existe.
        $this->actingAs($admin)->get(route('financial.show', $laFora))->assertNotFound();
        $this->actingAs($admin)->get(route('financial.edit', $laFora))->assertNotFound();
        $this->editando($admin, $laFora)->assertNotFound();
        $this->actingAs($admin)->patch(route('financial.cancel', $laFora), ['motivo' => 'Conta do vizinho.'])->assertNotFound();
        $this->actingAs($admin)->patch(route('financial.reopen', $laFora))->assertNotFound();
        $this->actingAs($admin)->post(route('financial.payments.store', $laFora), [
            'valor' => '10.00', 'metodo' => 'pix', 'data' => '2026-10-02',
        ])->assertNotFound();
        $this->actingAs($admin)->delete(route('financial.payments.destroy', [$laFora, $pagamentoFora]))->assertNotFound();
        $this->actingAs($admin)->delete(route('financial.destroy', $laFora))->assertNotFound();
        $this->actingAs($admin)->patch(route('financial.restore', $laFora->id))->assertNotFound();

        // A listagem e a exportação também não enxergam a carteira alheia.
        $tela = $this->semEspaços($this->actingAs($admin)->get(route('financial.index'))->assertOk()->getContent());
        $this->assertStringContainsString('Cobrança da casa', $tela);
        $this->assertStringNotContainsString('Cobrança de outra empresa', $tela);

        $csv = $this->actingAs($admin)->get(route('financial.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Cobrança da casa', $csv);
        $this->assertStringNotContainsString('Cobrança de outra empresa', $csv);

        // Papéis sem o módulo: a rota recusa e nada chega ao banco.
        foreach ([$campo, $cliente] as $semPermissao) {
            $this->actingAs($semPermissao)->get(route('financial.index'))->assertForbidden();
            $this->actingAs($semPermissao)->get(route('financial.show', $minha))->assertForbidden();
            $this->abrindoConta($semPermissao, ['descricao' => 'Forjada', 'cliente_id' => $this->cliente($empresa, 'Quem paga')->id])
                ->assertForbidden();
            $this->assertSame(0, $this->contandoContas('Forjada'), 'O 403 não cria linha no banco.');
        }

        // A funcionária lê e cobra, mas não edita, não estorna, não cancela, não apaga.
        $this->actingAs($funcionaria)->get(route('financial.edit', $minha))->assertForbidden();
        $this->editando($funcionaria, $minha, ['descricao' => 'Reescrita'])->assertForbidden();
        $this->assertSame('Cobrança da casa', $this->recarregando($minha)->description);

        // A conta só sai do banco por exclusão de quem responde pelo cadastro, e volta
        // pela listagem de excluídos.
        $this->actingAs($funcionaria)->delete(route('financial.destroy', $minha))->assertForbidden();
        $this->actingAs($admin)->delete(route('financial.destroy', $minha))->assertSessionHasNoErrors();
        $this->assertNotNull($this->recarregando($minha)->deleted_at);

        $excluidas = $this->semEspaços($this->actingAs($admin)->get(route('financial.index', ['estado' => 'excluidos']))->assertOk()->getContent());
        $this->assertStringContainsString('Cobrança da casa', $excluidas);
        $this->assertStringContainsString('fora da carteira', $excluidas);

        $this->actingAs($funcionaria)->patch(route('financial.restore', $minha->id))->assertForbidden();
        $this->actingAs($admin)->patch(route('financial.restore', $minha->id))->assertSessionHasNoErrors()
            ->assertRedirect(route('financial.show', $minha));
        $this->assertNull($this->recarregando($minha)->deleted_at);
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /** @param  array<string, mixed>  $extras  */
    private function novaConta(Company $empresa, array $extras = []): FinancialRecord
    {
        TenantContext::set($empresa->id);
        $conta = FinancialRecord::query()->create(array_merge([
            'type' => FinancialRecord::REVENUE,
            'category' => 'visita_tecnica',
            'description' => 'Cobrança de teste',
            'amount' => 100,
            'due_date' => '2026-10-20',
            'status' => FinancialRecord::PENDING,
            'occurred_at' => null,
        ], $extras));
        TenantContext::forget();

        return $conta;
    }

    /** @param  array<string, mixed>  $campos  */
    private function abrindoConta(User $quem, array $campos = []): TestResponse
    {
        return $this->actingAs($quem)->post(route('financial.store'), array_merge([
            'tipo' => FinancialRecord::REVENUE,
            'categoria' => 'visita_tecnica',
            'descricao' => 'Cobrança padrão do teste',
            'valor' => '200.00',
            'vencimento' => '2026-10-20',
        ], $campos));
    }

    /** @param  array<string, mixed>  $campos  */
    private function recebendo(User $quem, FinancialRecord $conta, array $campos = []): TestResponse
    {
        return $this->actingAs($quem)->post(route('financial.payments.store', $conta), array_merge([
            'valor' => '100.00',
            'metodo' => 'pix',
            'data' => '2026-10-05',
        ], $campos));
    }

    /**
     * A PUT inteira: o formulário de edição manda todos os campos, e um teste que
     * mandasse só o que mudaria estaria provando outra coisa.
     *
     * @param  array<string, mixed>  $mudanças
     */
    private function editando(User $quem, FinancialRecord $conta, array $mudanças = []): TestResponse
    {
        $estaConta = $this->recarregando($conta);

        return $this->actingAs($quem)->put(route('financial.update', $conta), array_merge([
            'tipo' => $estaConta->type,
            'categoria' => $estaConta->category,
            'descricao' => $estaConta->description,
            'valor' => number_format((float) $estaConta->amount, 2, '.', ''),
            'vencimento' => $estaConta->due_date->toDateString(),
            'cliente_id' => $estaConta->client_id,
            'ordem_id' => $estaConta->service_order_id,
        ], $mudanças));
    }

    /** @param  array<string, mixed>  $campos  */
    private function cobrando(User $quem, ServiceOrder $ordem, array $campos): TestResponse
    {
        return $this->actingAs($quem)->post(route('orders.charge', $ordem), $campos);
    }

    private function recarregando(FinancialRecord $conta): FinancialRecord
    {
        return FinancialRecord::query()->withoutGlobalScopes()->findOrFail($conta->id);
    }

    /** @return Collection<int, Payment> */
    private function pagamentosDa(FinancialRecord $conta): Collection
    {
        return Payment::query()->withoutGlobalScopes()->where('financial_record_id', $conta->id)->orderBy('id')->get();
    }

    private function contaPorDescricao(string $descricao): FinancialRecord
    {
        return FinancialRecord::query()->withoutGlobalScopes()->where('description', $descricao)->firstOrFail();
    }

    private function contandoContas(string $descricao): int
    {
        return FinancialRecord::query()->withoutGlobalScopes()->where('description', 'like', $descricao)->count();
    }

    /**
     * Linha de ordem nasce do catálogo: o CHECK do banco exige serviço ou produto, e
     * uma linha sem origem seria mentira — a cobrança soma exatamente estas linhas.
     *
     * @param  array<int, array{0: string, 1: string, 2: float, 3: float}>  $linhas  [servico|produto, nome, quantidade, unitário]
     */
    private function linhasDaOrdem(ServiceOrder $ordem, array $linhas): void
    {
        foreach ($linhas as [$tipo, $nome, $quantidade, $unitario]) {
            $ehServico = $tipo === 'servico';

            $origem = $ehServico
                ? $this->servico($ordem->company, $nome, ['price' => $unitario])
                : $this->produto($ordem->company, $nome, ['price' => $unitario]);

            TenantContext::set($ordem->company_id);
            $ordem->items()->create([
                $ehServico ? 'service_id' : 'product_id' => $origem->id,
                'description' => $nome,
                'quantity' => $quantidade,
                'unit_price' => $unitario,
            ]);
            TenantContext::forget();
        }
    }

    /** @param  array<string, mixed>  $extras  */
    private function servico(Company $empresa, string $nome, array $extras = []): Service
    {
        TenantContext::set($empresa->id);
        $servico = Service::query()->create($extras + ['name' => $nome, 'price' => 100, 'status' => 'active']);
        TenantContext::forget();

        return $servico;
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

    private function ultimaTrilha(string $acao): string
    {
        $trilha = AuditLog::query()->withoutGlobalScopes()
            ->where('action', $acao)
            ->latest('id')
            ->first();

        $this->assertNotNull($trilha, "Nenhum rastro de [{$acao}] na auditoria.");

        return strval($trilha->description);
    }

    /**
     * A mensagem flash lida antes do próximo pedido: o Laravel só aposenta o flash no
     * fim da requisição seguinte, então a leitura tem de ser na ordem dos fatos.
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

    private function semEspaços(string $html): string
    {
        return preg_replace('/\s+/', ' ', $html);
    }
}
