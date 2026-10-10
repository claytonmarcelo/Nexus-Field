<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
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

        $this->assertStringContainsString('FluxoDeOrdem', $ordens);
        $this->assertStringContainsString('RegistroDePresenca', $chegadas);
        $this->assertStringContainsString('LancamentoDeEstoque', $estoque);
        $this->assertStringContainsString('FluxoDeChamado', $chamados);
        $this->assertStringContainsString('ConversaDeChamado', $notas);
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
