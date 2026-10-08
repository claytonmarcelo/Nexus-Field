<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\Technician;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 14 é o calendário da operação, e tudo que ele desenha vem do banco: os
 * compromissos que a conta alcança e a janela que cada ordem de serviço já pediu.
 * Estes testes prendem as quatro coisas que fariam da agenda um enfeite — o alcance
 * dentro do JSON, o arraste que só grava o que o servidor aceita, o fluxo do estado
 * e a régua igual entre o painel e o quadro.
 */
class AgendaTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_tela_monta_o_quadro_e_a_escritorio_le_a_empresa_inteira(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $esta = $this->compromisso($empresa, ['title' => 'Instalação de vitrine — Vinha d’Uva', 'client_id' => $padaria->id]);

        $tela = $this->actingAs($usuario)->get(route('agenda.index'));

        $tela->assertOk()
            ->assertSee('Agenda')
            ->assertSee('Novo compromisso')
            // O quadro é desenhado pelo script a partir do JSON. Sem ele a tela diz a
            // verdade em vez de deixar um calendário vazio fingindo que carrega.
            ->assertSee('A agenda não se desenha sem JavaScript');

        $eventos = $this->actingAs($usuario)->getJson($this->janelaSemana())->assertOk()->json();

        $this->assertCount(1, $eventos);
        $this->assertSame('c'.$esta->id, $eventos[0]['id']);
        $this->assertSame($esta->title, $eventos[0]['title']);
        $this->assertSame('compromisso', $eventos[0]['extendedProps']['natureza']);
        $this->assertTrue($eventos[0]['editable'], 'Quem conduz a escala move a janela pelo quadro.');
        $this->assertSame(route('agenda.show', $esta), $eventos[0]['url']);
        $this->assertSame(route('agenda.reschedule', $esta), $eventos[0]['extendedProps']['janela']);
        $this->assertContains('nf-fc-t--waiting', $eventos[0]['classNames'], 'Visita técnica sai no tom que o catálogo define.');
    }

    public function test_a_agenda_do_tecnico_so_sai_do_banco_com_as_janelas_dele(): void
    {
        [$empresa, $escritorio] = $this->empresaComAdmin();

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');

        $dele = $this->compromisso($empresa, ['title' => 'Revisão de soveladeira', 'technician_id' => $ricardo->id]);
        $desta = $this->compromisso($empresa, ['title' => 'Levantamento de coifa', 'technician_id' => $ana->id]);
        $esta = $this->compromisso($empresa, ['title' => 'Escala da semana', 'technician_id' => null]);

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        TenantContext::forget();
        $ricardo->update(['user_id' => $campo->id]);

        $titulos = array_column($this->actingAs($campo->fresh())->getJson($this->janelaSemana())->assertOk()->json(), 'title');

        $this->assertSame([$dele->title], $titulos,
            'A escala do técnico é a dele: nem a janela de outro técnico, nem o compromisso interno sem dono.');

        // Digitar ?tecnico= de outro na URL não abre a escala alheia: o alcance vem antes do filtro.
        $filtrando = $this->actingAs($campo->fresh())->getJson($this->janelaSemana(['tecnico' => $ana->id]))->assertOk()->json();
        $this->assertSame([], array_column($filtrando, 'title'));

        // A ficha e o arraste da janela alheia são recusados no servidor, não escondidos no HTML.
        $this->actingAs($campo->fresh())->get(route('agenda.show', $desta))->assertForbidden();
        $this->actingAs($campo->fresh())->patch(route('agenda.reschedule', $desta), [
            'inicio' => now()->addDay()->format('Y-m-d\TH:i:s'),
        ])->assertForbidden();

        // O técnico lê, não conduz: sem `agenda.update` o quadro não oferece arraste.
        $this->actingAs($campo->fresh())->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('data-mover="0"', false)
            ->assertSee('data-criar="0"', false)
            ->assertDontSee('Novo compromisso');

        // As três janelas têm a mesma hora de partida, então a ordem entre elas é do
        // banco, não da tela: o que se cobra aqui é o conjunto, não a sequência.
        $titulosDoEscritorio = array_column($this->actingAs($escritorio)->getJson($this->janelaSemana())->assertOk()->json(), 'title');
        sort($titulosDoEscritorio);
        $esperado = [$dele->title, $desta->title, $esta->title];
        sort($esperado);

        $this->assertSame($esperado, $titulosDoEscritorio,
            'O escritório lê a empresa inteira no mesmo filtro de janela.');
    }

    public function test_a_conta_de_cliente_nao_abre_a_agenda_da_empresa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $esta = $this->compromisso($empresa, ['title' => 'Visita de vistoria', 'client_id' => $padaria->id]);

        TenantContext::set($empresa->id);
        $contaCliente = $this->makeUser('client', $empresa, 'cliente@test.local');
        TenantContext::forget();
        $contaCliente->update(['client_id' => $padaria->id]);

        // O papel de cliente não tem `agenda.view`: a rota recusa antes de consultar.
        $this->actingAs($contaCliente->fresh())->get(route('agenda.index'))->assertForbidden();
        $this->actingAs($contaCliente->fresh())->getJson($this->janelaSemana())->assertForbidden();
        $this->actingAs($contaCliente->fresh())->get(route('agenda.show', $esta))->assertForbidden();

        $this->assertTrue(
            Appointment::query()->visiveisPara($contaCliente->fresh())->whereKey($esta->id)->exists(),
            'O compromisso do próprio cliente é alcançável por ele — o que falta aqui é a permissão do papel, não o alcance do banco.'
        );

        $this->actingAs($usuario)->get(route('agenda.index'))->assertOk();
    }

    public function test_a_ordem_agendada_aparece_no_quadro_e_nao_se_arrasta_de_le(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, 'OS-2026-0118', 'Manutenção na câmara fria', [
            'scheduled_starts_at' => now()->startOfDay()->addHours(14),
            'scheduled_ends_at' => now()->startOfDay()->addHours(16),
        ]);

        $cancelada = $this->ordem($empresa, 'OS-2026-0119', 'Serviço que não acontece', [
            'status' => 'canceled',
            'scheduled_starts_at' => now()->startOfDay()->addHours(9),
            'scheduled_ends_at' => now()->startOfDay()->addHours(10),
        ]);

        $rascunho = $this->ordem($empresa, 'OS-2026-0120', 'Ordem ainda não marcada', ['status' => 'draft']);

        $semJanela = $this->ordem($empresa, 'OS-2026-0121', 'Ordem aberta sem dia');

        $porId = [];
        foreach ($this->actingAs($usuario)->getJson($this->janelaSemana())->assertOk()->json() as $evento) {
            $porId[$evento['id']] = $evento;
        }

        $this->assertArrayHasKey('o'.$ordem->id, $porId, 'A janela marcada na ordem é lida da própria ordem e desenhada no calendário.');
        $this->assertArrayNotHasKey('o'.$cancelada->id, $porId, 'Ordem cancelada não reserva dia na escala de ninguém.');
        $this->assertArrayNotHasKey('o'.$rascunho->id, $porId, 'Rascunho ainda não foi marcado para ninguém.');
        $this->assertArrayNotHasKey('o'.$semJanela->id, $porId, 'Ordem sem janela não tem onde aparecer.');

        $this->assertSame('OS-2026-0118 · Manutenção na câmara fria', $porId['o'.$ordem->id]['title']);
        $this->assertFalse($porId['o'.$ordem->id]['editable'], 'Somente-leitura: a janela da ordem é movida na ficha dela, onde o motivo fica registrado.');
        $this->assertSame('ordem', $porId['o'.$ordem->id]['extendedProps']['natureza']);
        $this->assertSame(route('orders.show', $ordem), $porId['o'.$ordem->id]['url']);
        $this->assertContains('nf-fc-event--ordem', $porId['o'.$ordem->id]['classNames']);

        // `?ordem=` na tela de novo compromisso só aceita ordem que esta conta alcança.
        $desta = $this->ordem($empresa, 'OS-2026-0122', 'Montagem de balcão');
        $outra = $this->makeCompany('bravo');
        $estrangeira = $this->ordem($outra, 'OS-2026-0900', 'Ordem que não é daqui');

        $this->actingAs($usuario)->get(route('agenda.create', ['ordem' => $desta->id]))
            ->assertOk()
            ->assertSee('OS-2026-0122', false)
            ->assertDontSee('OS-2026-0900', false);

        $ficha = $this->actingAs($usuario)->get(route('agenda.create', ['ordem' => $estrangeira->id]))
            ->assertOk();
        $ficha->assertDontSee('OS-2026-0900', false);
        $this->assertNull(Appointment::anyCompany()->firstWhere('service_order_id', $estrangeira->id));
    }

    public function test_o_feed_limita_a_janela_que_le_e_exige_inicio_e_fim(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $dentro = $this->compromisso($empresa, [
            'title' => 'Dentro do teto',
            'starts_at' => now()->addDays(110)->startOfDay()->addHours(9),
            'ends_at' => now()->addDays(110)->startOfDay()->addHours(10),
        ]);
        $fora = $this->compromisso($empresa, [
            'title' => 'Longe demais para ser lido',
            'starts_at' => now()->addDays(200)->startOfDay()->addHours(9),
            'ends_at' => now()->addDays(200)->startOfDay()->addHours(10),
        ]);

        $titulos = array_column($this->actingAs($usuario)->getJson(route('agenda.feed', [
            'inicio' => now()->toDateString(),
            'fim' => now()->addYears(9)->toDateString(),
        ]))->assertOk()->json(), 'title');

        $this->assertContains($dentro->title, $titulos);
        $this->assertNotContains($fora->title, $titulos, 'O `?fim=` digitado na URL não vira varredura de nove anos na tabela.');

        $this->actingAs($usuario)->getJson(route('agenda.feed', ['inicio' => now()->toDateString()]))->assertStatus(422);
        $this->actingAs($usuario)->getJson(route('agenda.feed'))->assertStatus(422);

        $this->assertSame(2, Appointment::anyCompany()->count(), 'Ler a agenda não escreve nada.');
    }

    public function test_arrastar_a_janela_grava_o_que_o_servidor_aceita(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $comHora = $this->compromisso($empresa, ['title' => 'Coleta de equipamento para bancada']);
        $novo = now()->startOfDay()->addDays(2)->addHours(15);

        $resposta = $this->actingAs($usuario)->patchJson(route('agenda.reschedule', $comHora), [
            'inicio' => $novo->format('Y-m-d\TH:i:s'),
        ])->assertOk()->json();

        $comHora->refresh();
        $this->assertSame($novo->toDateTimeString(), $comHora->starts_at->toDateTimeString());
        $this->assertSame($novo->copy()->addHour()->toDateTimeString(), $comHora->ends_at->toDateTimeString(),
            'Sem fim na requisição, a duração gravada continua mandando: o arraste move, não encolhe.');
        $this->assertStringContainsString('até', $resposta['compromisso']['janela']);

        $fimDiferente = now()->startOfDay()->addDays(5)->addHours(19);
        $this->actingAs($usuario)->patchJson(route('agenda.reschedule', $comHora), [
            'inicio' => $novo->format('Y-m-d\TH:i:s'),
            'fim' => $fimDiferente->format('Y-m-d\TH:i:s'),
        ])->assertOk();
        $this->assertSame($fimDiferente->toDateTimeString(), $comHora->fresh()->ends_at->toDateTimeString());

        $deDiaInteiro = $this->compromisso($empresa, [
            'title' => 'Escala da semana — equipe Centro',
            'all_day' => true,
            'starts_at' => now()->startOfDay()->addDay(),
            'ends_at' => now()->startOfDay()->addDays(3)->addHours(8),
        ]);

        $inicioAntes = $deDiaInteiro->starts_at->toDateTimeString();
        $fimAntes = $deDiaInteiro->ends_at->toDateTimeString();

        $esta = now()->startOfDay()->addDays(8)->addHours(13);
        $this->actingAs($usuario)->patchJson(route('agenda.reschedule', $deDiaInteiro), [
            'inicio' => $esta->format('Y-m-d\TH:i:s'),
        ])->assertOk();

        // O arraste de um dia inteiro desloca a janela pelo número de dias entre o dia
        // de origem e o dia de queda: a hora gravada não é a que o mouse marcou.
        $dias = Carbon::parse($inicioAntes)->startOfDay()->diffInDays($esta->copy()->startOfDay(), false);

        $deDiaInteiro->refresh();
        $this->assertSame(Carbon::parse($inicioAntes)->addDays($dias)->toDateTimeString(), $deDiaInteiro->starts_at->toDateTimeString(),
            'Dia inteiro se move por dias inteiros: a madrugada gravada continua de pé.');
        $this->assertSame(Carbon::parse($fimAntes)->addDays($dias)->toDateTimeString(), $deDiaInteiro->ends_at->toDateTimeString(),
            'A duração que estava gravada acompanha o deslocamento inteiro.');

        // Janela torta é recusada no servidor, e nada é escrito.
        $antes = $comHora->fresh()->starts_at->toDateTimeString();
        $this->actingAs($usuario)->patchJson(route('agenda.reschedule', $comHora), [
            'inicio' => $novo->format('Y-m-d\TH:i:s'),
            'fim' => $novo->copy()->subHour()->format('Y-m-d\TH:i:s'),
        ])->assertStatus(422);
        $this->assertSame($antes, $comHora->fresh()->starts_at->toDateTimeString());
    }

    public function test_a_janela_de_um_compromisso_concluido_nao_se_move_mais(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $concluido = $this->compromisso($empresa, ['title' => 'Manutenção concluída — Bica Quente', 'status' => 'completed']);
        $antes = $concluido->starts_at->toDateTimeString();

        $recusado = $this->actingAs($usuario)->patchJson(route('agenda.reschedule', $concluido), [
            'inicio' => now()->addDays(4)->format('Y-m-d\TH:i:s'),
        ]);
        $recusado->assertStatus(422);
        $this->assertSame('Compromisso concluído não muda mais de janela.', $recusado->json('mensagem'));
        $this->assertSame($antes, $concluido->fresh()->starts_at->toDateTimeString());

        $this->actingAs($usuario)->get(route('agenda.edit', $concluido))->assertSessionHas('erro');

        $this->actingAs($usuario)->put(route('agenda.update', $concluido), [
            'title' => 'Reescrevendo um fato passado',
            'type' => 'visit',
            'starts_at' => $concluido->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $concluido->ends_at->format('Y-m-d\TH:i'),
        ])->assertSessionHas('erro');

        $this->assertSame('Manutenção concluída — Bica Quente', $concluido->fresh()->title);

        // O quadro devolve o compromisso concluído como não arrastável: nem o cursor
        // promete um gesto que o servidor vai recusar.
        $evento = $this->actingAs($usuario)->getJson($this->janelaSemana())->assertOk()->json()[0];
        $this->assertTrue($evento['extendedProps']['travado']);
        $this->assertFalse($evento['editable']);
    }

    public function test_o_compromisso_nasce_agendado_e_herda_o_cliente_do_que_prende(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $ordem = $this->ordem($empresa, 'OS-2026-0118', 'Instalação de vitrine', ['client_id' => $padaria->id]);
        $alheia = $this->cliente($outra, 'Cliente de outra empresa');
        $tecnicoAlheio = $this->tecnico($outra, 'Técnico de fora');

        // O cliente vem da ordem presa, e um `company_id` no payload não compra vaga
        // na escala de outra conta.
        $this->actingAs($usuario)->post(route('agenda.store'), [
            'title' => 'Vistoria pós-instalação',
            'type' => 'order',
            'service_order_id' => $ordem->id,
            'company_id' => $outra->id,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'location' => 'Cozinha do cliente',
            'description' => 'Avisar a portaria antes das 9h.',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status');

        $compromisso = Appointment::anyCompany()->firstWhere('title', 'Vistoria pós-instalação');
        $this->assertNotNull($compromisso);
        $this->assertSame($empresa->id, $compromisso->company_id, 'A empresa vem do login, nunca do payload.');
        $this->assertSame($padaria->id, $compromisso->client_id, 'Prender à ordem traz o cliente da ordem junto.');
        $this->assertNull($compromisso->technician_id, 'Sem técnico escolhido na ficha, a janela fica sem dono explícito.');
        $this->assertSame('scheduled', $compromisso->status, 'Ninguém escolhe o estado de entrada.');
        $this->assertFalse($compromisso->all_day);
        $this->assertSame(120, $compromisso->minutos(), 'A duração é medida da janela gravada.');
        // Marcar uma janela na agenda é escrita da empresa: tem de ter autor e hora
        // na trilha, como no resto do domínio.
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $empresa->id,
            'entity_type' => 'Appointment',
            'entity_id' => (string) $compromisso->id,
            'action' => 'criado',
            'user_id' => $usuario->id,
        ]);

        // A ficha presa aparece no calendário com o número da ordem que a origem conta.
        $ficha = $this->actingAs($usuario)->get(route('agenda.show', $compromisso))->assertOk();
        $ficha->assertSee('OS-2026-0118', false)->assertSee('Padaria Sant’Anna')->assertSee('Agendado');

        // Cliente e técnico de outra empresa são recusados na regra, antes de escrever.
        $antes = Appointment::anyCompany()->count();
        $this->actingAs($usuario)->post(route('agenda.store'), [
            'title' => 'Vistoria que não acontece',
            'type' => 'visit',
            'client_id' => $alheia->id,
            'technician_id' => $tecnicoAlheio->id,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors(['client_id', 'technician_id']);

        $this->assertSame($antes, Appointment::anyCompany()->count(),
            'A agenda da empresa não aceita vínculo que é de outra conta.');
    }

    public function test_a_tela_nao_grava_janela_sem_fim_depois_do_inicio(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $this->actingAs($usuario)->post(route('agenda.store'), [
            'title' => 'Janela torta',
            'type' => 'visit',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('ends_at');

        $this->assertFalse(Appointment::anyCompany()->where('title', 'Janela torta')->exists(),
            'Recusa no servidor não cria compromisso.');

        // Dia inteiro tem fim no mesmo dia por definição, e a regra acompanha a escolha.
        $this->actingAs($usuario)->post(route('agenda.store'), [
            'title' => 'Inventário na base',
            'type' => 'custom',
            'all_day' => '1',
            'starts_at' => now()->addDay()->startOfDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->startOfDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $esta = Appointment::anyCompany()->firstWhere('title', 'Inventário na base');
        $this->assertTrue($esta->all_day);

        $inativo = $this->tecnico($empresa, 'Técnico Desligado', ['status' => 'inactive']);
        $this->actingAs($usuario)->post(route('agenda.store'), [
            'title' => 'Janela para quem saiu da escala',
            'type' => 'visit',
            'technician_id' => $inativo->id,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('technician_id');

        $this->actingAs($usuario)->post(route('agenda.store'), [
            'title' => 'Tipo que a agenda não conhece',
            'type' => 'festa-surpresa',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('type');

        $this->actingAs($usuario)->post(route('agenda.store'), [
            'title' => '',
            'type' => 'visit',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('title');
    }

    public function test_o_estado_so_muda_pelo_fluxo_e_por_quem_conduz_a_escala(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $agendado = $this->compromisso($empresa, ['title' => 'Atendimento emergencial — Forno Alto']);

        $this->actingAs($usuario)->put(route('agenda.status', $agendado), ['estado' => 'completed'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $agendado->fresh()->status);

        // Concluído é terminal: reabrir seria reescrever um dia que já passou.
        $esta = $this->actingAs($usuario)->put(route('agenda.status', $agendado), ['estado' => 'scheduled']);
        $esta->assertSessionHas('erro');
        $this->assertSame('completed', $agendado->fresh()->status);

        $cancelado = $this->compromisso($empresa, ['title' => 'Atendimento remarcado', 'status' => 'canceled']);
        $this->actingAs($usuario)->put(route('agenda.status', $cancelado), ['estado' => 'scheduled'])->assertSessionHasNoErrors();
        $this->assertSame('scheduled', $cancelado->fresh()->status,
            'Cancelado volta a agendado: remarcar é exatamente o que a agenda serve para fazer.');

        $esta = $this->compromisso($empresa, ['title' => 'Sem técnico na janela']);
        $esta->update(['status' => 'canceled']);
        $this->actingAs($usuario)->put(route('agenda.status', $esta), ['estado' => 'completed'])->assertSessionHas('erro');
        $this->assertSame('canceled', $esta->fresh()->status, 'Do cancelado só se volta a agendado.');

        $this->actingAs($usuario)->put(route('agenda.status', $agendado), ['estado' => 'nada-deste-catalogo'])
            ->assertSessionHasErrors('estado');

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        TenantContext::forget();

        $novo = $this->compromisso($empresa, ['title' => 'Janela que o técnico não move']);
        $this->actingAs($campo->fresh())->put(route('agenda.status', $novo), ['estado' => 'completed'])->assertForbidden();
        $this->assertSame('scheduled', $novo->fresh()->status);
    }

    public function test_o_painel_e_o_calendario_contam_a_mesma_agenda(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');

        $hoje = $this->compromisso($empresa, ['title' => 'Vistoria hoje', 'technician_id' => $ricardo->id]);
        $deOutro = $this->compromisso($empresa, ['title' => 'Vistoria hoje da Ana', 'technician_id' => $ana->id]);

        // Janela sem técnico marcado, presa a uma ordem que é dele: alcançável pela ordem.
        $daOrdem = $this->compromisso($empresa, [
            'title' => 'Reteste da montagem',
            'technician_id' => null,
            'service_order_id' => $this->ordem($empresa, 'OS-2026-0777', 'Montagem de balcão', [
                'technician_id' => $ricardo->id,
            ])->id,
        ]);
        $depois = $this->compromisso($empresa, [
            'title' => 'Visita daqui a quatro dias',
            'technician_id' => $ricardo->id,
            'starts_at' => now()->addDays(4)->startOfDay()->addHours(9),
            'ends_at' => now()->addDays(4)->startOfDay()->addHours(10),
        ]);

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        TenantContext::forget();
        $ricardo->update(['user_id' => $campo->id]);

        $painel = (new DashboardMetrics($campo->fresh()))->toArray();
        $titulosDoPainel = $painel['agenda']['hoje']->pluck('title')->sort()->values()->all();

        $this->assertSame([$daOrdem->title, $hoje->title], $titulosDoPainel,
            'O painel entrega exatamente as janelas de hoje que o calendário entregaria para este técnico.');

        $this->assertSame(3, $painel['agenda']['proximos'],
            'Hoje (2 janelas) mais a de daqui a quatro dias: a contagem de sete dias é do alcance dele, não da empresa.');

        $titulos = array_column($this->actingAs($campo->fresh())->getJson($this->janelaSemana())->assertOk()->json(), 'title');
        sort($titulos);
        $esperado = [$daOrdem->title, $depois->title, $hoje->title];
        sort($esperado);

        $this->assertSame($esperado, $titulos, 'Quadro e painel leem o mesmo alcance; nada aparece num e falta no outro.');
        $this->assertNotContains($deOutro->title, $titulos);

        // O escritório, que alcança as quatro, conta as quatro no mesmo quadro.
        $this->assertCount(4, $this->actingAs($usuario)->getJson($this->janelaSemana())->assertOk()->json());
    }

    public function test_apagar_a_janela_nao_apaga_o_trabalho(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, 'OS-2026-0118', 'Manutenção na câmara fria');
        $compromisso = $this->compromisso($empresa, ['title' => 'Reteste da câmara', 'service_order_id' => $ordem->id]);

        $this->actingAs($usuario)->delete(route('agenda.destroy', $compromisso))
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('appointments', ['id' => $compromisso->id]);
        $this->assertSame('open', $ordem->fresh()->status, 'Apagar a janela não apaga a ordem: o trabalho continua onde estava.');

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        TenantContext::forget();

        $sobra = $this->compromisso($empresa, ['title' => 'Janela que sobra']);
        $this->actingAs($campo->fresh())->delete(route('agenda.destroy', $sobra))->assertForbidden();
        $this->assertDatabaseHas('appointments', ['id' => $sobra->id]);
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /**
     * A janela que o calendário pede ao desenhar o quadro. Sete dias a partir de
     * hoje, e não a semana de calendário: os compromissos padrão são criados
     * relativos a `now()`, e uma janela que depende de que dia da semana hoje é
     * deixaria de fora a visita marcada para depois de amanhã.
     *
     * @param  array<string, mixed>  $extras
     */
    private function janelaSemana(array $extras = []): string
    {
        return route('agenda.feed', [
            'inicio' => now()->startOfDay()->toDateTimeString(),
            'fim' => now()->addDays(6)->endOfDay()->toDateTimeString(),
        ] + $extras);
    }

    /** @param  array<string, mixed>  $extras */
    private function compromisso(Company $empresa, array $extras = []): Appointment
    {
        TenantContext::set($empresa->id);
        $compromisso = Appointment::query()->create($this->atributos($extras));
        TenantContext::forget();

        return $compromisso;
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function atributos(array $extras = []): array
    {
        $inicio = $extras['starts_at'] ?? now()->startOfDay()->addHours(10);

        return array_merge([
            'title' => 'Visita de vistoria — Empório Serra Verde',
            'type' => 'visit',
            'status' => 'scheduled',
            'starts_at' => $inicio,
            'ends_at' => Carbon::parse($inicio)->addHour(),
            'all_day' => false,
            'location' => 'Endereço do cliente',
        ], $extras);
    }

    /** @param  array<string, mixed>  $extras */
    private function ordem(Company $empresa, string $numero, string $titulo, array $extras = []): ServiceOrder
    {
        $clienteId = $extras['client_id'] ?? $this->cliente($empresa, 'Cliente '.$numero)->id;

        TenantContext::set($empresa->id);
        $ordem = ServiceOrder::query()->create(array_merge([
            'company_id' => $empresa->id,
            'client_id' => $clienteId,
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
}
