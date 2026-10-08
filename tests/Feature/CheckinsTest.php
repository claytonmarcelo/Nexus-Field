<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\Technician;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\Distancia;
use App\Support\Formatters;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 15 é a passagem do técnico pelo endereço da ordem. O que se prende aqui é a
 * origem de cada número: a hora sai do relógio do servidor, o técnico sai da ficha
 * de quem está logado, e a distância sai de uma conta feita aqui dentro. Nenhum dos
 * três pode ser digitado no formulário, porque os três respondem por um fato com
 * interessado direto nele.
 */
class CheckinsTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private const ENDERECO = ['latitude' => '-23.5612340', 'longitude' => '-46.6551000'];

    /** Um ponto a pouco mais de cem metros do endereço: 0,0009 grau de latitude. */
    private const PERTO = ['latitude' => '-23.5603340', 'longitude' => '-46.6551000'];

    private const LONGE = ['latitude' => '-23.5512340', 'longitude' => '-46.6551000'];

    public function test_a_chegada_sai_do_relogio_de_cá_da_ficha_de_quem_loga_e_da_conta_de_distância(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $campo = $this->contaDeTécnico($empresa, $ricardo);

        $ordem = $this->ordem($empresa, 'OS-2026-0301', 'Reparo na câmara fria', [
            'technician_id' => $ricardo->id,
            ...self::ENDERECO,
        ]);

        // O payload tenta forjar as três coisas que a tela não pode decidir.
        $this->actingAs($campo->fresh())
            ->post(route('orders.checkin', $ordem), [
                'technician_id' => $this->tecnico($empresa, 'Outro Técnico')->id,
                'checkin_at' => now()->subDays(30)->toDateTimeString(),
                'distancia' => 3,
                'latitude' => self::PERTO['latitude'],
                'longitude' => self::PERTO['longitude'],
                'observacao' => 'Portão bloqueado, cliente avisado na recepção.',
            ])
            ->assertRedirect(route('orders.show', $ordem))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $visita = ServiceOrderCheckin::anyCompany()->firstWhere('service_order_id', $ordem->id);
        $this->assertNotNull($visita);

        $this->assertSame($ricardo->id, $visita->technician_id,
            'Quem esteve lá vem da ficha de quem fez o login, nunca do formulário.');
        $this->assertTrue($visita->checkin_at->between(now()->subMinute(), now()->addMinute()),
            'O carimbo é o agora do servidor: uma hora digitada não reescreve o passado.');
        $this->assertSame('open', $visita->status);
        $this->assertNull($visita->checkout_at);

        // A medida é geometria, não campo: o vão entre a posição lida e o endereço.
        $esperada = Distancia::metros(
            self::PERTO['latitude'], self::PERTO['longitude'],
            self::ENDERECO['latitude'], self::ENDERECO['longitude'],
        );
        $this->assertSame($esperada, (float) $visita->checkin_distance);
        $this->assertGreaterThan(90, $esperada);
        $this->assertLessThan(120, $esperada, 'Cem metros de latitude são cem metros no banco, não três.');

        TenantContext::set($empresa->id);
        $this->assertFalse($visita->foraDoRaio(), 'Dentro dos 250 m que a casa aceita por padrão.');
        TenantContext::forget();

        // A chegada empurra a ordem pelo fluxo dela, com trilha e autor.
        $ordem->refresh();
        $this->assertSame('in_progress', $ordem->status);
        $this->assertNotNull($ordem->started_at);
        $this->assertDatabaseHas('service_order_status_history', [
            'service_order_id' => $ordem->id,
            'user_id' => $campo->id,
            'from_status' => 'open',
            'to_status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $empresa->id,
            'entity_type' => 'ServiceOrderCheckin',
            'entity_id' => (string) $visita->id,
            'action' => 'chegada em campo',
            'user_id' => $campo->id,
            'description' => 'OS-2026-0301: Ricardo Prado chegou ao local ('
                .Formatters::decimal($esperada).' m do endereço).',
        ]);
    }

    public function test_a_posição_é_opcional_e_a_falta_dela_não_se_chama_zero(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, 'OS-2026-0302', 'Troca de resistência no subsolo', [
            'technician_id' => $this->tecnico($empresa, 'Ana Beltrão')->id,
            ...self::ENDERECO,
        ]);

        // Sem GPS a chegada continua sendo chegada — o que não existe é a medida.
        $this->actingAs($admin)->post(route('orders.checkin', $ordem), [
            'observacao' => 'Sem sinal de satélite no subsolo: registro feito pela porta do poço.',
        ])->assertRedirect(route('orders.show', $ordem))->assertSessionHasNoErrors();

        $visita = ServiceOrderCheckin::anyCompany()->firstWhere('service_order_id', $ordem->id);
        $this->assertNull($visita->checkin_latitude);
        $this->assertNull($visita->checkin_distance);

        TenantContext::set($empresa->id);
        $this->assertNull($visita->foraDoRaio(), 'Sem medida não é "dentro do raio" disfarçado.');
        TenantContext::forget();

        $this->assertStringContainsString(
            'sem posição lida no aparelho',
            $this->descriçãoDaAuditoria('chegada em campo'),
        );

        $this->actingAs($admin)->get(route('checkins.index'))
            ->assertOk()
            ->assertSee('sem posição lida')
            ->assertSee('OS-2026-0302', false);

        // Posição incompleta é recusada: meia coordenada não é posição, é dedo torto.
        $this->actingAs($admin)->post(route('orders.checkin', $ordem), [
            'latitude' => '-23.5603',
        ])->assertSessionHasErrors('longitude');

        $this->assertSame(1, ServiceOrderCheckin::anyCompany()->count());
    }

    public function test_a_saída_encerra_a_passagem_mas_não_a_ordem(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ordem = $this->ordem($empresa, 'OS-2026-0303', 'Instalação de vitrine', [
            'technician_id' => $this->tecnico($empresa, 'Ana Beltrão')->id,
            ...self::ENDERECO,
        ]);

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), self::PERTO)
            ->assertRedirect()->assertSessionHasNoErrors();

        $visita = ServiceOrderCheckin::anyCompany()->firstWhere('service_order_id', $ordem->id);

        $this->actingAs($admin)->patch(route('checkins.checkout', $visita), [
            'latitude' => self::PERTO['latitude'],
            'longitude' => self::PERTO['longitude'],
            'observacao' => 'Equipamento testado em carga, cliente assinou a ordem.',
        ])->assertRedirect(route('orders.show', $ordem))->assertSessionHas('status');

        $visita->refresh();
        $this->assertSame('closed', $visita->status);
        $this->assertNotNull($visita->checkout_at);
        $this->assertNotNull($visita->checkout_distance);
        $this->assertSame('Equipamento testado em carga, cliente assinou a ordem.', $visita->observation);
        $this->assertSame(0, $visita->duracaoMinutos());

        $this->assertSame('in_progress', $ordem->fresh()->status,
            'Ir embora não é terminar o serviço: concluir tem estado, nota e quem aprova.');

        // Repetir a saída não reescreve o carimbo nem apaga o primeiro.
        $saida = $visita->checkout_at->toDateTimeString();
        $this->actingAs($admin)->patch(route('checkins.checkout', $visita), [
            'observacao' => 'Riscando o que já aconteceu',
        ])->assertSessionHas('aviso');

        $visita->refresh();
        $this->assertSame($saida, $visita->checkout_at->toDateTimeString());
        $this->assertNotSame('Riscando o que já aconteceu', $visita->observation);

        // Relato vazio na saída não apaga o que a chegada registrou.
        $outra = $this->ordem($empresa, 'OS-2026-0304', 'Reparo no forno alto', [
            'technician_id' => $this->tecnico($empresa, 'Jonas Reis')->id,
            ...self::ENDERECO,
        ]);
        $this->actingAs($admin)->post(route('orders.checkin', $outra), [
            'latitude' => self::PERTO['latitude'],
            'longitude' => self::PERTO['longitude'],
            'observacao' => 'Chamado aberto pela central.',
        ])->assertSessionHasNoErrors();

        $passagem = ServiceOrderCheckin::anyCompany()->firstWhere('service_order_id', $outra->id);
        $this->actingAs($admin)->patch(route('checkins.checkout', $passagem), [])
            ->assertSessionHasNoErrors();
        $this->assertSame('Chamado aberto pela central.', $passagem->fresh()->observation);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ServiceOrderCheckin',
            'entity_id' => (string) $visita->id,
            'action' => 'saída em campo',
            'user_id' => $admin->id,
        ]);
    }

    public function test_rascunho_ordem_encerrada_e_passagem_aberta_recusam_uma_nova_chegada(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ana = $this->tecnico($empresa, 'Ana Beltrão');

        $rascunho = $this->ordem($empresa, 'OS-2026-0305', 'Orçamento virando ordem', [
            'status' => 'draft', 'technician_id' => $ana->id, ...self::ENDERECO,
        ]);

        $this->actingAs($admin)->post(route('orders.checkin', $rascunho), self::PERTO)
            ->assertRedirect(route('orders.show', $rascunho))
            ->assertSessionHas('erro', fn (string $msg) => str_contains($msg, 'ainda é rascunho'));
        $this->assertSame(0, ServiceOrderCheckin::anyCompany()->count());

        $concluida = $this->ordem($empresa, 'OS-2026-0306', 'Serviço do mês passado', [
            'status' => 'completed', 'technician_id' => $ana->id, ...self::ENDERECO,
        ]);

        $this->actingAs($admin)->post(route('orders.checkin', $concluida), self::PERTO)
            ->assertSessionHas('erro', fn (string $msg) => str_contains($msg, 'já foi encerrada'));
        $this->assertSame(0, ServiceOrderCheckin::anyCompany()->count());

        // Ordem sem técnico responsável: quem responde pela escala não tem quem
        // inventar como técnico da passagem.
        $semDono = $this->ordem($empresa, 'OS-2026-0307', 'Ordem aberta sem comissão', self::ENDERECO);
        $this->actingAs($admin)->post(route('orders.checkin', $semDono), self::PERTO)
            ->assertSessionHasErrors('ordem');
        $this->assertSame(0, ServiceOrderCheckin::anyCompany()->count());

        // Segunda chegada com a primeira ainda em campo.
        $aberta = $this->ordem($empresa, 'OS-2026-0308', 'Manutenção em andamento', [
            'technician_id' => $ana->id, ...self::ENDERECO,
        ]);
        $this->actingAs($admin)->post(route('orders.checkin', $aberta), self::PERTO)->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('orders.checkin', $aberta), self::LONGE)
            ->assertSessionHas('aviso', fn (string $msg) => str_contains($msg, 'já está em campo'));

        $this->assertSame(1, ServiceOrderCheckin::anyCompany()->count(),
            'Dois carimbos de entrada abertos para a mesma pessoa na mesma ordem é duplicata, não histórico.');
    }

    public function test_a_presença_fica_na_ficha_da_ordem_e_a_saída_com_quem_esteve_lá(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $jonas = $this->tecnico($empresa, 'Jonas Reis');
        $supervisora = $this->makeUser('supervisor', $empresa, 'sup@test.local');
        $atendente = $this->makeUser('employee', $empresa, 'papel@test.local');
        $campo = $this->contaDeTécnico($empresa, $ana);

        $ordem = $this->ordem($empresa, 'OS-2026-0309', 'Refrigeração do salão', [
            'technician_id' => $ana->id, ...self::ENDERECO,
        ]);

        // O escritório que responde pela escala registra pela ficha da ordem.
        $this->actingAs($supervisora)->post(route('orders.checkin', $ordem), self::PERTO)
            ->assertRedirect(route('orders.show', $ordem))->assertSessionHasNoErrors();
        $visita = ServiceOrderCheckin::anyCompany()->firstWhere('service_order_id', $ordem->id);
        $this->assertSame($ana->id, $visita->technician_id);

        // Quem conduz a execução mas não responde pela escala não registra presença
        // de outra pessoa: a rota aceita, o servidor recusa.
        $desta = $this->ordem($empresa, 'OS-2026-0310', 'Refrigeração do mezanino', [
            'technician_id' => $jonas->id, ...self::ENDERECO,
        ]);
        $this->actingAs($atendente)->post(route('orders.checkin', $desta), self::PERTO)->assertForbidden();
        $this->assertSame(1, ServiceOrderCheckin::anyCompany()->count());

        // A ordem do colega nem aparece na ficha do técnico: alcance antes de tudo.
        $this->actingAs($campo->fresh())->post(route('orders.checkin', $desta), self::PERTO)->assertNotFound();

        // A passagem de outro técnico não se fecha por engano de ficha.
        $doJonas = $this->ordem($empresa, 'OS-2026-0311', 'Refrigeração da câmara B', [
            'technician_id' => $jonas->id, ...self::ENDERECO,
        ]);
        $this->actingAs($admin)->post(route('orders.checkin', $doJonas), self::PERTO)->assertSessionHasNoErrors();
        $visitaJonas = ServiceOrderCheckin::anyCompany()->firstWhere('service_order_id', $doJonas->id);

        $this->actingAs($campo->fresh())->patch(route('checkins.checkout', $visitaJonas), [])->assertNotFound();
        $this->assertNull($visitaJonas->fresh()->checkout_at);

        // Fora do alcance é 404. Dentro do alcance, mas sem ser quem esteve lá nem
        // responder pela escala, é 403: o atendente de escritório tem a execução da
        // rota, e ainda assim não fecha a passagem do técnico.
        $this->actingAs($atendente)->patch(route('checkins.checkout', $visitaJonas), [])->assertForbidden();
        $this->assertNull($visitaJonas->fresh()->checkout_at);

        // A própria passagem, sim.
        $this->actingAs($campo->fresh())->patch(route('checkins.checkout', $visita), [])
            ->assertSessionHasNoErrors();
        $this->assertSame('closed', $visita->fresh()->status);

        // E quem responde pela escala encerra a de qualquer um — a rota aceita o
        // approve, senão uma visita ficaria aberta para sempre no escritório.
        $this->actingAs($supervisora)->patch(route('checkins.checkout', $visitaJonas), [])
            ->assertSessionHasNoErrors();
        $this->assertSame('closed', $visitaJonas->fresh()->status);

        $this->assertSame(2, ServiceOrderCheckin::anyCompany()->count());
    }

    public function test_a_tela_de_visitas_e_a_exportação_respeitam_o_alcance_de_cada_conta(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $jonas = $this->tecnico($empresa, 'Jonas Reis');
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $bica = $this->cliente($empresa, 'Restaurante Bica Quente');
        $campo = $this->contaDeTécnico($empresa, $ana);

        $daAna = $this->ordem($empresa, 'OS-2026-0312', 'Vitrine da padaria', [
            'client_id' => $padaria->id, 'technician_id' => $ana->id, ...self::ENDERECO,
        ]);
        $doJonas = $this->ordem($empresa, 'OS-2026-0313', 'Câmara do restaurante', [
            'client_id' => $bica->id, 'technician_id' => $jonas->id, ...self::ENDERECO,
        ]);

        $this->actingAs($admin)->post(route('orders.checkin', $daAna), self::PERTO)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('orders.checkin', $doJonas), self::LONGE)->assertSessionHasNoErrors();

        // Uma visita de outra empresa existe no banco, e não se alcança por ID na URL.
        $bravo = $this->makeCompany('bravo');
        $la = $this->tecnico($bravo, 'Técnico Bravo');
        $deOutro = $this->ordem($bravo, 'OS-2026-0900', 'Ordem de outra empresa', [
            'technician_id' => $la->id, ...self::ENDERECO,
        ]);
        $visitaAlheia = ServiceOrderCheckin::query()->create([
            'company_id' => $bravo->id,
            'service_order_id' => $deOutro->id,
            'technician_id' => $la->id,
            'checkin_at' => now()->subHour(),
            'status' => 'open',
        ]);

        $this->actingAs($admin)->patch(route('checkins.checkout', $visitaAlheia), [])->assertNotFound();

        $escritorio = $this->actingAs($admin)->get(route('checkins.index'))->assertOk();
        $escritorio->assertSee('OS-2026-0312', false)->assertSee('OS-2026-0313', false);
        $escritorio->assertDontSee('OS-2026-0900', false);
        $escritorio->assertSee('Quem esteve onde, quando e por quanto tempo');
        $escritorio->assertSee('fora do raio de 250,00 m');

        $doTécnico = $this->actingAs($campo->fresh())->get(route('checkins.index'))->assertOk();
        $doTécnico->assertSee('OS-2026-0312', false)->assertDontSee('OS-2026-0313', false);
        $doTécnico->assertSee('A sua presença em campo');
        // O filtro de técnico e o de cliente somem da tela de quem já está filtrado por ficha.
        $doTécnico->assertDontSee('Qualquer técnico da escala');

        // Digitar ?tecnico= do colega na URL não abre a escala alheia.
        $filtrando = $this->actingAs($campo->fresh())
            ->get(route('checkins.index', ['tecnico' => $jonas->id]))->assertOk();
        $filtrando->assertDontSee('OS-2026-0313', false);

        // A conta de cliente lê o que foi medido na carteira dela, pela ordem.
        $cliente = $this->makeUser('client', $empresa, 'cliente@test.local');
        $cliente->update(['client_id' => $padaria->id]);
        $carteira = $this->actingAs($cliente->fresh())->get(route('checkins.index'))->assertOk();
        $carteira->assertSee('OS-2026-0312', false)->assertDontSee('OS-2026-0313', false);

        // A rota de saída é de quem conduz a execução: o cliente nem chega na regra de alcance.
        $this->actingAs($cliente->fresh())
            ->patch(route('checkins.checkout', ServiceOrderCheckin::anyCompany()
                ->firstWhere('service_order_id', $daAna->id)), [])->assertForbidden();

        // Os filtros que o escritório usa de verdade.
        $encerradas = $this->actingAs($admin)->get(route('checkins.index', ['situacao' => 'closed']))->assertOk();
        $encerradas->assertSee('Nenhuma visita com estes filtros');
        $this->assertStringNotContainsString('OS-2026-0312', $encerradas->getContent());

        $porNumero = $this->actingAs($admin)->get(route('checkins.index', ['busca' => '313']))->assertOk();
        $porNumero->assertSee('OS-2026-0313', false)->assertDontSee('OS-2026-0312', false);

        $porNome = $this->actingAs($admin)->get(route('checkins.index', ['busca' => 'Jonas']))->assertOk();
        $porNome->assertSee('OS-2026-0313', false)->assertDontSee('OS-2026-0312', false);

        $semPosicao = $this->actingAs($admin)->get(route('checkins.index', ['sem_posicao' => '1']))->assertOk();
        $this->assertStringNotContainsString('OS-2026-0312', $semPosicao->getContent());

        // O CSV é do módulo de exportação e sai com o mesmo alcance do que a tela lista.
        $csv = $this->actingAs($admin)->get(route('checkins.export'))->assertOk();
        $corpo = $csv->streamedContent();
        $this->assertStringContainsString('Distância do endereço', $corpo);
        $this->assertStringContainsString('OS-2026-0312', $corpo);
        $this->assertStringContainsString('OS-2026-0313', $corpo);
        $this->assertStringNotContainsString('OS-2026-0900', $corpo);
        $this->assertStringContainsString('fora do raio', $corpo);
        $this->assertStringContainsString('dentro do raio', $corpo);

        $this->actingAs($campo->fresh())->get(route('checkins.export'))->assertForbidden();
        $this->actingAs($cliente->fresh())->get(route('checkins.export'))->assertForbidden();

        // A outra empresa tem a própria lista, e nada da primeira aparece nela.
        TenantContext::set($bravo->id);
        $bravoAdmin = $this->makeUser('administrator', $bravo, 'fora@test.local');
        TenantContext::forget();

        $laDela = $this->actingAs($bravoAdmin->fresh())->get(route('checkins.index'))->assertOk();
        $laDela->assertSee('OS-2026-0900', false);
        $this->assertStringNotContainsString('OS-2026-0312', $laDela->getContent());
    }

    public function test_o_raio_que_a_empresa_escolhe_manda_na_marca_e_não_na_recusa(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $ordem = $this->ordem($empresa, 'OS-2026-0320', 'Reparo na cozinha do salão', [
            'technician_id' => $this->tecnico($empresa, 'Ana Beltrão')->id,
            ...self::ENDERECO,
        ]);

        TenantContext::set($empresa->id);
        CompanySetting::put(ServiceOrderCheckin::CHAVE_RAIO, 50);
        $estaCurto = ServiceOrderCheckin::raioAceito();
        TenantContext::forget();
        $this->assertSame(50.0, $estaCurto);

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), self::PERTO)
            ->assertSessionHasNoErrors();

        $visita = ServiceOrderCheckin::anyCompany()->firstWhere('service_order_id', $ordem->id);

        TenantContext::set($empresa->id);
        $this->assertTrue($visita->foraDoRaio(), 'A ~100 m, um raio de 50 m não cabe — e a visita existe mesmo assim.');
        TenantContext::forget();

        $this->actingAs($admin)->get(route('checkins.index'))
            ->assertOk()
            ->assertSee('fora do raio de 50,00 m')
            ->assertSee('OS-2026-0320', false);

        $this->assertSame(1, ServiceOrderCheckin::anyCompany()->count(),
            'Fora do raio se marca, não se apaga: recusar o registro ensinaria a inventar coordenada.');

        // Régua mal escrita na configuração não derruba a tela: volta a da casa.
        TenantContext::set($empresa->id);
        CompanySetting::put(ServiceOrderCheckin::CHAVE_RAIO, 'não-medida');
        $volto = ServiceOrderCheckin::raioAceito();
        TenantContext::forget();
        $this->assertSame(ServiceOrderCheckin::RAIO_PADRAO_METROS, $volto);
    }

    public function test_o_painel_conta_a_presença_no_alcance_de_quem_lê(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $jonas = $this->tecnico($empresa, 'Jonas Reis');

        foreach ([['OS-2026-0330', $ana], ['OS-2026-0331', $ana], ['OS-2026-0332', $jonas]] as [$numero, $tecnico]) {
            $ordem = $this->ordem($empresa, $numero, 'Manutenção '.$numero, [
                'technician_id' => $tecnico->id, ...self::ENDERECO,
            ]);
            $this->actingAs($admin)->post(route('orders.checkin', $ordem), self::PERTO)->assertSessionHasNoErrors();
        }

        $daEmpresa = $this->campoDoPainel($admin);
        $this->assertSame(2, $daEmpresa,
            'Dois técnicos em campo: a Ana tem duas passagens abertas, e a contagem é de gente, não de carimbo.');

        // Supervisor que também está na escala lê o cartão pelo alcance dele.
        $supervisor = $this->makeUser('supervisor', $empresa, 'sup2@test.local');
        $ana->update(['user_id' => $supervisor->id]);

        $this->assertSame(1, $this->campoDoPainel($supervisor->fresh()),
            'Quem tem ficha de técnico conta a própria presença, não a empresa inteira num cartão que parece dele.');

        $primeira = ServiceOrderCheckin::anyCompany()
            ->where('service_order_id', ServiceOrder::anyCompany()->firstWhere('number', 'OS-2026-0330')->id)
            ->first();

        $this->actingAs($supervisor->fresh())->patch(route('checkins.checkout', $primeira), [])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->campoDoPainel($admin),
            'A saída fecha uma passagem da Ana, mas ela continua em campo na outra.');
    }

    public function test_a_ficha_da_ordem_só_oferece_o_formulário_a_quem_pode_registrar(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $ordem = $this->ordem($empresa, 'OS-2026-0340', 'Montagem de balcão', [
            'technician_id' => $ana->id, ...self::ENDERECO,
        ]);

        // A conta de cliente lê a ficha sem a caixa de registro, e diz com honestidade
        // que ainda não há medida nenhuma.
        $cliente = $this->makeUser('client', $empresa, 'le@test.local');
        $cliente->update(['client_id' => $ordem->client_id]);

        $antes = $this->actingAs($cliente->fresh())->get(route('orders.show', $ordem))->assertOk();
        $antes->assertSee('Check-in de campo')
            ->assertSee('Nenhum check-in nesta ordem')
            ->assertDontSee('Ler posição do aparelho')
            ->assertDontSee('data-nf-checkin', false);

        $ficha = $this->actingAs($admin)->get(route('orders.show', $ordem))->assertOk();
        $ficha->assertSee('Check-in de campo')
            ->assertSee('Registrar chegada')
            ->assertSee('Ler posição do aparelho')
            ->assertSee('data-nf-checkin', false)
            ->assertSee('O raio aceito por esta empresa é de 250,00 m');

        $this->assertStringNotContainsString('alert(', $ficha->getContent());

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), self::PERTO)->assertSessionHasNoErrors();

        $depois = $this->actingAs($admin)->get(route('orders.show', $ordem))->assertOk();
        $depois->assertSee('Registrar saída')
            ->assertSee('Em campo desde')
            ->assertSee('Sair do local não encerra a ordem');

        // O carimbo é histórico: a tela lista a passagem, mas não dá campo para reescrevê-lo.
        $this->assertStringNotContainsString('name="checkout_at"', $depois->getContent());
        $this->assertStringNotContainsString('name="checkin_at"', $depois->getContent());

        $leitura = $this->actingAs($cliente->fresh())->get(route('orders.show', $ordem))->assertOk();
        $leitura->assertSee('entrada')
            ->assertSee('m do endereço')
            ->assertSee('Em campo')
            ->assertDontSee('Registrar chegada')
            ->assertDontSee('data-nf-checkin', false);
    }

    public function test_a_tela_não_grava_coordenada_fora_do_mundo_nem_relato_imenso(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $ordem = $this->ordem($empresa, 'OS-2026-0350', 'Reparo elétrico', [
            'technician_id' => $this->tecnico($empresa, 'Ana Beltrão')->id,
            ...self::ENDERECO,
        ]);

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), [
            'latitude' => '100.0', 'longitude' => '0',
        ])->assertSessionHasErrors('latitude');

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), [
            'latitude' => '0', 'longitude' => '-200.0',
        ])->assertSessionHasErrors('longitude');

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), [
            'latitude' => '-23.56123401234', 'longitude' => '-46.6551',
        ])->assertSessionHasErrors('latitude');

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), [
            'latitude' => 'abc', 'longitude' => '0',
        ])->assertSessionHasErrors('latitude');

        $this->actingAs($admin)->post(route('orders.checkin', $ordem), [
            'observacao' => str_repeat('a', 501),
        ])->assertSessionHasErrors('observacao');

        $this->assertSame(0, ServiceOrderCheckin::anyCompany()->count(),
            'Recusa no servidor não cria visita.');

        $this->assertSame('open', $ordem->fresh()->status, 'A ordem não se moveu com uma chegada que nunca existiu.');
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    private function contaDeTécnico(Company $empresa, Technician $tecnico): User
    {
        TenantContext::set($empresa->id);
        $usuario = $this->makeUser('technician', $empresa, 'campo-'.$tecnico->id.'@test.local');
        TenantContext::forget();

        $tecnico->update(['user_id' => $usuario->id]);

        return $usuario->fresh();
    }

    /** @param  array<string, mixed>  $extras */
    private function ordem(Company $empresa, string $numero, string $titulo, array $extras = []): ServiceOrder
    {
        // Só se cria o cliente padrão quando a ficha não escolheu um: criar sempre
        // encheria o filtro de cliente da tela com nomes que nenhum teste pediu.
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

    /**
     * O cartão "Técnicos em campo agora" do painel, lido pelo rótulo: os KPIs chegam
     * ao Blade como uma lista única, e a posição deles depende de quais blocos a
     * permissão do usuário montou.
     */
    private function campoDoPainel(User $usuario): int
    {
        $kpis = (new DashboardMetrics($usuario))->toArray()['kpis'];
        $cartao = collect($kpis)->firstWhere('label', 'Técnicos em campo agora');

        $this->assertNotNull($cartao, 'O painel tem de responder quantos técnicos estão em campo.');

        return (int) $cartao['value'];
    }

    private function descriçãoDaAuditoria(string $acao): string
    {
        return (string) DB::table('audit_logs')->where('action', $acao)->latest('id')->value('description');
    }
}
