<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\ServiceOrderStatusHistory;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\TechnicianStock;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Services\Agenda\AgendamentoDeCompromisso;
use App\Services\Finance\LancamentoDeConta;
use App\Services\Finance\RegistroDePagamento;
use App\Services\Orders\FluxoDeOrdem;
use App\Services\Orders\RegistroDePresenca;
use App\Services\Recusa;
use App\Services\Stock\LancamentoDeEstoque;
use App\Services\Tickets\ConversaDeChamado;
use App\Services\Tickets\FluxoDeChamado;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * A camada de serviço é a escrita; a tela é a fachada.
 *
 * O desenho pedido — interface → serviço → backend → dados — só vale se o degrau do
 * meio for o dono da caneta. Estes testes chamam o serviço sem passar por HTTP, e
 * depois olham para o controller: quem abre transação, troca estado e grava carimbo
 * é o serviço, e a apresentação não escreve nada. É a trava contra o atalho de
 * sempre — no dia em que alguém pôr `DB::transaction` de volta num controller, ou
 * mover a regra de recusa para o HTML, este arquivo para de passar.
 */
class CamadaDeServicosTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_o_servico_barra_o_salto_e_nao_deixa_rastro_na_ficha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ordem = $this->ordem($empresa, 'OS-2026-0401', 'Instalação que ainda não começou');

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            app(FluxoDeOrdem::class)->mudarStatus($ordem, $admin, 'completed');
            $this->fail('O serviço deixou a ordem pular de aberta para concluída.');
        } catch (Recusa $recusa) {
            $this->assertSame('erro', $recusa->tom());
            $this->assertStringContainsString('não pode ir para', $recusa->getMessage());
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('open', $ordem->fresh()->status);
        $this->assertSame(0, ServiceOrderStatusHistory::query()->where('to_status', 'completed')->count());
    }

    public function test_o_passo_que_pediu_aprovacao_volta_como_recusa_e_nao_como_500(): void
    {
        [$empresa, $campo] = $this->empresaComTecnico();
        $ordem = $this->ordem($empresa, 'OS-2026-0402', 'Ordem de rascunho do campo', ['status' => 'draft']);

        $this->actingAs($campo);
        TenantContext::set($empresa->id);

        try {
            app(FluxoDeOrdem::class)->mudarStatus($ordem, $campo, 'open');
            $this->fail('Quem não tem a aprovação tirou um rascunho do papel.');
        } catch (Recusa $recusa) {
            $this->assertStringContainsString('permissão de aprovação', $recusa->getMessage());
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('draft', $ordem->fresh()->status);
    }

    public function test_a_chegada_do_servico_abre_a_execucao_pela_passagem_e_pelo_carimbo(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $ordem = $this->ordem($empresa, 'OS-2026-0403', 'Reparo no balcão frio', [
            'technician_id' => $ana->id,
            'latitude' => -23.55052,
            'longitude' => -46.633308,
        ]);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $visita = app(RegistroDePresenca::class)->chega($ordem, $ana, $admin, [
                'latitude' => -23.5506,
                'longitude' => -46.6333,
                'observacao' => 'Equipamento desligado na tomada.',
            ]);
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('open', $visita->status);
        $this->assertSame('in_progress', $ordem->fresh()->status);
        $this->assertNotNull($visita->checkin_distance);

        $passagem = ServiceOrderStatusHistory::query()
            ->where('service_order_id', $ordem->id)
            ->where('to_status', 'in_progress')
            ->sole();

        $this->assertSame('Chegada registrada em campo pelo check-in.', $passagem->note);
        $this->assertSame($admin->id, $passagem->user_id);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ServiceOrderCheckin',
            'entity_id' => (string) $visita->id,
            'action' => 'chegada em campo',
            'user_id' => $admin->id,
        ]);
    }

    public function test_a_segunda_chegada_e_aviso_de_quem_ja_esta_la_e_nao_duplicata(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $ordem = $this->ordem($empresa, 'OS-2026-0404', 'Manutenção em andamento', ['technician_id' => $ana->id]);

        $servico = app(RegistroDePresenca::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $servico->chega($ordem, $ana, $admin, []);

            try {
                $servico->chega($ordem, $ana, $admin, []);
                $this->fail('A segunda chegada passou como se fosse a primeira.');
            } catch (Recusa $recusa) {
                $this->assertSame('aviso', $recusa->tom());
                $this->assertStringContainsString('já está em campo', $recusa->getMessage());
            }
        } finally {
            TenantContext::forget();
        }

        $this->assertSame(1, ServiceOrderCheckin::query()->count());
    }

    public function test_a_saida_repetida_devolve_aviso_e_preserva_o_primeiro_carimbo(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $ordem = $this->ordem($empresa, 'OS-2026-0405', 'Vitrine que voltou a gelar', ['technician_id' => $ana->id]);

        $servico = app(RegistroDePresenca::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $visita = $servico->chega($ordem, $ana, $admin, []);
            $servico->sai($visita, $ordem, ['observacao' => 'Cliente assinou a ordem.']);
            $saida = $visita->fresh()->checkout_at->toDateTimeString();

            try {
                $servico->sai($visita->fresh(), $ordem->fresh(), ['observacao' => 'Riscando o que já aconteceu']);
                $this->fail('A saída repetida reescreveu o carimbo.');
            } catch (Recusa $recusa) {
                $this->assertSame('aviso', $recusa->tom());
            }
        } finally {
            TenantContext::forget();
        }

        $atualizada = $visita->fresh();

        $this->assertSame($saida, $atualizada->checkout_at->toDateTimeString());
        $this->assertSame('Cliente assinou a ordem.', $atualizada->observation);
        $this->assertSame('closed', $atualizada->status);
    }

    public function test_o_lancamento_de_estoque_escreve_os_dois_saldos_a_trilha_e_a_frase_da_tela(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $produto = $this->produto($empresa, 'Gás R-410a', ['sku' => 'GS-410', 'unit' => 'kg']);

        $estoque = app(LancamentoDeEstoque::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $compra = $estoque->registrar($this->pedido($admin, 'purchase', $produto, 10));
            $carga = $estoque->registrar($this->pedido($admin, 'load', $produto, 4, $ana));
        } finally {
            TenantContext::forget();
        }

        $this->assertSame(6.0, $produto->fresh()->saldoCentralAtual(), 'A carga não baixou o central.');
        $this->assertSame(4.0, (float) $this->carga($ana, $produto)->quantity, 'A carga não entrou na mala.');

        // As duas frases que a escrita devolve: a da tela, com o efeito do gesto, e a
        // da trilha, com o saldo que o lançamento formou.
        $this->assertStringContainsString('Compra de 10,00 kg de Gás R-410a (GS-410)', $compra['resumo']);
        $this->assertStringContainsString('Estoque central: +10,00 kg.', $compra['resumo']);
        $this->assertStringNotContainsString('na carga de', $compra['resumo'], 'Compra não tem mala, e a frase não pode inventar uma.');

        $this->assertStringContainsString('Carga para o técnico de 4,00 kg de Gás R-410a (GS-410)', $carga['resumo']);
        $this->assertStringContainsString('Estoque central: −4,00 kg', $carga['resumo']);
        $this->assertStringContainsString('Entraram 4,00 kg na carga de Ana Beltrão.', $carga['resumo']);
        $this->assertSame(6.0, $carga['central']);
        $this->assertNull($carga['aviso'], 'Com o ponto de reposição em zero não há o que avisar.');

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'StockMovement',
            'entity_id' => (string) $carga['movimento']->id,
            'action' => 'movimentação de estoque',
            'user_id' => $admin->id,
        ]);
    }

    public function test_a_recusa_do_saldo_devolve_o_campo_que_errou_e_nao_deixa_linha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $produto = $this->produto($empresa, 'Correia dentada');
        $ordem = $this->ordem($empresa, 'OS-2026-0501', 'Troca da correia', ['technician_id' => $ana->id]);

        $estoque = app(LancamentoDeEstoque::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $estoque->registrar($this->pedido($admin, 'purchase', $produto, 5));

            try {
                $estoque->registrar($this->pedido($admin, 'load', $produto, 8, $ana));
                $this->fail('O serviço deixou o estoque central ficar negativo.');
            } catch (ValidationException $recusa) {
                $this->assertArrayHasKey('quantidade', $recusa->errors());
                $this->assertStringContainsString('não cabe aí', $recusa->errors()['quantidade'][0]);
            }

            // A mala também não vira crédito: sem carga na ficha, o consumo não passa.
            try {
                $estoque->registrar($this->pedido($admin, 'consume', $produto, 2, $ana, $ordem));
                $this->fail('O consumo passou do que há na mala.');
            } catch (ValidationException $recusa) {
                $this->assertArrayHasKey('tecnico_id', $recusa->errors());
                $this->assertStringContainsString('passa do que há na mala', $recusa->errors()['tecnico_id'][0]);
            }
        } finally {
            TenantContext::forget();
        }

        // A transação desfeita não pode deixar rastro: nem linha, nem saldo na mala.
        $this->assertSame(1, StockMovement::query()->count(), 'A recusa deixou linha escrita.');
        $this->assertSame(5.0, $produto->fresh()->saldoCentralAtual());
        $this->assertSame(0, TechnicianStock::query()->count());
    }

    public function test_a_falta_no_central_sai_da_escrita_com_aviso_e_sino(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $produto = $this->produto($empresa, 'Filtro de óleo', ['reorder_point' => 4]);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            app(LancamentoDeEstoque::class)->registrar($this->pedido($admin, 'purchase', $produto, 10));
            $carga = app(LancamentoDeEstoque::class)->registrar($this->pedido($admin, 'load', $produto, 8, $ana));
        } finally {
            TenantContext::forget();
        }

        $this->assertStringContainsString('abaixo do ponto de reposição', strval($carga['aviso']));
        $this->assertStringContainsString('2,00 contra o mínimo de 4,00 un', strval($carga['aviso']));

        // O sino é da escrita, não da tela: quem responde pelo inventário é chamado no
        // momento em que o saldo cruzou a linha, sem depender de alguém abrir a listagem.
        $this->assertDatabaseHas('notifications', [
            'company_id' => $empresa->id,
            'user_id' => $admin->id,
            'type' => 'estoque.baixo',
        ]);
    }

    public function test_o_chamado_nasce_pelo_servico_com_protocolo_passagem_e_sino(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        $padaria = $this->cliente($empresa, 'Padaria do Rodrigo');

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $chamado = app(FluxoDeChamado::class)->abrir($admin, [
                'client_id' => $padaria->id,
                'subject' => 'Vitrine parada no fundo da loja',
                'description' => '<p>Pressão caindo.</p><script>alert(1)</script>',
                'category' => 'refrigeracao',
                'priority' => 'high',
            ]);
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('open', $chamado->status);
        $this->assertMatchesRegularExpression('/^CH-\d{4}-0001$/', $chamado->protocol);
        $this->assertNotNull($chamado->opened_at);

        // O texto rico é sanitizado na escrita, não na tela: o rótulo do editor pode
        // chegar, o script que vem colado nele não.
        $this->assertStringContainsString('<p>Pressão caindo.</p>', $chamado->description);
        $this->assertStringNotContainsString('<script>', $chamado->description);

        $passagem = TicketStatusHistory::query()->where('ticket_id', $chamado->id)->sole();
        $this->assertNull($passagem->from_status);
        $this->assertSame('open', $passagem->to_status);
        $this->assertSame($admin->id, $passagem->user_id);

        // Quem atende é chamado no nascimento: o sino não espera alguém abrir a tela.
        $this->assertDatabaseHas('notifications', [
            'company_id' => $empresa->id,
            'user_id' => $campo->id,
            'type' => 'chamdo.aberto',
        ]);
    }

    public function test_a_conta_de_cliente_abre_na_propria_carteira_mesmo_apontando_outra(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $minha = $this->cliente($empresa, 'Padaria do Rodrigo');
        $alheia = $this->cliente($empresa, 'Oficina do Sr. Lima');
        $conta = $this->makeUser('client', $empresa, 'cliente@test.local');

        TenantContext::set($empresa->id);
        $conta->update(['client_id' => $minha->id]);
        TenantContext::forget();

        $this->actingAs($conta);
        TenantContext::set($empresa->id);

        try {
            $chamado = app(FluxoDeChamado::class)->abrir($conta, [
                'client_id' => $alheia->id,
                'subject' => 'Nota fiscal do serviço de ontem',
                'category' => 'refrigeracao',
                'priority' => 'normal',
            ]);
        } finally {
            TenantContext::forget();
        }

        // A carteira é da conta, não do formulário: quem escreve a regra é o serviço,
        // então nenhuma fachada esquece de aplicar o próprio dono.
        $this->assertSame($minha->id, $chamado->client_id);
        $this->assertNotSame($alheia->id, $chamado->client_id);
        $this->assertSame($conta->id, TicketStatusHistory::query()->where('ticket_id', $chamado->id)->sole()->user_id);
    }

    public function test_a_travessia_sem_fluxo_e_o_passo_sem_permissao_voltam_como_recusa(): void
    {
        [$empresa, $campo] = $this->empresaComTecnico();
        $conta = $this->makeUser('client', $empresa, 'leitura@test.local');
        $chamado = $this->chamado($empresa);

        $fluxo = app(FluxoDeChamado::class);

        $this->actingAs($campo);
        TenantContext::set($empresa->id);

        try {
            try {
                $fluxo->mudarStatus($chamado, $campo, 'resolved');
                $this->fail('O serviço deixou o chamado pular de aberto para resolvido.');
            } catch (Recusa $recusa) {
                $this->assertSame('erro', $recusa->tom());
                $this->assertStringContainsString('não pode ir para', $recusa->getMessage());
            }

            // Conduzir o dia a dia é do técnico, e o encerramento não: a régua que
            // oferece o botão é a mesma que barra o passo.
            $this->assertSame(['in_progress', 'waiting'], array_keys($fluxo->proximosEstados($chamado->fresh(), $campo)));
            $this->assertSame([], array_keys($fluxo->proximosEstados($chamado->fresh(), $conta)));

            try {
                $fluxo->mudarStatus($chamado, $conta, 'in_progress');
                $this->fail('Quem só lê conduziu o estado do chamado.');
            } catch (Recusa $recusa) {
                $this->assertStringContainsString('permissão de atendimento', $recusa->getMessage());
            }

            $origem = $fluxo->mudarStatus($chamado, $campo, 'in_progress', 'Visita feita, aguardando a peça.');
            $this->assertSame('open', $origem);

            try {
                $fluxo->mudarStatus($chamado->fresh(), $campo, 'resolved', 'Pronto, pode conferir.');
                $this->fail('Sem a permissão de encerramento, o chamado foi resolvido.');
            } catch (Recusa $recusa) {
                $this->assertStringContainsString('permissão de encerramento', $recusa->getMessage());
            }
        } finally {
            TenantContext::forget();
        }

        $atual = $chamado->fresh();

        $this->assertSame('in_progress', $atual->status);
        $this->assertNull($atual->resolved_at, 'A travessia recusada deixou carimbo de resolução.');
        $this->assertSame(2, TicketStatusHistory::query()->where('ticket_id', $chamado->id)->count());
    }

    public function test_a_nota_interna_e_a_conversa_encerrada_sao_decisoes_do_servico(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        $chamado = $this->chamado($empresa);

        $conversa = app(ConversaDeChamado::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $interna = $conversa->responder($chamado, $admin, '<p>Cotação do fornecedor ainda não assinada.</p>', true);
            $this->assertTrue($interna->is_internal);

            // O técnico não responde pelo escritório: o pedido de interna é descartado.
            $falsa = $conversa->responder($chamado->fresh(), $campo, '<p>Peça a caminho.</p><script>alert(1)</script>', true);
            $this->assertFalse($falsa->is_internal, 'Sem `tickets.update` a marca de interna não se aplica.');
            $this->assertStringNotContainsString('<script>', $falsa->body);

            $this->assertSame('Nota interna registrada no chamado.', $conversa->resumo($interna));
            $this->assertSame('Resposta enviada no chamado.', $conversa->resumo($falsa));

            $chamado->mudarStatus('closed', $admin);

            try {
                $conversa->responder($chamado->fresh(), $admin, '<p>Algo depois do fim</p>', false);
                $this->fail('A conversa de um chamado fechado recebeu resposta.');
            } catch (Recusa $recusa) {
                $this->assertSame('erro', $recusa->tom());
                $this->assertStringContainsString('outro protocolo', $recusa->getMessage());
            }
        } finally {
            TenantContext::forget();
        }

        $this->assertSame(2, TicketComment::query()->where('ticket_id', $chamado->id)->count());
    }

    public function test_a_fachada_apresenta_e_o_servico_escreve(): void
    {
        $ordens = File::get(base_path('app/Http/Controllers/Orders/OrderController.php'));
        $chegadas = File::get(base_path('app/Http/Controllers/Orders/CheckinController.php'));
        $estoque = File::get(base_path('app/Http/Controllers/Stock/MovementController.php'));
        $chamados = File::get(base_path('app/Http/Controllers/Tickets/TicketController.php'));
        $notas = File::get(base_path('app/Http/Controllers/Tickets/TicketCommentController.php'));
        $contas = File::get(base_path('app/Http/Controllers/Finance/FinancialRecordController.php'));
        $dinheiro = File::get(base_path('app/Http/Controllers/Finance/PaymentController.php'));
        $escala = File::get(base_path('app/Http/Controllers/Agenda/AgendaController.php'));

        // Nenhum dos dois abre transação, cria linha, grava passagem de estado,
        // toca sino nem escreve na trilha: quem faz isso é o serviço, e a tela só
        // pede e traduz a resposta. Chamar `$this->fluxo->mudarStatus()` daqui é o
        // pedido; escrever o estado é de `FluxoDeOrdem`.
        foreach (['DB::transaction', 'ServiceOrderStatusHistory', 'Auditor::gravar', 'Notifier::', 'ServiceOrder::create', 'ServiceOrderCheckin::query()->create'] as $proibida) {
            $this->assertStringNotContainsString($proibida, $ordens, "A escrita {$proibida} voltou para o controller de ordens.");
            $this->assertStringNotContainsString($proibida, $chegadas, "A escrita {$proibida} voltou para o controller de chegadas.");
        }

        // O mesmo corte no estoque: quem abre a transação, trava o produto, escreve a
        // linha, carimba a auditoria e toca o sino de reposição é `LancamentoDeEstoque`.
        foreach (['DB::transaction', 'Auditor::gravar', 'Notifier::', 'StockMovement::query()->create', 'TechnicianStock::travar', 'lockForUpdate'] as $proibida) {
            $this->assertStringNotContainsString($proibida, $estoque, "A escrita {$proibida} voltou para o controller de estoque.");
        }

        // O mesmo corte no chamado: abrir protocolo, escrever a passagem de nascimento,
        // tocar sino, sanitizar o corpo da nota e conduzir estado são do serviço. Pedir
        // a travessia com `$this->fluxo->mudarStatus()` é apresentação; gravá-la é de
        // `FluxoDeChamado`.
        foreach (['DB::transaction', 'TicketStatusHistory', 'Notifier::', 'Ticket::create', 'TextoSeguro::sanitizar', '$chamado->mudarStatus('] as $proibida) {
            $this->assertStringNotContainsString($proibida, $chamados, "A escrita {$proibida} voltou para o controller de chamados.");
        }

        foreach (['DB::transaction', 'Notifier::', 'comments()->create', "'is_internal'", 'estaEncerrado'] as $proibida) {
            $this->assertStringNotContainsString($proibida, $notas, "A escrita {$proibida} voltou para o controller de notas.");
        }

        // O mesmo corte no financeiro. Aqui a lista é dupla porque são duas fachadas:
        // a conta (`LancamentoDeConta`) e o dinheiro (`RegistroDePagamento`). Travar a
        // linha, somar no banco, derivar o estado, escrever a linha e gravar a trilha
        // estão fora das duas — e `recalcularEstado()` fora das duas é o que impede o
        // select de "Pago" de voltar para a tela.
        foreach (['DB::transaction', 'Auditor::gravar', 'FinancialRecord::query()->create', 'Payment::query()->create', 'lockForUpdate', 'recalcularEstado', '$registro->update(', '$registro->delete()', 'ValidationException'] as $proibida) {
            $this->assertStringNotContainsString($proibida, $contas, "A escrita {$proibida} voltou para o controller de contas.");
        }

        foreach (['DB::transaction', 'Auditor::gravar', 'Payment::query()->create', 'FinancialRecord::query()->create', 'lockForUpdate', 'recalcularEstado', 'ValidationException', 'Formatters::money'] as $proibida) {
            $this->assertStringNotContainsString($proibida, $dinheiro, "A escrita {$proibida} voltou para o controller de pagamentos.");
        }

        // O mesmo corte na agenda: criar a linha, mover a janela, conduzir o estado e
        // conferir a máquina de estados estão em `AgendamentoDeCompromisso`. O que sobra
        // na tela é o que só a tela sabe — o alcance de leitura, a regra de campo e o
        // idioma da recusa: 302 com flash para quem preencheu o formulário, 422 com
        // `mensagem` para quem arrastou o bloco no quadro.
        foreach (['Appointment::create', 'Appointment::query()->create', '$compromisso->update(', '$compromisso->delete()', 'Appointment::FLUXO', 'podeMudarPara(', 'DB::transaction', 'Auditor::gravar'] as $proibida) {
            $this->assertStringNotContainsString($proibida, $escala, "A escrita {$proibida} voltou para o controller de agenda.");
        }

        $this->assertStringContainsString('FluxoDeOrdem', $ordens);
        $this->assertStringContainsString('RegistroDePresenca', $chegadas);
        $this->assertStringContainsString('LancamentoDeEstoque', $estoque);
        $this->assertStringContainsString('FluxoDeChamado', $chamados);
        $this->assertStringContainsString('ConversaDeChamado', $notas);
        $this->assertStringContainsString('LancamentoDeConta', $contas);
        $this->assertStringContainsString('RegistroDePagamento', $dinheiro);
        $this->assertStringContainsString('AgendamentoDeCompromisso', $escala);
    }

    public function test_a_conta_nasce_do_servico_em_aberto_e_o_que_chega_de_fora_nao_entra(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $contas = app(LancamentoDeConta::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $receita = $contas->criar($this->formularioDeConta([
                'descricao' => 'Contrato de manutenção de outubro',
                'valor' => '850.00',
                'vencimento' => '2026-11-10',
                'cliente_id' => $padaria->id,
                'observacao' => 'Cobre a câmara fria e as vitrines da frente.',
                // Ninguém digita estado nem data do fato: o serviço nem olha para aqui.
                'status' => FinancialRecord::PAID,
                'occurred_at' => '2026-10-01',
            ]));

            $despesa = $contas->criar($this->formularioDeConta([
                'tipo' => FinancialRecord::EXPENSE,
                'categoria' => 'deslocamento',
                'descricao' => 'Combustível da semana',
                'valor' => 120,
                'vencimento' => '2026-10-20',
                'cliente_id' => $padaria->id,
            ]));
        } finally {
            TenantContext::forget();
        }

        $this->assertSame(FinancialRecord::PENDING, $receita->status, 'O estado nasce em aberto: conta quitada sem dinheiro é número inventado.');
        $this->assertNull($receita->occurred_at, 'Sem pagamento não há data do fato: nada aconteceu ainda.');
        $this->assertSame($empresa->id, (int) $receita->company_id);
        $this->assertSame(850.00, (float) $receita->amount);
        $this->assertSame($padaria->id, (int) $receita->client_id);
        $this->assertSame('Contrato de manutenção de outubro', $receita->description);
        $this->assertSame('contrato_mensal', $receita->category);

        // Despesa tem fornecedor, e fornecedor não está no schema desta casa: a
        // carteira que chegar junto é descartada, não aplicada.
        $this->assertSame(FinancialRecord::EXPENSE, $despesa->type);
        $this->assertNull($despesa->client_id);
        $this->assertSame(120.00, (float) $despesa->amount);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'FinancialRecord',
            'entity_id' => (string) $receita->id,
            'action' => 'lançamento financeiro',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'FinancialRecord',
            'entity_id' => (string) $receita->id,
            'action' => 'criado',
        ]);
    }

    public function test_a_ordem_de_outro_cliente_e_a_conta_meio_paga_que_muda_de_valor_sao_recusadas_no_servico(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $vinha = $this->cliente($empresa, 'Vinha d’Uva');
        $ordem = $this->ordem($empresa, 'OS-2026-0501', 'Câmara fria da Padaria', ['client_id' => $padaria->id]);

        $contas = app(LancamentoDeConta::class);
        $dinheiro = app(RegistroDePagamento::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            // A validação de campo confere cada um sozinho e não enxerga o par: é o
            // serviço que barra a cobrança da Vinha contra a ordem da Padaria.
            try {
                $contas->criar($this->formularioDeConta([
                    'descricao' => 'Cobrança que trocou de cliente',
                    'cliente_id' => $vinha->id,
                    'ordem_id' => $ordem->id,
                ]));
                $this->fail('A conta de um cliente ficou presa à ordem de outro.');
            } catch (ValidationException $falha) {
                $this->assertArrayHasKey('ordem_id', $falha->errors());
                $this->assertStringContainsString('pertence a', strval($falha->errors()['ordem_id'][0]));
            }

            $conta = $contas->criar($this->formularioDeConta([
                'descricao' => 'Mensalidade da vitrine da Padaria',
                'valor' => '500.00',
                'cliente_id' => $padaria->id,
            ]));

            $dinheiro->registrar($conta, $admin, ['valor' => '200.00', 'metodo' => 'pix', 'data' => '2026-10-02']);

            try {
                $contas->alterar($conta->fresh(), $this->formularioDeConta([
                    'descricao' => 'Mensalidade da vitrine da Padaria',
                    'valor' => '900.00',
                    'cliente_id' => $padaria->id,
                ]));
                $this->fail('O valor de uma conta meio paga foi mexido.');
            } catch (ValidationException $falha) {
                $this->assertArrayHasKey('valor', $falha->errors());
                $this->assertStringContainsString('mover a régua', strval($falha->errors()['valor'][0]));
            }

            try {
                $contas->alterar($conta->fresh(), $this->formularioDeConta([
                    'tipo' => FinancialRecord::EXPENSE,
                    'categoria' => 'estoque',
                    'descricao' => 'Mensalidade que virou compra',
                    // O valor é o da conta: o que muda aqui é o lado da moeda, e a
                    // régua tem de acusar o campo `tipo`, não o número que ficou igual.
                    'valor' => '500.00',
                ]));
                $this->fail('Uma receita meio recebida virou despesa.');
            } catch (ValidationException $falha) {
                $this->assertArrayHasKey('tipo', $falha->errors());
            }

            // O que não mexe em valor nem tipo passa: acertar vencimento e descrição é
            // do dia a dia, e o estado continua sendo o que a soma diz.
            $ajustada = $contas->alterar($conta->fresh(), $this->formularioDeConta([
                'descricao' => 'Mensalidade da vitrine — acertada na tela',
                'valor' => '500.00',
                'vencimento' => '2026-12-05',
                'cliente_id' => $padaria->id,
            ]));

            $this->assertSame('Mensalidade da vitrine — acertada na tela', $ajustada->description);
            $this->assertSame(FinancialRecord::PARTIAL, $ajustada->status, 'A alteração não tocou no estado derivado.');
            $this->assertSame(200.00, (float) $ajustada->valorPago());
        } finally {
            TenantContext::forget();
        }

        $this->assertSame(0, FinancialRecord::query()->where('description', 'Cobrança que trocou de cliente')->count());
    }

    public function test_o_dinheiro_registrado_pelo_servico_movimento_estado_data_do_fato_e_trilha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $contas = app(LancamentoDeConta::class);
        $dinheiro = app(RegistroDePagamento::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $conta = $contas->criar($this->formularioDeConta([
                'descricao' => 'Mensalidade da vitrine',
                'valor' => '500.00',
                'vencimento' => '2026-10-20',
                'cliente_id' => $padaria->id,
            ]));

            $primeiro = $dinheiro->registrar($conta, $admin, [
                'valor' => '200.00',
                'metodo' => 'pix',
                'data' => '2026-10-02',
                'referencia' => 'Pix 02/10 da recepção',
            ]);

            $this->assertSame(FinancialRecord::PARTIAL, $primeiro['estado']);
            $this->assertSame(200.00, (float) $primeiro['conta']->valorPago());
            $this->assertStringContainsString('faltam R$ 300,00', $primeiro['resumo']);
            $this->assertSame('Pix 02/10 da recepção', $primeiro['pagamento']->reference);

            // A data do fato é a do último pagamento registrado, e não a de hoje.
            $this->assertSame('2026-10-02', $conta->fresh()->occurred_at->toDateString());

            // Acima do saldo não entra: a resposta devolve quanto falta.
            try {
                $dinheiro->registrar($conta->fresh(), $admin, ['valor' => '900.00', 'metodo' => 'pix', 'data' => '2026-10-03']);
                $this->fail('A conta aceitou R$ 900 quando faltavam R$ 300.');
            } catch (ValidationException $falha) {
                $this->assertStringContainsString('Faltam R$ 300,00', strval($falha->errors()['valor'][0]));
            }

            $ultimo = $dinheiro->registrar($conta->fresh(), $admin, [
                'valor' => '300.00',
                'metodo' => 'credit_card',
                'data' => '2026-10-04',
            ]);

            $this->assertSame(FinancialRecord::PAID, $ultimo['estado']);
            $this->assertStringContainsString('recebido — R$ 500,00 de R$ 500,00', $ultimo['resumo']);
            $this->assertSame('2026-10-04', $conta->fresh()->occurred_at->toDateString(), 'O último pagamento é o que dá a data do fato.');

            try {
                $dinheiro->registrar($conta->fresh(), $admin, ['valor' => '10.00', 'metodo' => 'cash', 'data' => '2026-10-05']);
                $this->fail('Conta quitada recebeu pagamento.');
            } catch (ValidationException $falha) {
                $this->assertStringContainsString('já está quitada', strval($falha->errors()['valor'][0]));
            }

            // Estorno devolve o estado que a soma forma sem a linha desfeita.
            $estorno = $dinheiro->estornar($conta->fresh(), $ultimo['pagamento'], $admin);

            $this->assertSame(FinancialRecord::PARTIAL, $estorno['estado']);
            $this->assertStringContainsString('estornado', $estorno['resumo']);
            $this->assertSame(1, Payment::query()->where('financial_record_id', $conta->id)->count());
            $this->assertSame(200.00, (float) $conta->fresh()->valorPago());

            // Cancelar conta com dinheiro dentro é recusado no campo do motivo: o
            // cancelamento apagaria a expectativa de caixa, não o que mudou de mão.
            try {
                $contas->cancelar($conta->fresh(), 'Não dava para cancelar com entrada.');
                $this->fail('Uma conta com pagamento foi cancelada.');
            } catch (ValidationException $falha) {
                $this->assertArrayHasKey('motivo', $falha->errors());
                $this->assertStringContainsString('Estorne o pagamento antes', strval($falha->errors()['motivo'][0]));
            }

            $this->assertDatabaseHas('audit_logs', [
                'entity_type' => 'FinancialRecord',
                'entity_id' => (string) $conta->id,
                'action' => 'pagamento de conta',
                'user_id' => $admin->id,
            ]);
            $this->assertDatabaseHas('audit_logs', [
                'entity_type' => 'FinancialRecord',
                'entity_id' => (string) $conta->id,
                'action' => 'estorno de pagamento',
            ]);
        } finally {
            TenantContext::forget();
        }
    }

    public function test_cancelar_reabrir_e_apagar_andam_pelo_servico_com_o_motivo_carimbado_na_nota(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $contas = app(LancamentoDeConta::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $conta = $contas->criar($this->formularioDeConta(['descricao' => 'Conta que foi cancelada', 'valor' => '200.00']));

            try {
                $contas->reabrir($conta);
                $this->fail('Reabriu conta que nunca foi cancelada.');
            } catch (Recusa $recusa) {
                $this->assertSame('erro', $recusa->tom());
                $this->assertStringContainsString('Só se reabre', $recusa->getMessage());
            }

            $contas->cancelar($conta, 'Emitida com a categoria errada.');

            $cancelada = $conta->fresh();
            $this->assertSame(FinancialRecord::CANCELED, $cancelada->status);
            $this->assertNull($cancelada->occurred_at);
            $this->assertStringContainsString('] Cancelamento: Emitida com a categoria errada.', strval($cancelada->notes));

            try {
                $contas->cancelar($cancelada, 'De novo, sem necessidade.');
                $this->fail('A conta já cancelada foi cancelada outra vez.');
            } catch (Recusa $recusa) {
                $this->assertStringContainsString('já está cancelada', $recusa->getMessage());
            }

            // Pagamento em conta cancelada não aparece em carteira nenhuma.
            try {
                app(RegistroDePagamento::class)->registrar($cancelada, $admin, ['valor' => '50.00', 'metodo' => 'pix', 'data' => '2026-10-06']);
                $this->fail('Dinheiro entrou em conta cancelada.');
            } catch (ValidationException $falha) {
                $this->assertStringContainsString('está cancelada', strval($falha->errors()['valor'][0]));
            }

            $estado = $contas->reabrir($cancelada);
            $this->assertSame(FinancialRecord::PENDING, $estado);
            $this->assertStringContainsString('] Reabertura: Conta devolvida à carteira.', strval($cancelada->fresh()->notes));

            // Apagar é o verbo do cadastro que nunca existiu, e a exclusão suave deixa
            // restaurar com o rastro dos dois atos.
            $errada = $contas->criar($this->formularioDeConta(['descricao' => 'Cadastro repetido por engano']));
            $contas->apagar($errada);
            $this->assertSoftDeleted('financial_records', ['id' => $errada->id]);
            $this->assertNull($contas->restaurar($errada->id)->fresh()->deleted_at);

            $this->assertDatabaseHas('audit_logs', ['action' => 'cancelamento de conta', 'entity_id' => (string) $conta->id]);
            $this->assertDatabaseHas('audit_logs', ['action' => 'reabertura de conta', 'entity_id' => (string) $conta->id]);
            $this->assertDatabaseHas('audit_logs', ['action' => 'excluido suavemente', 'entity_id' => (string) $errada->id]);
            $this->assertDatabaseHas('audit_logs', ['action' => 'restaurado', 'entity_id' => (string) $errada->id]);
        } finally {
            TenantContext::forget();
        }
    }

    public function test_a_cobranca_emitida_pelo_servico_soma_as_linhas_da_ordem_e_recusa_segunda_via(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $fora = $this->cliente($empresa, 'Cliente de fora');
        $contas = app(LancamentoDeConta::class);

        $ordem = $this->ordem($empresa, 'OS-2026-0502', 'Câmara fria da Padaria', [
            'status' => 'completed',
            'client_id' => $padaria->id,
            'discount' => 40,
        ]);
        $this->linhasDaOrdem($ordem, [
            ['servico', 'Manutenção da câmara fria', 2, 700],
            ['produto', 'Válvula expansionista', 1, 120],
        ]);

        // As outras três ordens são o lado de fora do fluxo: inacabada, sem linha e
        // desfeita. Todas nascem antes do contexto, porque cada helper de fixture abre
        // e fecha o próprio tenant.
        $aberta = $this->ordem($empresa, 'OS-2026-0503', 'Coifa em aberto', ['client_id' => $padaria->id]);
        $this->linhasDaOrdem($aberta, [['servico', 'Limpeza da coifa', 1, 200]]);
        $semLinhas = $this->ordem($empresa, 'OS-2026-0504', 'Visita sem registro', ['status' => 'completed', 'client_id' => $padaria->id]);
        $desfeita = $this->ordem($empresa, 'OS-2026-0505', 'Instalação desfeita', ['status' => 'canceled', 'client_id' => $padaria->id]);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $cobranca = $contas->emitirCobranca($ordem, [
                'vencimento' => '2026-11-05',
                'categoria' => 'mao_de_obra_e_pecas',
                // O request não tem valor nem cliente: o que chegar aqui é ignorado.
                'valor' => '900.00',
                'cliente_id' => $fora->id,
                'observacao' => 'Boleto enviado por e-mail.',
            ]);

            $this->assertSame(1480.00, (float) $cobranca->amount, 'Duas linhas de 700 mais 120, menos os 40 de desconto da ordem.');
            $this->assertSame($padaria->id, (int) $cobranca->client_id);
            $this->assertSame($ordem->id, (int) $cobranca->service_order_id);
            $this->assertSame(FinancialRecord::REVENUE, $cobranca->type);
            $this->assertSame(FinancialRecord::PENDING, $cobranca->status);
            $this->assertSame('Cobrança da OS-2026-0502 — Câmara fria da Padaria', $cobranca->description);
            $this->assertStringContainsString('Boleto enviado', strval($cobranca->notes));

            try {
                $contas->emitirCobranca($ordem, ['vencimento' => '2026-12-05', 'categoria' => 'visita_tecnica']);
                $this->fail('A segunda cobrança da mesma OS passou.');
            } catch (Recusa $recusa) {
                $this->assertStringContainsString('já tem a cobrança', $recusa->getMessage());
            }

            // Cancelar a cobrança libera emitir outra: cancelamento não é duplicata.
            $contas->cancelar($cobranca, 'Categoria errada na primeira via.');
            $segunda = $contas->emitirCobranca($ordem, ['vencimento' => '2026-12-01', 'categoria' => 'visita_tecnica']);
            $this->assertSame(1480.00, (float) $segunda->amount);
            $this->assertSame('visita_tecnica', $segunda->category);

            // Ordem inacabada, ordem sem linhas e ordem cancelada não fecham conta.
            try {
                $contas->emitirCobranca($aberta, ['vencimento' => '2026-11-05', 'categoria' => 'visita_tecnica']);
                $this->fail('Cobrança saiu de ordem que não terminou.');
            } catch (Recusa $recusa) {
                $this->assertStringContainsString('ainda não terminou', $recusa->getMessage());
            }

            try {
                $contas->emitirCobranca($semLinhas, ['vencimento' => '2026-11-05', 'categoria' => 'visita_tecnica']);
                $this->fail('Nasceu conta de R$ 0,00.');
            } catch (Recusa $recusa) {
                $this->assertStringContainsString('não tem o que cobrar', $recusa->getMessage());
            }

            try {
                $contas->emitirCobranca($desfeita, ['vencimento' => '2026-11-05', 'categoria' => 'visita_tecnica']);
                $this->fail('Ordem cancelada virou cobrança.');
            } catch (Recusa $recusa) {
                $this->assertStringContainsString('foi cancelada', $recusa->getMessage());
            }

            $this->assertSame(0, FinancialRecord::query()->where('description', 'like', 'Cobrança da OS-2026-0504%')->count());
            $this->assertDatabaseHas('audit_logs', ['action' => 'cobrança de ordem', 'entity_id' => (string) $segunda->id]);
        } finally {
            TenantContext::forget();
        }
    }

    public function test_a_janela_nasce_agendada_pelo_servico_e_o_cliente_de_quem_prende_manda(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $ordem = $this->ordem($empresa, 'OS-2026-0701', 'Instalação de vitrine', ['client_id' => $padaria->id]);

        $agenda = app(AgendamentoDeCompromisso::class);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $compromisso = $agenda->agendar([
                'title' => 'Vistoria pós-instalação',
                'type' => 'order',
                'starts_at' => now()->addDay()->startOfDay()->addHours(14),
                'ends_at' => now()->addDay()->startOfDay()->addHours(16),
                'service_order_id' => $ordem->id,
                'all_day' => false,
                // Estado e carteira tentados por fora: a tela não tem esses campos, e o
                // serviço não deixa que entrem pela porta dos fundos.
                'status' => 'completed',
                'company_id' => $outra->id,
            ], $admin);

            $this->assertSame('scheduled', $compromisso->status, 'Janela recém-marcada não nasce fato passado.');
            $this->assertSame($empresa->id, (int) $compromisso->company_id, 'A carteira é do login, nunca do payload.');
            $this->assertSame($padaria->id, (int) $compromisso->client_id, 'Prender à ordem traz o cliente da ordem, não o que a tela desenhou.');
            $this->assertNull($compromisso->technician_id, 'Sem técnico escolhido, a janela fica sem dono explícito.');
            $this->assertSame(120, $compromisso->minutos(), 'A duração é medida da janela gravada, não digitada.');

            // Solta, sem ordem nem chamado presos, o cliente que vem é o da ficha.
            $soltura = $agenda->agendar([
                'title' => 'Reunião de escala da semana',
                'type' => 'custom',
                'client_id' => $padaria->id,
                'starts_at' => now()->addDays(2)->startOfDay()->addHours(8),
                'ends_at' => now()->addDays(2)->startOfDay()->addHours(9),
                'all_day' => false,
            ], $admin);

            $this->assertSame($padaria->id, (int) $soltura->client_id);
            $this->assertSame('scheduled', $soltura->status);
        } finally {
            TenantContext::forget();
        }

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $empresa->id,
            'entity_type' => 'Appointment',
            'entity_id' => (string) $compromisso->id,
            'action' => 'criado',
            'user_id' => $admin->id,
        ]);
    }

    public function test_a_passagem_de_estado_e_a_porta_do_cadastro_sao_reguas_do_servico(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $agenda = app(AgendamentoDeCompromisso::class);

        $este = $this->janela($empresa, ['title' => 'Revisão de soveladeira']);
        $cancelado = $this->janela($empresa, ['title' => 'Visita que não aconteceu', 'status' => 'canceled']);
        $concluido = $this->janela($empresa, ['title' => 'Manutenção concluída — Bica Quente', 'status' => 'completed']);

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        TenantContext::forget();

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            // A régua que desenha o botão da ficha é a mesma que barra o atalho pela URL.
            $this->assertSame(['completed', 'canceled'], array_keys($agenda->proximosEstados($este, $admin)));
            $this->assertSame([], $agenda->proximosEstados($concluido, $admin), 'Concluído é terminal: nenhum passo sai dele.');
            $this->assertSame(['scheduled'], array_keys($agenda->proximosEstados($cancelado, $admin)), 'Remarcar é exatamente o que uma agenda serve para fazer.');

            $passagem = $agenda->mudarStatus($este, $admin, 'completed');
            $this->assertSame(['de' => 'scheduled', 'para' => 'completed'], $passagem, 'O serviço devolve vocabulário cru: rótulo é da apresentação.');
            $this->assertSame('completed', $este->fresh()->status);

            try {
                $agenda->mudarStatus($cancelado, $admin, 'completed');
                $this->fail('O serviço deixou um cancelado virar concluído sem voltar a agendado.');
            } catch (Recusa $recusa) {
                $this->assertSame('O compromisso está “Cancelado” e não pode ir para “Concluído”: o fluxo da agenda é o que vale.', $recusa->getMessage());
            }

            $this->assertSame('canceled', $cancelado->fresh()->status, 'Recusa no serviço não escreve estado.');

            try {
                $agenda->garantirEditavel($concluido);
                $this->fail('O formulário abriu prometendo editar um fato passado.');
            } catch (Recusa $recusa) {
                $this->assertSame('Compromisso concluído é fato passado: a janela dele não se edita mais.', $recusa->getMessage());
            }

            try {
                $agenda->alterar($concluido, [
                    'title' => 'Reescrevendo um fato passado',
                    'type' => 'visit',
                    'starts_at' => $concluido->starts_at,
                    'ends_at' => $concluido->ends_at,
                ], $admin);
                $this->fail('O cadastro de um compromisso concluído foi reescrito.');
            } catch (Recusa $recusa) {
                $this->assertSame('Compromisso concluído é fato passado: a janela dele não se edita mais.', $recusa->getMessage());
            }

            $this->assertSame('Manutenção concluída — Bica Quente', $concluido->fresh()->title);
        } finally {
            TenantContext::forget();
        }

        // Quem lê a escala não a conduz: sem `agenda.update` não há passo oferecido e o
        // POST pela rota do estado volta como recusa, não como escrita silenciosa.
        $esta = $this->janela($empresa, ['title' => 'Visita de vistoria']);

        $this->actingAs($campo);
        $this->assertSame([], $agenda->proximosEstados($esta, $campo));

        TenantContext::set($empresa->id);
        try {
            try {
                $agenda->mudarStatus($esta, $campo, 'completed');
                $this->fail('O técnico conduziu o estado de um compromisso da escala.');
            } catch (Recusa $recusa) {
                $this->assertSame('Esta conta não conduz o estado de um compromisso da agenda.', $recusa->getMessage());
            }
        } finally {
            TenantContext::forget();
        }

        $this->assertSame('scheduled', $esta->fresh()->status);
    }

    public function test_o_arraste_do_servico_move_a_janela_preservando_o_que_a_tela_nao_manda(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $agenda = app(AgendamentoDeCompromisso::class);

        $comHora = $this->janela($empresa, ['title' => 'Coleta de equipamento para bancada']);
        $diaInteiro = $this->janela($empresa, [
            'title' => 'Escala da semana — equipe Centro',
            'all_day' => true,
            'starts_at' => now()->startOfDay()->addDay(),
            'ends_at' => now()->startOfDay()->addDays(3)->addHours(8),
        ]);
        $concluido = $this->janela($empresa, ['title' => 'Manutenção concluída', 'status' => 'completed']);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $novo = now()->startOfDay()->addDays(6)->addHours(15);

            $movida = $agenda->reagendar($comHora, ['inicio' => $novo->format('Y-m-d\TH:i:s')]);
            $this->assertSame($novo->toDateTimeString(), $movida->starts_at->toDateTimeString());
            $this->assertSame($novo->copy()->addHours(2)->toDateTimeString(), $movida->ends_at->toDateTimeString(),
                'Sem fim na requisição, a duração gravada continua mandando: o arraste move, não encolhe.');

            $redonda = $agenda->reagendar($movida, [
                'inicio' => $novo->format('Y-m-d\TH:i:s'),
                'fim' => $novo->copy()->addMinutes(45)->format('Y-m-d\TH:i:s'),
            ]);
            $this->assertSame(45, $redonda->minutos(), 'O resize manda os dois lados da janela.');

            $inicioAntes = $diaInteiro->starts_at->copy();
            $fimAntes = $diaInteiro->ends_at->copy();
            $queda = now()->startOfDay()->addDays(9)->addHours(13);

            $deslocada = $agenda->reagendar($diaInteiro, ['inicio' => $queda->format('Y-m-d\TH:i:s')]);
            $dias = $inicioAntes->copy()->startOfDay()->diffInDays($queda->copy()->startOfDay(), false);

            $this->assertSame($inicioAntes->copy()->addDays((int) $dias)->toDateTimeString(), $deslocada->starts_at->toDateTimeString(),
                'Dia inteiro se move por dias inteiros: a madrugada gravada continua de pé, e não a hora do mouse.');
            $this->assertSame($fimAntes->copy()->addDays((int) $dias)->toDateTimeString(), $deslocada->ends_at->toDateTimeString(),
                'A duração que estava gravada acompanha o deslocamento inteiro.');

            $antesInicio = $redonda->starts_at->toDateTimeString();
            $antesFim = $redonda->ends_at->toDateTimeString();

            try {
                $agenda->reagendar($redonda, [
                    'inicio' => $novo->format('Y-m-d\TH:i:s'),
                    'fim' => $novo->copy()->subHour()->format('Y-m-d\TH:i:s'),
                ]);
                $this->fail('O serviço gravou uma janela que termina antes de começar.');
            } catch (Recusa $recusa) {
                $this->assertSame('A janela terminou antes de começar: arraste de novo.', $recusa->getMessage());
            }

            $this->assertSame($antesInicio, $redonda->fresh()->starts_at->toDateTimeString(), 'Janela torta não escreve nada.');
            $this->assertSame($antesFim, $redonda->fresh()->ends_at->toDateTimeString());

            try {
                $agenda->reagendar($concluido, ['inicio' => now()->addDays(4)->format('Y-m-d\TH:i:s')]);
                $this->fail('O serviço moveu a janela de um compromisso concluído.');
            } catch (Recusa $recusa) {
                $this->assertSame('Compromisso concluído não muda mais de janela.', $recusa->getMessage(),
                    'A frase curta do 422 do quadro não é a mesma do 302 do cadastro, e não deveria ser.');
            }

            $this->assertSame('completed', $concluido->fresh()->status);
        } finally {
            TenantContext::forget();
        }
    }

    public function test_apagar_a_janela_do_servico_deixa_a_ordem_e_a_janela_dela_no_lugar(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $agenda = app(AgendamentoDeCompromisso::class);

        $ordem = $this->ordem($empresa, 'OS-2026-0702', 'Manutenção da câmara fria', [
            'scheduled_starts_at' => now()->startOfDay()->addDays(2)->addHours(14),
            'scheduled_ends_at' => now()->startOfDay()->addDays(2)->addHours(16),
        ]);
        $compromisso = $this->janela($empresa, [
            'title' => 'Janela que vai sair da escala',
            'service_order_id' => $ordem->id,
        ]);

        $this->actingAs($admin);
        TenantContext::set($empresa->id);

        try {
            $agenda->apagar($compromisso);
        } finally {
            TenantContext::forget();
        }

        $this->assertFalse(Appointment::anyCompany()->whereKey($compromisso->id)->exists(), 'O recado saiu do calendário.');
        $this->assertNotNull($ordem->fresh()->scheduled_starts_at,
            'Apagar um compromisso da agenda nunca foi verbo de operação: a ordem e a janela dela continuam onde estavam.');

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $empresa->id,
            'entity_type' => 'Appointment',
            'entity_id' => (string) $compromisso->id,
            'action' => 'excluido',
            'user_id' => $admin->id,
        ]);
    }

    /**
     * A janela como o modelo a guarda, para os testes que provam a escrita do serviço sem
     * passar pela tela. Os extras entram por cima porque cada teste precisa de um estado,
     * uma duração ou um vínculo diferente.
     *
     * @param  array<string, mixed>  $extras
     */
    private function janela(Company $empresa, array $extras = []): Appointment
    {
        TenantContext::set($empresa->id);

        $janela = Appointment::query()->create($extras + [
            'company_id' => $empresa->id,
            'title' => 'Janela escrita pelo serviço',
            'type' => 'visit',
            'status' => 'scheduled',
            'starts_at' => now()->addDay()->startOfDay()->addHours(9),
            'ends_at' => now()->addDay()->startOfDay()->addHours(11),
            'all_day' => false,
        ]);

        TenantContext::forget();

        return $janela;
    }

    /**
     * A conta como o formulário do financeiro a entrega: tipo, categoria, valor,
     * vencimento e as ponteiras de relação. Os extras entram por cima porque cada
     * teste precisa de uma descrição, um valor ou uma carteira diferente.
     *
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function formularioDeConta(array $extras = []): array
    {
        return $extras + [
            'tipo' => FinancialRecord::REVENUE,
            'categoria' => 'contrato_mensal',
            'descricao' => 'Conta escrita pelo serviço',
            'valor' => '100.00',
            'vencimento' => '2026-10-20',
            'cliente_id' => null,
            'ordem_id' => null,
            'observacao' => null,
        ];
    }

    /**
     * As linhas do serviço: uma cobrança soma exatamente estas linhas, então linha
     * sem origem cadastrada seria conta mentirosa.
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

    /** @param  array<string, mixed>  $extras */
    private function servico(Company $empresa, string $nome, array $extras = []): Service
    {
        TenantContext::set($empresa->id);
        $servico = Service::query()->create($extras + ['name' => $nome, 'price' => 100, 'status' => 'active']);
        TenantContext::forget();

        return $servico;
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComTecnico(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('beta');

        return [$empresa, $this->makeUser('technician', $empresa, 'campo@test.local')];
    }

    /** @param  array<string, mixed>  $extras */
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

    /**
     * O pedido que a tela montaria: o controller resolve produto, tipo, quantidade,
     * dono da carga e ordem antes de chamar o serviço — aqui a chamada é direta, sem
     * HTTP, para provar que a escrita não depende da fachada.
     *
     * @return array{produto_id: int, tipo: string, quantidade: float, tecnico: ?Technician, ordem: ?ServiceOrder, usuario: User, custo: null, observacao: null}
     */
    private function pedido(User $autor, string $tipo, Product $produto, float $quantidade, ?Technician $tecnico = null, ?ServiceOrder $ordem = null): array
    {
        return [
            'produto_id' => $produto->id,
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'tecnico' => $tecnico,
            'ordem' => $ordem,
            'usuario' => $autor,
            'custo' => null,
            'observacao' => null,
        ];
    }

    private function carga(Technician $ficha, Product $produto): TechnicianStock
    {
        return TechnicianStock::query()
            ->where('technician_id', $ficha->id)
            ->where('product_id', $produto->id)
            ->sole();
    }

    /**
     * O chamado já nascido, para os testes que só olham a travessia. O nascimento é
     * escrito pelo modelo porque aqui o que se prova é o passo seguinte.
     *
     * @param  array<string, mixed>  $extras
     */
    private function chamado(Company $empresa, array $extras = []): Ticket
    {
        if (! array_key_exists('client_id', $extras)) {
            $extras['client_id'] = $this->cliente($empresa, 'Cliente do chamado')->id;
        }

        TenantContext::set($empresa->id);

        $chamado = Ticket::query()->create($extras + [
            'company_id' => $empresa->id,
            'protocol' => Ticket::proximoProtocolo(),
            'subject' => 'Vitrine sem pressão na frente da loja',
            'category' => 'refrigeracao',
            'priority' => 'normal',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        TicketStatusHistory::query()->create([
            'ticket_id' => $chamado->id,
            'user_id' => User::query()->where('company_id', $empresa->id)->value('id'),
            'from_status' => null,
            'to_status' => 'open',
            'note' => 'Chamado aberto na tela de chamados.',
            'created_at' => now(),
        ]);

        TenantContext::forget();

        return $chamado;
    }
}
