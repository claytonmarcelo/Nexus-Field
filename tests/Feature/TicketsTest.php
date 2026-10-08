<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\ServiceCategory;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 13 é a voz do cliente: o chamado nasce com protocolo da sequência da empresa,
 * carrega o relato em texto rico limpo no servidor, corre o prazo da própria
 * prioridade e só muda de estado pelo caminho que grava quem fez, quando e por quê.
 * A conversa tem duas margens — o que o cliente lê e o que o escritório anotou para
 * si — e a margem interna não chega na tela de quem não pode editar chamado.
 */
class TicketsTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_listagem_traz_so_os_chamados_da_empresa_aberta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $this->chamado($empresa, $this->cliente($empresa, 'Padaria Sant’Anna'), [
            'protocol' => 'CH-2026-0001',
            'subject' => 'Câmara fria apitando alarme de porta',
        ]);
        $alheia = $this->chamado($outra, $this->cliente($outra, 'Cliente de outra empresa'), [
            'protocol' => 'CH-2026-9001',
            'subject' => 'Chamado que não é desta empresa',
        ]);

        $lista = $this->actingAs($usuario)->get(route('tickets.index'));

        $lista->assertOk()
            ->assertSee('CH-2026-0001')
            ->assertSee('Câmara fria apitando alarme de porta')
            ->assertDontSee('CH-2026-9001')
            ->assertSee('1 chamado encontrado');

        $this->actingAs($usuario)->get(route('tickets.show', $alheia))->assertNotFound();
        $this->actingAs($usuario)->get(route('tickets.edit', $alheia))->assertNotFound();
        $this->actingAs($usuario)->put(route('tickets.status', $alheia), ['estado' => 'in_progress'])->assertNotFound();
        $this->actingAs($usuario)->post(route('tickets.comments.store', $alheia), ['nota' => 'Nota que não entra'])->assertNotFound();

        $this->assertSame('open', $alheia->fresh()->status);
        $this->assertSame(0, $alheia->comments()->count(), 'A nota postada fora do alcance não chegou à ficha alheia.');
    }

    public function test_o_tecnico_ve_os_dele_e_a_conta_de_cliente_ve_a_carteira_dele(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $gelateria = $this->cliente($empresa, 'Gelato Bella Manteiga');

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');

        $dele = $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0001', 'technician_id' => $ricardo->id]);
        $responsavel = $this->chamado($empresa, $gelateria, ['protocol' => 'CH-2026-0002']);
        $esta = $this->chamado($empresa, $gelateria, ['protocol' => 'CH-2026-0003', 'technician_id' => $ana->id]);

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        $responsavel->update(['responsible_user_id' => $campo->id]);
        $ricardo->update(['user_id' => $campo->id]);

        $contaCliente = $this->makeUser('client', $empresa, 'cliente@test.local');
        $contaCliente->update(['client_id' => $padaria->id]);
        TenantContext::forget();

        $vida = $this->actingAs($campo->fresh())->get(route('tickets.index'));
        $vida->assertOk()->assertSee('CH-2026-0001')->assertSee('CH-2026-0002')->assertDontSee('CH-2026-0003');
        $vida->assertSee('Os chamados que são seus aparecem aqui');

        // Digitar ?cliente= na URL não abre a carteira de outro: o alcance entra antes do filtro.
        $this->actingAs($campo->fresh())->get(route('tickets.index', ['cliente' => $gelateria->id]))
            ->assertOk()->assertDontSee('CH-2026-0003');

        $this->actingAs($campo->fresh())->get(route('tickets.show', $esta))->assertNotFound();

        $carteira = $this->actingAs($contaCliente->fresh())->get(route('tickets.index'));
        $carteira->assertOk()->assertSee('CH-2026-0001')->assertDontSee('CH-2026-0002')->assertDontSee('CH-2026-0003');
        $this->actingAs($contaCliente->fresh())->get(route('tickets.show', $dele))->assertOk();

        $this->assertSame(3, Ticket::anyCompany()->count(), 'Ler não escreve: o escritório continua com os três.');

        // E o escritório, que alcança os três, vê os três na mesma régua de contagem.
        $this->actingAs($usuario)->get(route('tickets.index'))
            ->assertOk()->assertSee('3 chamados encontrados');
    }

    public function test_o_protocolo_nace_da_sequencia_do_ano_dentro_da_empresa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $this->chamado($outra, $this->cliente($outra, 'Cliente de outra empresa'), ['protocol' => 'CH-2026-0001']);

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0007']);

        $payload = ['client_id' => $padaria->id, 'subject' => 'Forno combinado sem atingir a temperatura', 'priority' => 'high'];

        $primeiro = $this->actingAs($usuario)->post(route('tickets.store'), $payload + [
            'category' => 'refrigeracao',
            'description' => '<p>A 200 °C não segura no second batch.</p>',
        ]);
        $primeiro->assertRedirect()->assertSessionHasNoErrors();

        $novo = Ticket::anyCompany()->firstWhere('subject', 'Forno combinado sem atingir a temperatura');
        $this->assertNotNull($novo);
        $this->assertSame('CH-'.now()->format('Y').'-0008', $novo->protocol,
            'A sequência é por empresa: o que a outra empresa tinha no ano não conta, e o maior protocolo desta (0007) é o que empurra.');
        $this->assertSame('open', $novo->status, 'O chamado nasce aberto: ninguém escolhe o estado de entrada.');
        $this->assertNotNull($novo->opened_at);
        $this->assertSame(1, $novo->statusHistory()->count(), 'Abrir já fica registrado com quem abriu.');
    }

    public function test_o_relato_e_limpo_no_servidor_e_assim_que_a_ficha_mostra(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');

        $resposta = $this->actingAs($usuario)->post(route('tickets.store'), [
            'client_id' => $padaria->id,
            'category' => 'refrigeracao',
            'priority' => 'normal',
            'subject' => 'Vitrine com vidro embaçado no self-service',
            'description' => '<p>Vidro embaçado desde <strong>ontem</strong>.</p>'
                .'<script>alert(1)</script>'
                .'<a href="javascript:alert(2)">clique</a>'
                .'<a href="https://fornecedor.example/pecas" target="_blank">peças</a>'
                .'<img src="data:image/svg+xml,onerror=alert(3)">'
                .'<p onclick="alert(4)">com estilo e evento</p>',
        ]);

        $resposta->assertSessionHasNoErrors();
        $chamado = Ticket::anyCompany()->firstWhere('subject', 'Vitrine com vidro embaçado no self-service');
        $this->assertNotNull($chamado);

        $guardado = $chamado->description;
        $this->assertStringNotContainsString('script', $guardado);
        $this->assertStringNotContainsString('javascript:', $guardado);
        $this->assertStringNotContainsString('onclick', $guardado);
        $this->assertStringNotContainsString('<img', $guardado);
        $this->assertStringContainsString('<strong>ontem</strong>', $guardado);
        $this->assertStringContainsString('https://fornecedor.example/pecas', $guardado);
        $this->assertStringContainsString('rel="noopener nofollow"', $guardado);

        $ficha = $this->actingAs($usuario)->get(route('tickets.show', $chamado));
        $ficha->assertOk()->assertSee('vidro embaçado', false);

        // A página tem script próprio (Vite, Summernote), então o que prova a limpeza não
        // é a tag: é a carga útil, que não pode chegar ao HTML em nenhuma forma.
        foreach (['alert(1)', 'alert(2)', 'alert(3)', 'alert(4)', 'javascript:', 'onerror', 'onclick'] as $carga) {
            $this->assertStringNotContainsString($carga, $ficha->getContent());
        }

        // Nota sem palavra — só formatação vazia — não é resposta para quem espera.
        $this->actingAs($usuario)->post(route('tickets.comments.store', $chamado), ['nota' => '<p><br></p>'])
            ->assertSessionHasErrors('nota');
        $this->assertSame(0, $chamado->comments()->count());
    }

    public function test_a_nota_interna_nao_chega_na_tela_de_quem_nao_pode_editar(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');

        $chamado = $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0001']);

        TenantContext::set($empresa->id);
        $contaCliente = $this->makeUser('client', $empresa, 'cliente@test.local');
        $contaCliente->update(['client_id' => $padaria->id]);
        $this->tecnico($empresa, 'Ricardo Prado');
        TenantContext::forget();

        $this->actingAs($usuario)->post(route('tickets.comments.store', $chamado), [
            'nota' => 'Peça separada no almoxarifado; não prometer data ao cliente.',
            'interna' => '1',
        ])->assertSessionHasNoErrors();

        // Quem não tem a permissão de editar não consegue marcar nota interna, mesmo enviando o campo.
        $this->actingAs($contaCliente->fresh())->post(route('tickets.comments.store', $chamado), [
            'nota' => 'O equipamento voltou a falhar no segundo turno.',
            'interna' => '1',
        ])->assertSessionHasNoErrors();

        $interna = TicketComment::query()->firstWhere('is_internal', true);
        $this->assertNotNull($interna);
        $this->assertSame($usuario->id, $interna->user_id);

        $esta = $this->actingAs($contaCliente->fresh())->get(route('tickets.show', $chamado));
        $esta->assertOk()
            ->assertSee('segundo turno')
            ->assertDontSee('Peça separada no almoxarifado')
            ->assertDontSee('Nota interna');

        $doEscritorio = $this->actingAs($usuario)->get(route('tickets.show', $chamado));
        $doEscritorio->assertOk()->assertSee('Peça separada no almoxarifado')->assertSee('interna');
    }

    public function test_o_estado_muda_pelo_fluxo_com_nota_e_passagem_registrada(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        $gestor = $this->makeUser('supervisor', $empresa, 'gestor@test.local');

        $chamado = $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0001', 'priority' => 'normal']);

        // Atender é do dia a dia: o gestor move sem precisar da permissão de encerramento.
        $this->actingAs($gestor)->put(route('tickets.status', $chamado), ['estado' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $chamado->fresh()->status);

        // Salto que o fluxo não permite: de “Em atendimento” para “Aberto” não existe.
        $this->actingAs($gestor)->put(route('tickets.status', $chamado), ['estado' => 'open'])
            ->assertSessionHas('erro');
        $this->assertSame('in_progress', $chamado->fresh()->status);

        // Resolver sem dizer o que foi feito não resolve.
        $this->actingAs($gestor)->put(route('tickets.status', $chamado), ['estado' => 'resolved'])
            ->assertSessionHasErrors('nota');
        $this->assertSame('in_progress', $chamado->fresh()->status);

        $this->actingAs($gestor)->put(route('tickets.status', $chamado), [
            'estado' => 'resolved',
            'nota' => 'Vidro trocado e regulagem de defrotest feita; cliente testou na hora.',
        ])->assertSessionHasNoErrors();

        $resolvido = $chamado->fresh();
        $this->assertSame('resolved', $resolvido->status);
        $this->assertNotNull($resolvido->resolved_at);
        $this->assertSame('Vidro trocado e regulagem de defrotest feita; cliente testou na hora.', $resolvido->resolution_note);

        // Reabrir apaga o carimbo de resolução: o prazo volta a correr.
        $this->actingAs($gestor)->put(route('tickets.status', $resolvido), ['estado' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $este = $resolvido->fresh();
        $this->assertSame('in_progress', $este->status);
        $this->assertNull($este->resolved_at);

        $este->mudarStatus('resolved', $gestor, 'Segunda visita: resistência substituída.');
        $este->mudarStatus('closed', $gestor, 'Cliente confirmou e o protocolo foi encerrado.');

        $fechado = $este->fresh();
        $this->assertSame('closed', $fechado->status);
        $this->assertNotNull($fechado->closed_at);
        $this->assertFalse($fechado->podeMudarPara('in_progress'), 'Fechado é o único terminal do fluxo.');

        $trilha = TicketStatusHistory::query()->where('ticket_id', $fechado->id)->orderBy('id')->pluck('to_status')->all();
        $this->assertSame(['open', 'in_progress', 'resolved', 'in_progress', 'resolved', 'closed'], $trilha);

        // Fechado, a conversa parou: responder pede outro protocolo.
        $this->actingAs($gestor)->post(route('tickets.comments.store', $fechado), ['nota' => 'Apareceu outra coisa.'])
            ->assertSessionHas('erro');
        $this->assertSame(0, $fechado->comments()->count());
    }

    public function test_resolver_e_fechar_pedem_a_permissao_de_encerramento(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');

        TenantContext::set($empresa->id);
        $ficha = $this->tecnico($empresa, 'Ricardo Prado');
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        $ficha->update(['user_id' => $campo->id]);
        TenantContext::forget();

        // O chamado é do técnico: sem a ficha apontada nele, o alcance nem o veria, e a
        // prova de que a tela dele não oferece encerramento seria sobre um 404.
        $chamado = $this->chamado($empresa, $padaria, [
            'protocol' => 'CH-2026-0001',
            'technician_id' => $ficha->id,
            'priority' => 'urgent',
            'opened_at' => now()->subHours(10),
        ]);

        $este = $chamado->fresh();
        $vida = $this->actingAs($campo->fresh());

        // O técnico move o passo do dia a dia, e a tela dele não oferece o encerramento.
        // `assertRedirect` antes do session: sem ele, um 404 passaria por "sem erros".
        $vida->put(route('tickets.status', $este), ['estado' => 'in_progress'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $fichaDestec = $this->actingAs($campo->fresh())->get(route('tickets.show', $este->fresh()));
        $fichaDestec->assertOk()->assertSee('Em atendimento');
        $this->assertStringNotContainsString('Resolvido', $fichaDestec->getContent());

        $este = $este->fresh();
        $this->assertSame('in_progress', $este->status);

        // Forjar a URL não passa: o servidor conhece o mesmo fluxo que a tela.
        $este->mudarStatus('waiting', $campo->fresh());
        $este = $este->fresh();
        $this->actingAs($campo->fresh())->put(route('tickets.status', $este), ['estado' => 'resolved', 'nota' => 'Pronto, pode conferir.'])
            ->assertSessionHas('erro');
        $this->assertSame('waiting', $este->fresh()->status, 'Resolver ficou recusado e o estado não mudou.');

        // O administrador, que tem encerramento, resolve a mesma linha.
        $this->actingAs($usuario)->put(route('tickets.status', $este->fresh()), ['estado' => 'resolved', 'nota' => 'Resistor trocado e teste feito.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('resolved', $este->fresh()->status);
    }

    public function test_o_prazo_da_prioridade_vence_na_listagem_no_filtro_e_no_csv(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');

        $vencido = $this->chamado($empresa, $padaria, [
            'protocol' => 'CH-2026-0001',
            'priority' => 'urgent',
            'subject' => 'Máquina de espresso perdendo pressão',
            'opened_at' => now()->subHours(6),
        ]);
        $noPrazo = $this->chamado($empresa, $padaria, [
            'protocol' => 'CH-2026-0002',
            'priority' => 'low',
            'subject' => 'Estufa exibindo com lâmpada queimada',
            'opened_at' => now()->subHours(6),
        ]);
        $resolvidoHáTempo = $this->chamado($empresa, $padaria, [
            'protocol' => 'CH-2026-0003',
            'priority' => 'urgent',
            'subject' => 'Soveladeira parando no meio do sova',
            'opened_at' => now()->subDays(3),
            'status' => 'resolved',
            'resolved_at' => now()->subDays(2),
        ]);

        $this->assertTrue($vencido->prazoVencido());
        $this->assertFalse($noPrazo->prazoVencido());
        $this->assertFalse($resolvidoHáTempo->prazoVencido(), 'O prazo para de correr quando o chamado foi resolvido.');

        $lista = $this->actingAs($usuario)->get(route('tickets.index'));
        $lista->assertOk()->assertSee('prazo vencido');

        $soOsVencidos = $this->actingAs($usuario)->get(route('tickets.index', ['atrasado' => '1']));
        $soOsVencidos->assertOk()->assertSee('Máquina de espresso perdendo pressão')
            ->assertDontSee('Estufa exibindo com lâmpada queimada')
            ->assertDontSee('Soveladeira parando no meio do sova')
            ->assertSee('1 chamado encontrado');

        $csv = $this->actingAs($usuario)->get(route('tickets.export', ['atrasado' => '1']));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $conteudo = $csv->streamedContent();
        $linhas = array_filter(explode("\n", trim($conteudo)));

        $this->assertStringContainsString('Protocolo;Assunto;Cliente', $conteudo);
        $this->assertCount(2, $linhas, 'Um chamado vencido mais o cabeçalho: o CSV é a mesma consulta da tela.');
        $this->assertStringContainsString('CH-2026-0001', $conteudo);
        $this->assertStringNotContainsString('CH-2026-0002', $conteudo);
        $this->assertStringContainsString('sim', $conteudo);
        $this->assertStringNotContainsString('Lâmpada', $conteudo);

        $todos = $this->actingAs($usuario)->get(route('tickets.export'));
        $todos->assertOk();
        $this->assertCount(4, array_filter(explode("\n", trim($todos->streamedContent()))));
    }

    public function test_o_painel_do_tecnico_nao_conta_os_chamados_dos_outros(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');

        $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0001', 'technician_id' => $ricardo->id]);
        $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0002', 'technician_id' => $ana->id]);
        $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0003', 'technician_id' => $ana->id, 'status' => 'closed', 'closed_at' => now()]);

        TenantContext::set($empresa->id);
        $campo = $this->makeUser('technician', $empresa, 'campo@test.local');
        $ricardo->update(['user_id' => $campo->id]);
        $painel = (new DashboardMetrics($campo->fresh()))->toArray();
        $doEscritorio = (new DashboardMetrics($usuario))->toArray();
        TenantContext::forget();

        $this->assertSame(1, $this->kpi($painel, 'Chamados abertos'),
            'O KPI responde pela fila de quem está no campo, não pela operação inteira.');
        $this->assertSame(2, $this->kpi($doEscritorio, 'Chamados abertos'));
        $this->assertCount(1, $painel['chamados']['fila']);
    }

    public function test_o_servidor_recusa_quem_nao_tem_a_permissao_do_modulo(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $this->categoria($empresa);

        $contaCliente = $this->makeUser('client', $empresa, 'cliente@test.local');
        $tecnico = $this->makeUser('technician', $empresa, 'campo@test.local');
        $gestor = $this->makeUser('supervisor', $empresa, 'gestor@test.local');

        $padaria = $this->cliente($empresa, 'Padaria Sant’Anna');
        TenantContext::set($empresa->id);
        $contaCliente->update(['client_id' => $padaria->id]);
        $ficha = $this->tecnico($empresa, 'Ricardo Prado', ['user_id' => $tecnico->id]);
        TenantContext::forget();

        $chamado = $this->chamado($empresa, $padaria, ['protocol' => 'CH-2026-0001', 'technician_id' => $ficha->id]);
        $payload = ['category' => 'refrigeracao', 'subject' => 'Chamado que não vai nascer', 'priority' => 'normal'];

        // A conta de cliente lê a carteira dela, abre chamado seu e não edita nada.
        $this->actingAs($contaCliente)->get(route('tickets.index'))->assertOk();
        $this->actingAs($contaCliente)->get(route('tickets.show', $chamado))->assertOk();
        $this->actingAs($contaCliente)->get(route('tickets.create'))->assertOk();
        $this->actingAs($contaCliente)->post(route('tickets.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($contaCliente)->get(route('tickets.edit', $chamado))->assertForbidden();
        $this->actingAs($contaCliente)->put(route('tickets.update', $chamado), $payload)->assertForbidden();
        $this->actingAs($contaCliente)->put(route('tickets.status', $chamado), ['estado' => 'in_progress'])->assertForbidden();
        $this->assertSame('open', $chamado->fresh()->status,
            'Conduzir o estado é de quem tem `tickets.execute`: a conta de cliente lê e responde.');
        $this->actingAs($contaCliente)->get(route('tickets.export'))->assertForbidden();

        $novo = Ticket::anyCompany()->firstWhere('subject', 'Chamado que não vai nascer');
        $this->assertNotNull($novo);
        $this->assertSame($padaria->id, $novo->client_id, 'A carteira veio da sessão, não do formulário.');

        // O técnico vê os dele, abre chamado e não apaga nada — o catálogo de permissões não tem delete.
        $this->actingAs($tecnico)->get(route('tickets.create'))->assertOk();
        $this->actingAs($tecnico)->put(route('tickets.update', $chamado), $payload + ['client_id' => $padaria->id])->assertForbidden();
        $this->actingAs($tecnico)->delete(route('tickets.show', $chamado))->assertMethodNotAllowed();

        // O gestor edita, encerra e exporta; apagar chamado não existe no módulo.
        $this->actingAs($gestor)->get(route('tickets.edit', $chamado))->assertOk();
        $this->actingAs($gestor)->put(route('tickets.update', $chamado), [
            'client_id' => $padaria->id,
            'category' => 'refrigeracao',
            'subject' => 'Câmara fria apitando alarme de porta',
            'priority' => 'high',
        ])->assertSessionHasNoErrors();
        $this->assertSame('high', $chamado->fresh()->priority);
        $this->assertSame('refrigeracao', $chamado->fresh()->category);
        $this->actingAs($gestor)->get(route('tickets.export'))->assertOk();

        $this->assertFalse(app('router')->has('tickets.destroy'), 'Apagar chamado não tem rota nem no módulo, nem por fora dele.');
        $this->assertSame(2, Ticket::anyCompany()->count(), 'Recusa no servidor não cria chamado.');
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $this->categoria($empresa);

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /**
     * Categoria de serviço da empresa: o chamado nasce de uma categoria do catálogo,
     * e o servidor recusa payload com categoria que não existe — inclusive o da conta
     * de cliente, que não escolhe a categoria na tela.
     */
    private function categoria(Company $empresa, string $slug = 'refrigeracao'): ServiceCategory
    {
        TenantContext::set($empresa->id);
        $categoria = ServiceCategory::query()->create(['name' => 'Refrigeração', 'slug' => $slug]);
        TenantContext::forget();

        return $categoria;
    }

    /** @param  array<string, mixed>  $extras */
    private function chamado(Company $empresa, Client $cliente, array $extras = []): Ticket
    {
        TenantContext::set($empresa->id);

        $chamado = Ticket::query()->create($extras + [
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'protocol' => 'CH-'.now()->format('Y').'-'.str_pad((string) (Ticket::anyCompany()->count() + 1), 4, '0', STR_PAD_LEFT),
            'subject' => 'Câmara fria apitando alarme de porta',
            'description' => '<p>Relato registrado pelo cliente.</p>',
            'category' => 'refrigeracao',
            'priority' => 'normal',
            'status' => 'open',
            'opened_at' => now()->subHour(),
        ]);

        TicketStatusHistory::query()->create([
            'ticket_id' => $chamado->id,
            'user_id' => $chamado->company->users()->orderBy('id')->first()?->id,
            'from_status' => null,
            'to_status' => $chamado->status,
            'note' => 'Chamado aberto na tela de chamados.',
            'created_at' => $chamado->opened_at,
        ]);

        TenantContext::forget();

        return $chamado;
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

    /** @param  array<string, mixed>  $painel */
    private function kpi(array $painel, string $rotulo): int
    {
        foreach ($painel['kpis'] as $kpi) {
            if ($kpi['label'] === $rotulo) {
                return (int) $kpi['value'];
            }
        }

        $this->fail("O painel não entregou o KPI '{$rotulo}'.");
    }
}
