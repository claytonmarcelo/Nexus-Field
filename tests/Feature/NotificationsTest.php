<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Notifier;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 19 é o sino. O que se prende aqui não é a tela, são três decisões do
 * servidor: a bandeja é da conta, não do papel — `notifications.view` abre a
 * central para qualquer um, e quem separa as caixas é a coluna `user_id` dentro da
 * consulta; ninguém recebe aviso do que acabou de fazer; e aviso recorrente só
 * toca de novo depois que o anterior foi lido. A varredura diária fecha o conjunto:
 * atraso, vencimento e a agenda de amanhã, sem empilhar o mesmo fato e sem
 * atravessar a fronteira da empresa.
 */
class NotificationsTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_central_mostra_só_os_avisos_da_conta_que_logou(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');

        TenantContext::set($empresa->id);
        Notifier::para($admin, 'ordem.criada', 'Aviso privativo do escritório');
        Notifier::para($supervisora, 'chamdo.aberto', 'Aviso da central da supervisora');
        TenantContext::forget();

        $daAdmin = Notification::anyCompany()->firstWhere('title', 'Aviso privativo do escritório');

        $this->actingAs($supervisora)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Aviso da central da supervisora')
            ->assertDontSee('Aviso privativo do escritório');

        $this->actingAs($supervisora)
            ->patch(route('notifications.mark', $daAdmin))
            ->assertNotFound();

        $this->assertNull($daAdmin->fresh()->read_at,
            'O sino de outra conta não ganha carimbo de quem só conhece o id da linha.');
    }

    public function test_a_ordem_entregue_a_um_técnico_toca_o_sino_dele_e_não_o_de_quem_abriu(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $campo = $this->conta($empresa, 'technician', 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $this->actingAs($admin)->post(route('orders.store'), [
            'client_id' => $cliente->id,
            'technician_id' => $ricardo->id,
            'title' => 'Reparo na vitrine refrigerada',
            'priority' => 'normal',
            'status' => 'open',
            'scheduled_starts_at' => '2026-10-12T08:00',
            'street' => 'Rua Aurora',
            'number_address' => '210',
            'city' => 'São Paulo',
            'state' => 'sp',
        ])->assertSessionHasNoErrors();

        $ordem = ServiceOrder::anyCompany()->firstWhere('title', 'Reparo na vitrine refrigerada');
        $aviso = Notification::anyCompany()->where('type', 'ordem.criada')->where('user_id', $campo->id)->first();

        $this->assertNotNull($aviso, 'Ordem que nasce com técnico escolhido acorda o sino dele.');
        $this->assertStringContainsString($ordem->number, $aviso->title);
        $this->assertSame(route('orders.show', $ordem), $aviso->link);

        $this->assertFalse(Notification::anyCompany()->where('user_id', $admin->id)->where('type', 'ordem.criada')->exists(),
            'Quem abriu a ordem acabou de ver o que fez: o sino não toca para o autor do ato.');
    }

    public function test_ordem_sem_responsável_avisa_quem_responde_pela_escala(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $cliente = $this->cliente($empresa, 'Restaurante Al Dente');

        $this->actingAs($admin)->post(route('orders.store'), [
            'client_id' => $cliente->id,
            'title' => 'Instalação do forno combinado',
            'priority' => 'normal',
            'status' => 'open',
            'scheduled_starts_at' => '2026-10-12T09:00',
            'street' => 'Rua das Palmeiras',
            'number_address' => '15',
            'city' => 'São Paulo',
            'state' => 'sp',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Notification::anyCompany()->where('type', 'ordem.criada')->where('user_id', $supervisora->id)->exists(),
            'Ordem sem dono é tarefa de quem aprova a escala, e essa pessoa precisa saber.');
        $this->assertFalse(Notification::anyCompany()->where('type', 'ordem.criada')->where('user_id', $admin->id)->exists());
    }

    public function test_comissionar_um_técnico_avisa_a_conta_dele(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $campo = $this->conta($empresa, 'technician', 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        $ordem = $this->ordem($empresa, 'OS-2026-0601', 'Troca de resistências do forno');

        $this->actingAs($admin)->post(route('orders.assignments.store', $ordem), [
            'tecnico_id' => $ricardo->id,
            'comissao_nota' => 'Segunda etapa da instalação, com apoio do estagiário.',
        ])->assertSessionHasNoErrors();

        $aviso = Notification::anyCompany()->where('type', 'ordem.atribuida')->where('user_id', $campo->id)->first();
        $this->assertNotNull($aviso, 'Entrar no quadro de comissão sem o sino do técnico é nomeação por rádio de corredor.');
        $this->assertStringContainsString($ordem->number, $aviso->title);
        $this->assertSame('Segunda etapa da instalação, com apoio do estagiário.', $aviso->body);
    }

    public function test_concluir_a_ordem_avisa_o_quadro_e_a_conta_do_cliente(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $campo = $this->conta($empresa, 'technician', 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');
        $consumidora = $this->conta($empresa, 'client', 'cliente@test.local');
        $consumidora->update(['client_id' => $cliente->id]);

        $ordem = $this->ordem($empresa, 'OS-2026-0602', 'Reparo na esteira', [
            'client_id' => $cliente->id,
            'technician_id' => $ricardo->id,
        ]);

        $this->actingAs($admin)->put(route('orders.status', $ordem), ['estado' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('orders.status', $ordem), ['estado' => 'completed'])
            ->assertSessionHasNoErrors();

        foreach ([$campo->id, $consumidora->id] as $id) {
            $this->assertTrue(Notification::anyCompany()->where('type', 'ordem.concluida')->where('user_id', $id)->exists(),
                "A conta {$id} trabalharia nesta ordem e tem direito ao aviso de conclusão.");
        }

        $this->assertSame(0, Notification::anyCompany()->where('user_id', $admin->id)->count(),
            'Movimento do meio do fluxo não toca sino, e o autor do fim também não.');
    }

    public function test_chamado_novo_toca_uma_só_vez_para_quem_pode_atender(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $campo = $this->conta($empresa, 'technician', 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        $cliente = $this->cliente($empresa, 'Cafeteria Boulevard');

        $this->actingAs($admin)->post(route('tickets.store'), [
            'client_id' => $cliente->id,
            'category' => 'refrigeracao',
            'priority' => 'high',
            'subject' => 'Mostrador sem gelar desde a abertura',
            'description' => '<p>Termômetro marcando 14 °C no interior.</p>',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Notification::anyCompany()->where('type', 'chamdo.aberto')->where('user_id', $campo->id)->count(),
            'Quem conduz o chamado recebe o aviso pela permissão — um sino, não uma campanha.');
        $this->assertFalse(Notification::anyCompany()->where('type', 'chamdo.aberto')->where('user_id', $admin->id)->exists());
    }

    public function test_resolver_o_chamado_avisa_a_conta_do_cliente_e_o_responsável(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $cliente = $this->cliente($empresa, 'Cafeteria Boulevard');
        $consumidora = $this->conta($empresa, 'client', 'cliente@test.local');
        $consumidora->update(['client_id' => $cliente->id]);
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');

        $chamado = $this->chamado($empresa, $cliente, ['responsible_user_id' => $supervisora->id]);

        $this->actingAs($admin)->put(route('tickets.status', $chamado), ['estado' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('tickets.status', $chamado), [
            'estado' => 'resolved',
            'nota' => 'Termostato trocado e ciclo de 200 °C aprovado no teste.',
        ])->assertSessionHasNoErrors();

        foreach ([$consumidora->id, $supervisora->id] as $id) {
            $aviso = Notification::anyCompany()->where('type', 'chamdo.resolvido')->where('user_id', $id)->first();
            $this->assertNotNull($aviso, "A conta {$id} espera a resposta deste chamado.");
            $this->assertSame('Termostato trocado e ciclo de 200 °C aprovado no teste.', $aviso->body);
        }

        $this->assertFalse(Notification::anyCompany()->where('type', 'chamdo.resolvido')->where('user_id', $admin->id)->exists());
    }

    public function test_a_falta_no_central_soa_uma_vez_para_quem_repõe(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $produto = $this->produto($empresa, 'Compressor Embraco FDS7', ['reorder_point' => 10]);

        // Compra acima do ponto de reposição não acorda sino nenhum.
        $this->actingAs($admin)->post(route('movements.store'), [
            'tipo' => 'purchase', 'produto_id' => $produto->id, 'quantidade' => '20',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, Notification::anyCompany()->where('type', 'estoque.baixo')->count());

        // O ajuste que cruza a linha avisa quem responde pelo inventário — uma vez.
        $this->actingAs($admin)->post(route('movements.store'), [
            'tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '-15',
        ])->assertSessionHasNoErrors();

        $avisados = Notification::anyCompany()->where('type', 'estoque.baixo')->pluck('user_id')->sort()->values()->all();
        $esperados = [$admin->id, $supervisora->id];
        sort($esperados);
        $this->assertSame($esperados, $avisados,
            'A falta de peça é tarefa de quem repõe, não só de quem registrou a baixa.');

        $this->actingAs($admin)->post(route('movements.store'), [
            'tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '-1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, Notification::anyCompany()->where('type', 'estoque.baixo')->count(),
            'Com o aviso anterior pendente de leitura, a mesma falta não empilha sino.');

        $this->actingAs($admin)->patch(route('notifications.mark-all'))->assertRedirect();

        $this->actingAs($admin)->post(route('movements.store'), [
            'tipo' => 'adjustment', 'produto_id' => $produto->id, 'quantidade' => '-0.5',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, Notification::anyCompany()->where('type', 'estoque.baixo')->count(),
            'Lido o primeiro aviso, a falta que continua pode soar de novo — para quem leu; quem ainda não leu não é martelado.');
        $this->assertTrue(Notification::anyCompany()
            ->where('type', 'estoque.baixo')
            ->where('user_id', $admin->id)
            ->whereNull('read_at')
            ->exists());
    }

    public function test_marcar_todos_só_carimba_a_bandeja_desta_conta(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');

        TenantContext::set($empresa->id);
        Notifier::para($admin, 'ordem.atrasada', 'Aviso um do admin');
        Notifier::para($admin, 'ordem.atrasada', 'Aviso dois do admin');
        Notifier::para($supervisora, 'ordem.atrasada', 'Aviso da supervisora');
        TenantContext::forget();

        $this->actingAs($admin)->patch(route('notifications.mark-all'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(2, Notification::anyCompany()->where('user_id', $admin->id)->whereNotNull('read_at')->count());
        $this->assertSame(0, Notification::anyCompany()->where('user_id', $supervisora->id)->whereNotNull('read_at')->count(),
            'O botão varre a bandeja de quem clicou, nunca a da empresa.');

        $this->actingAs($admin)->patch(route('notifications.mark-all'))
            ->assertRedirect()
            ->assertSessionHas('aviso');
    }

    public function test_a_varredura_diária_grava_atraso_vencimento_e_agenda_sem_repetir(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $campo = $this->conta($empresa, 'technician', 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        TenantContext::set($empresa->id);

        ServiceOrder::query()->create([
            'client_id' => $cliente->id,
            'technician_id' => $ricardo->id,
            'number' => 'OS-2026-0699',
            'title' => 'Revisão da câmara fria',
            'priority' => 'normal',
            'status' => 'in_progress',
            'scheduled_ends_at' => now()->subHours(3),
        ]);

        FinancialRecord::query()->create([
            'client_id' => $cliente->id,
            'type' => 'revenue',
            'category' => 'visita_tecnica',
            'description' => 'Visita técnica de revisão',
            'amount' => 480,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $compromisso = Appointment::query()->create([
            'technician_id' => $ricardo->id,
            'client_id' => $cliente->id,
            'title' => 'Troca do evaporador',
            'type' => 'visit',
            'status' => 'scheduled',
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 30),
        ]);

        TenantContext::forget();

        $this->artisan('nf:notificacoes:diaria')->assertSuccessful();

        foreach ([$campo->id, $admin->id, $supervisora->id] as $id) {
            $this->assertTrue(Notification::anyCompany()->where('type', 'ordem.atrasada')->where('user_id', $id)->exists(),
                "A conta {$id} responde por esta ordem e tem de ouvir o atraso.");
        }

        foreach ([$admin->id, $supervisora->id] as $id) {
            $this->assertTrue(Notification::anyCompany()->where('type', 'financeiro.vencendo')->where('user_id', $id)->exists(),
                'Quem lê a carteira vê o vencimento chegando, sem abrir a tela para isso.');
        }

        $lembrete = Notification::anyCompany()->where('type', 'agenda.lembrete')->where('user_id', $campo->id)->first();
        $this->assertNotNull($lembrete, 'O técnico da janela de amanhã é avisado por sino, não por memória.');
        $this->assertSame(route('agenda.show', $compromisso), $lembrete->link);

        $total = Notification::anyCompany()->count();
        $this->artisan('nf:notificacoes:diaria')->assertSuccessful();
        $this->assertSame($total, Notification::anyCompany()->count(),
            'Varredura sobre bandeja ainda não lida não empilha o mesmo fato.');
    }

    public function test_o_sino_da_barra_mostra_a_contagem_e_não_mistura_empresas(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $outra = $this->makeCompany('beta');
        $adminOutra = $this->makeUser('administrator', $outra, 'b@test.local');

        TenantContext::set($empresa->id);
        Notifier::para($admin, 'chamdo.aberto', 'Aviso da empresa alfa');
        TenantContext::forget();

        TenantContext::set($outra->id);
        Notifier::para($adminOutra, 'chamdo.aberto', 'Aviso da empresa beta');
        TenantContext::forget();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aviso da empresa alfa')
            ->assertSee('nf-bell-count', false)
            ->assertDontSee('Aviso da empresa beta');
    }

    public function test_o_escritório_da_outra_empresa_não_ouve_o_sino(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisora = $this->conta($empresa, 'supervisor', 'sup@test.local');
        $outra = $this->makeCompany('beta');
        $supervisoraOutra = $this->conta($outra, 'supervisor', 'supb@test.local');
        $cliente = $this->cliente($empresa, 'Padaria Sant’Anna');

        $this->actingAs($admin)->post(route('orders.store'), [
            'client_id' => $cliente->id,
            'title' => 'Instalação do balcão gelado',
            'priority' => 'normal',
            'status' => 'open',
            'scheduled_starts_at' => '2026-10-12T09:00',
            'street' => 'Rua Aurora',
            'number_address' => '210',
            'city' => 'São Paulo',
            'state' => 'sp',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Notification::anyCompany()->where('user_id', $supervisora->id)->exists(),
            'O escritório desta empresa ouve o que a sua empresa abriu.');
        $this->assertFalse(Notification::anyCompany()->where('user_id', $supervisoraOutra->id)->exists(),
            'A mesma permissão no outro tenant não dá direito ao sino desta empresa.');
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $this->categoria($empresa);

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    private function categoria(Company $empresa): ServiceCategory
    {
        TenantContext::set($empresa->id);
        $categoria = ServiceCategory::query()->create(['name' => 'Refrigeração', 'slug' => 'refrigeracao']);
        TenantContext::forget();

        return $categoria;
    }

    private function conta(Company $empresa, string $papel, string $email): User
    {
        TenantContext::set($empresa->id);
        $usuario = $this->makeUser($papel, $empresa, $email);
        TenantContext::forget();

        return $usuario;
    }

    /** @param array<string, mixed> $extras */
    private function cliente(Company $empresa, string $nome, array $extras = []): Client
    {
        TenantContext::set($empresa->id);
        $cliente = Client::query()->create($extras + ['name' => $nome, 'status' => 'active']);
        TenantContext::forget();

        return $cliente;
    }

    /** @param array<string, mixed> $extras */
    private function tecnico(Company $empresa, string $nome, array $extras = []): Technician
    {
        TenantContext::set($empresa->id);
        $ficha = Technician::query()->create($extras + ['name' => $nome, 'status' => 'available']);
        TenantContext::forget();

        return $ficha;
    }

    /** @param array<string, mixed> $extras */
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

    /** @param array<string, mixed> $extras */
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

    /** @param array<string, mixed> $extras */
    private function chamado(Company $empresa, Client $cliente, array $extras = []): Ticket
    {
        TenantContext::set($empresa->id);
        $chamado = Ticket::query()->create($extras + [
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'protocol' => 'CH-'.now()->format('Y').'-0001',
            'subject' => 'Câmara fria apitando alarme de porta',
            'description' => '<p>Relato registrado pelo cliente.</p>',
            'category' => 'refrigeracao',
            'priority' => 'normal',
            'status' => 'open',
            'opened_at' => now()->subHour(),
        ]);
        TenantContext::forget();

        return $chamado;
    }
}
