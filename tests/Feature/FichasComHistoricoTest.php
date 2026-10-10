<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Ficha não é formulário: é o que o banco já sabe sobre aquele cadastro. Estes
 * testes abrem a ficha do cliente e a do técnico e conferem as três promessas
 * desta etapa — cada cartão traz o registro verdadeiro (a ordem com o número
 * certo, o serviço somado das linhas da própria ordem, a janela que ainda vem, a
 * trilha da própria linha), o alcance de quem abre a tela entra antes da
 * contagem, e nenhum número mora em dois cartões diferentes.
 */
class FichasComHistoricoTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_ficha_do_cliente_traz_as_ordens_que_o_banco_aponta_para_ele(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Central');
        $vizinho = $this->cliente($empresa, 'Mercado da Esquina');

        $this->ordem($empresa, 'OS-2026-0001', [
            'client_id' => $cliente->id,
            'title' => 'Instalação de câmera na área fria',
            'scheduled_starts_at' => now()->subDays(6),
        ]);
        $this->ordem($empresa, 'OS-2026-0002', [
            'client_id' => $cliente->id,
            'title' => 'Manutenção da vitrine',
            'scheduled_starts_at' => now()->subDay(),
        ]);
        $this->ordem($empresa, 'OS-2026-0003', ['client_id' => $vizinho->id]);

        $this->actingAs($administrador)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('OS-2026-0001')
            ->assertSee('OS-2026-0002')
            ->assertSee('Instalação de câmera na área fria')
            // A ordem do vizinho é do vizinho: a ficha não dá volta na carteira.
            ->assertDontSee('OS-2026-0003')
            // A contagem é a do banco, não o tanto de linhas que coube na tela.
            ->assertSee('2 ordens registradas — as mais recentes primeiro.');
    }

    public function test_a_ficha_soma_os_servicos_que_saem_das_linhas_das_ordens_dele(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Central');
        $vitrine = $this->servico($empresa, 'Refrigeração de vitrine', 350);
        $camara = $this->servico($empresa, 'Câmera e gravação', 180);

        $primeira = $this->ordem($empresa, 'OS-2026-0010', ['client_id' => $cliente->id]);
        $segunda = $this->ordem($empresa, 'OS-2026-0011', ['client_id' => $cliente->id]);

        $this->linhaDaOrdem($primeira, $vitrine, 2);
        $this->linhaDaOrdem($segunda, $vitrine, 1);
        $this->linhaDaOrdem($segunda, $camara, 3);

        $this->actingAs($administrador)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('Refrigeração de vitrine')
            ->assertSee('Câmera e gravação')
            // Duas linhas de vitrine em ordens diferentes viram uma só linha na ficha:
            // 3 × 350 = 1.050 e 3 × 180 = 540, somados no MySQL pela própria tela.
            ->assertSee('R$ 1.050,00')
            ->assertSee('R$ 540,00');
    }

    public function test_a_agenda_da_ficha_mostra_a_janela_que_ainda_vem_e_a_ultima_que_passou(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Central');

        $this->compromisso($empresa, 'Visita de instalação', Carbon::now()->addDay()->setTime(9, 0), [
            'client_id' => $cliente->id,
        ]);
        $this->compromisso($empresa, 'Vistoria de garantia', Carbon::now()->subDays(5)->setTime(14, 0), [
            'client_id' => $cliente->id,
            'status' => 'completed',
        ]);

        $this->actingAs($administrador)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('Visita de instalação')
            ->assertSee('Agendado')
            ->assertSee('Última janela no nome dele')
            ->assertSee('Concluído')
            ->assertSee('2 janelas reservadas — as que ainda vêm estão aqui.');
    }

    public function test_quem_so_tem_janela_passada_recebe_o_aviso_em_vezo_de_agenda_em_branco(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Central');
        $this->compromisso($empresa, 'Visita que já foi', Carbon::now()->subDays(3)->setTime(10, 0), [
            'client_id' => $cliente->id,
            'status' => 'completed',
        ]);

        $this->actingAs($administrador)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('Nenhuma janela por vir')
            // A única janela que existe já foi: ela aparece como a última, com o
            // estado real, e não como promessa.
            ->assertSee('Última janela no nome dele')
            ->assertSee('Concluído')
            // Total registrado existe, então o estado vazio de "nenhuma reserva" não
            // é o que a ficha mostra: ela mostra o que passou, não uma tela limpa.
            ->assertDontSee('Nenhuma janela reservada');
    }

    public function test_a_ficha_mostra_as_seis_ordens_mais_recentes_e_continua_contando_o_total(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Central');
        for ($dia = 1; $dia <= 8; $dia++) {
            $this->ordem($empresa, 'OS-2026-010'.$dia, [
                'client_id' => $cliente->id,
                'scheduled_starts_at' => now()->subDays($dia),
            ]);
        }

        // Oito no banco, seis na tela: o cartão é amostra do que a lista inteira já
        // tem, e o número dele continua sendo oito — o link "Ver todas" é que fecha.
        $this->actingAs($administrador)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('8 ordens registradas')
            ->assertSee('OS-2026-0101')
            ->assertSee('OS-2026-0106')
            ->assertDontSee('OS-2026-0107')
            ->assertDontSee('OS-2026-0108');
    }

    public function test_a_trilha_da_ficha_conta_os_atos_desta_linha_e_nao_das_vizinhas(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();

        $cliente = $this->cliente($empresa, 'Padaria Central');
        $vizinho = $this->cliente($empresa, 'Mercado da Esquina');

        $this->actingAs($administrador)->put(route('clients.update', $cliente), [
            'name' => 'Padaria Central',
            'status' => 'active',
            'notes' => 'Entrada pelo portão lateral.',
        ])->assertRedirect(route('clients.show', $cliente));

        $this->put(route('clients.update', $vizinho), [
            'name' => 'Mercado da Esquina',
            'status' => 'active',
        ])->assertRedirect();

        $this->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('Trilha desta ficha')
            ->assertSee('atualizado')
            ->assertSee('Alteração de Client "Padaria Central"')
            // A mesma régua do auditor, agora por linha: a trilha do vizinho é do vizinho.
            ->assertDontSee('Alteração de Client "Mercado da Esquina"');
    }

    public function test_o_caixa_e_a_trilha_so_abrem_para_quem_tem_acesso(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();
        [$contaDeCampo, $fichaDeCampo] = $this->contaDeCampo($empresa);

        $cliente = $this->cliente($empresa, 'Padaria Central');
        $this->lancamento($empresa, $cliente, 250, Carbon::today()->subDays(3));
        $this->ordem($empresa, 'OS-2026-0200', [
            'client_id' => $cliente->id,
            'technician_id' => $fichaDeCampo->id,
        ]);

        // O técnico tem direito à ficha do cliente que ele atende — e não tem
        // financial.view nem audit.view: os dois cartões simplesmente não nascem.
        $this->actingAs($contaDeCampo)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('OS-2026-0200')
            ->assertDontSee('Financeiro deste cliente')
            ->assertDontSee('Trilha desta ficha');

        $this->actingAs($administrador)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('Financeiro deste cliente')
            ->assertSee('R$ 250,00')
            ->assertSee('1 conta atrasada')
            ->assertSee('Trilha desta ficha');
    }

    public function test_a_ficha_do_cliente_so_revela_o_que_quem_abre_pode_ver(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();
        [$contaDeCampo, $fichaDeCampo] = $this->contaDeCampo($empresa);
        $outro = $this->tecnico($empresa, 'Técnico de outra rota');

        $cliente = $this->cliente($empresa, 'Padaria Central');
        $limpeza = $this->servico($empresa, 'Limpeza de condensadora', 120);

        $this->ordem($empresa, 'OS-2026-0300', [
            'client_id' => $cliente->id,
            'technician_id' => $fichaDeCampo->id,
            'scheduled_starts_at' => now()->subDay(),
        ]);
        $deOutro = $this->ordem($empresa, 'OS-2026-0301', [
            'client_id' => $cliente->id,
            'technician_id' => $outro->id,
            'service_id' => $limpeza->id,
            'scheduled_starts_at' => now(),
        ]);
        $this->linhaDaOrdem($deOutro, $limpeza, 1);

        $this->actingAs($contaDeCampo)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('OS-2026-0300')
            ->assertDontSee('OS-2026-0301')
            // O cartão de serviços nasce das ordens visíveis: sem a ordem do outro
            // técnico na conta dele, o serviço que ela cobra também não aparece aqui.
            ->assertDontSee('Limpeza de condensadora')
            ->assertSee('Uma ordem no banco — ela está aqui.');

        // O mesmo cadastro, aberto por quem vê a carteira inteira, mostra as duas.
        $this->actingAs($administrador)->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('OS-2026-0300')
            ->assertSee('OS-2026-0301')
            ->assertSee('Limpeza de condensadora');
    }

    public function test_a_ficha_do_tecnico_traz_o_trabalho_dele_e_nao_o_dos_outros(): void
    {
        [$empresa, $administrador] = $this->empresaComAdmin();
        $supervisor = $this->makeUser('supervisor', $empresa, 'supervisor@test.local');

        $dele = $this->tecnico($empresa, 'Técnico da Rota Norte');
        $outro = $this->tecnico($empresa, 'Técnico da Rota Sul');
        $cliente = $this->cliente($empresa, 'Padaria Central');

        $this->ordem($empresa, 'OS-2026-0400', [
            'client_id' => $cliente->id,
            'technician_id' => $dele->id,
            'title' => 'Instalação na padaria',
            'scheduled_starts_at' => now()->subDays(2),
        ]);
        $this->ordem($empresa, 'OS-2026-0401', [
            'client_id' => $cliente->id,
            'technician_id' => $outro->id,
        ]);
        $this->chamado($empresa, 'Vitrine sem pressão na frente da loja', ['technician_id' => $dele->id]);
        $this->compromisso($empresa, 'Retorno da câmara fria', Carbon::now()->addDay()->setTime(15, 0), [
            'client_id' => $cliente->id,
            'technician_id' => $dele->id,
        ]);

        // Cada cartão puxa a linha do próprio domínio pelo `technician_id`, então a
        // ficha de um técnico não é janela para a escala do colega.
        $this->actingAs($supervisor)->get(route('technicians.show', $dele))
            ->assertOk()
            ->assertSee('Ordens com ele')
            ->assertSee('OS-2026-0400')
            ->assertSee('Instalação na padaria')
            ->assertDontSee('OS-2026-0401')
            ->assertSee('Vitrine sem pressão na frente da loja')
            ->assertSee('Retorno da câmara fria')
            ->assertSee('Uma ordem no nome dele — ela está aqui.')
            ->assertSee('Um chamado no nome dele — ele está aqui.')
            ->assertSee('1 janela reservada — as que ainda vêm estão aqui.');

        // A trilha é cartão de quem tem audit.view: supervisor não tem, administrador tem.
        $this->actingAs($administrador)->get(route('technicians.show', $dele))
            ->assertOk()
            ->assertSee('Trilha desta ficha');
    }

    public function test_a_ficha_nao_volta_a_ser_um_painel_de_contagens_soltas(): void
    {
        // O cartão que só dizia "3 ordens, 2 chamados" saiu de cena: cada número agora
        // mora no cartão do domínio que o possui. Se alguém reinventar a régua em um
        // controller à parte, este teste avisa antes de a tela nascer duplicada.
        $cartoesRetirados = [
            'clients' => 'O que este cliente já gerou',
            'technicians' => 'O que este técnico já gerou',
        ];

        foreach ($cartoesRetirados as $modulo => $titulo) {
            $this->assertStringNotContainsString(
                $titulo,
                file_get_contents(base_path('resources/views/'.$modulo.'/show.blade.php')),
                'A ficha de '.$modulo.' voltou a ter cartão de contagem solta.'
            );
        }

        foreach (['Clients/ClientController', 'Technicians/TechnicianController'] as $controller) {
            $codigo = file_get_contents(base_path('app/Http/Controllers/'.$controller.'.php'));

            $this->assertStringContainsString(
                'FichaHistorico::',
                $codigo,
                $controller.' deixou de usar a régua compartilhada de ficha.'
            );
            $this->assertStringNotContainsString(
                "'historico' =>",
                $codigo,
                $controller.' voltou a passar contagem solta para a tela.'
            );
        }
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /**
     * A conta de campo é o usuário amarrado à ficha — é por ela que o alcance
     * `visiveisPara` decide o que a pessoa pode ver.
     *
     * @return array{0: User, 1: Technician}
     */
    private function contaDeCampo(Company $empresa): array
    {
        $usuario = $this->makeUser('technician', $empresa, 'campo@test.local');
        $ficha = $this->tecnico($empresa, 'Técnico de Campo', ['user_id' => $usuario->id]);

        return [$usuario->fresh('technician'), $ficha];
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
        $ficha = Technician::query()->create($extras + ['name' => $nome, 'status' => 'available']);
        TenantContext::forget();

        return $ficha;
    }

    /** @param  array<string, mixed>  $extras */
    private function ordem(Company $empresa, string $numero, array $extras = []): ServiceOrder
    {
        $extras['client_id'] ??= $this->cliente($empresa, 'Cliente '.$numero)->id;

        TenantContext::set($empresa->id);
        $ordem = ServiceOrder::query()->create(array_merge([
            'company_id' => $empresa->id,
            'number' => $numero,
            'title' => 'Ordem '.$numero,
            'priority' => 'normal',
            'status' => 'open',
        ], $extras));
        TenantContext::forget();

        return $ordem;
    }

    private function servico(Company $empresa, string $nome, float $preco): Service
    {
        TenantContext::set($empresa->id);
        $servico = Service::query()->create(['name' => $nome, 'price' => $preco, 'status' => 'active']);
        TenantContext::forget();

        return $servico;
    }

    /** A linha que a ordem cobra: é dela que o cartão de serviços soma. */
    private function linhaDaOrdem(ServiceOrder $ordem, Service $servico, float $quantidade): void
    {
        TenantContext::set($ordem->company_id);
        $ordem->items()->create([
            'service_id' => $servico->id,
            'description' => $servico->name,
            'quantity' => $quantidade,
            'unit_price' => $servico->price,
        ]);
        TenantContext::forget();
    }

    /** @param  array<string, mixed>  $extras */
    private function chamado(Company $empresa, string $assunto, array $extras = []): Ticket
    {
        $extras['client_id'] ??= $this->cliente($empresa, 'Cliente do chamado')->id;

        TenantContext::set($empresa->id);
        $chamado = Ticket::query()->create($extras + [
            'company_id' => $empresa->id,
            'protocol' => Ticket::proximoProtocolo(),
            'subject' => $assunto,
            'category' => 'refrigeracao',
            'priority' => 'normal',
            'status' => 'open',
            'opened_at' => now(),
        ]);
        TenantContext::forget();

        return $chamado;
    }

    /** @param  array<string, mixed>  $extras */
    private function compromisso(Company $empresa, string $titulo, Carbon $inicio, array $extras = []): Appointment
    {
        TenantContext::set($empresa->id);
        $compromisso = Appointment::query()->create($extras + [
            'company_id' => $empresa->id,
            'title' => $titulo,
            'type' => 'visit',
            'status' => 'scheduled',
            'starts_at' => $inicio,
            'ends_at' => $inicio->copy()->addHour(),
        ]);
        TenantContext::forget();

        return $compromisso;
    }

    private function lancamento(Company $empresa, Client $cliente, float $valor, Carbon $vencimento): FinancialRecord
    {
        TenantContext::set($empresa->id);
        $registro = FinancialRecord::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'type' => FinancialRecord::REVENUE,
            'category' => 'visita_tecnica',
            'description' => 'Visita técnica na loja',
            'amount' => $valor,
            'due_date' => $vencimento,
            'occurred_at' => Carbon::today(),
            'status' => FinancialRecord::PENDING,
        ]);
        TenantContext::forget();

        return $registro;
    }
}
