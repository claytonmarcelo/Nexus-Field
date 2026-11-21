<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employment;
use App\Models\OrganizationScope;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Alcance;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Contrato da migração: a estrutura nova nunca pode mudar o que cada perfil já
 * enxergava. Enquanto o usuário só tem `company`, `client` ou `self`, o
 * `visiveisPara` dos modelos continua mandando; o recorte do Alcance entra como
 * AND somente quando existe filial, setor, equipe ou subordinados gravados.
 */
class AlcanceTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private function usuario(Company $empresa, string $email, ?int $clienteId = null): User
    {
        return User::create([
            'company_id' => $empresa->id,
            'name' => 'Usuario '.$email,
            'email' => $email,
            'password' => 'Senha-Forte-123',
            'status' => 'active',
            'client_id' => $clienteId,
        ]);
    }

    private function cliente(Company $empresa, string $documento): Client
    {
        return Client::create([
            'company_id' => $empresa->id,
            'name' => 'Cliente '.$documento,
            'document' => $documento,
            'status' => 'active',
        ]);
    }

    private function ordem(Company $empresa, Client $cliente, string $numero, ?int $tecnicoId = null): ServiceOrder
    {
        return ServiceOrder::create([
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'technician_id' => $tecnicoId,
            'number' => $numero,
            'title' => 'Ordem '.$numero,
            'status' => 'open',
        ]);
    }

    private function tecnico(Company $empresa, User $usuario): Technician
    {
        return Technician::create([
            'company_id' => $empresa->id,
            'user_id' => $usuario->id,
            'name' => 'Tecnico '.$usuario->id,
            'status' => 'available',
        ]);
    }

    private function emprego(
        Company $empresa,
        User $usuario,
        ?Department $setor = null,
        ?User $chefe = null,
        ?Branch $filial = null,
    ): Employment {
        return Employment::create([
            'company_id' => $empresa->id,
            'user_id' => $usuario->id,
            'branch_id' => $filial?->id,
            'department_id' => $setor?->id,
            'manager_id' => $chefe?->id,
            'employee_code' => 'ALC-'.$usuario->id,
            'status' => 'ativo',
            'started_at' => now()->toDateString(),
        ]);
    }

    /** @return array{0: Branch, 1: Department} */
    private function estrutura(Company $empresa): array
    {
        $filial = Branch::create([
            'company_id' => $empresa->id, 'name' => 'Matriz', 'slug' => 'matriz', 'type' => 'matriz',
        ]);
        $raiz = Department::create([
            'company_id' => $empresa->id, 'branch_id' => $filial->id,
            'name' => 'Operacao', 'slug' => 'operacao',
        ]);

        return [$filial, $raiz];
    }

    private function escopo(Company $empresa, User $usuario, string $alcance, ?int $id = null): OrganizationScope
    {
        return OrganizationScope::create([
            'company_id' => $empresa->id,
            'user_id' => $usuario->id,
            'scope' => $alcance,
            'scope_id' => $id,
        ]);
    }

    public function test_usuario_sem_estrutura_continua_vendo_a_empresa_inteira(): void
    {
        $empresa = $this->makeCompany('alcance-legado');
        $usuario = $this->usuario($empresa, 'escritorio@test.local');
        $cliente = $this->cliente($empresa, '00000000000191');
        $this->ordem($empresa, $cliente, 'OS-LEGADO-1');
        $this->ordem($empresa, $cliente, 'OS-LEGADO-2');

        $this->assertSame([Alcance::EMPRESA], Alcance::escopos($usuario)->pluck('scope')->all());
        $this->assertTrue(Alcance::tem($usuario, Alcance::EMPRESA));
        $this->assertNull(Alcance::idsClientes($usuario));
        $this->assertNull(Alcance::idsUsuarios($usuario));
        $this->assertFalse(Alcance::temRecorteOrganizacional($usuario));
        $this->assertSame(2, Alcance::aplicar(
            ServiceOrder::query(), $usuario, ['cliente' => 'client_id'],
        )->count());
        $this->assertSame(2, ServiceOrder::query()->visiveisPara($usuario)->count());
    }

    public function test_carteira_de_cliente_e_fonte_da_restricao_legada(): void
    {
        $empresa = $this->makeCompany('alcance-cliente');
        $carteira = $this->cliente($empresa, '00000000000192');
        $alheia = $this->cliente($empresa, '00000000000193');
        $usuario = $this->usuario($empresa, 'cliente@test.local', $carteira->id);

        $this->ordem($empresa, $carteira, 'OS-CARTEIRA');
        $this->ordem($empresa, $alheia, 'OS-ALHEIA');

        $this->assertSame([Alcance::CLIENTE], Alcance::escopos($usuario)->pluck('scope')->all());
        $this->assertSame([$carteira->id], Alcance::idsClientes($usuario));
        // Escopo só de cliente não pode esvaziar a listagem de contas.
        $this->assertNull(Alcance::idsUsuarios($usuario));
        $this->assertFalse(Alcance::temRecorteOrganizacional($usuario));

        // O aplicar é no-op aqui: quem decide a carteira é o escopo legado.
        $this->assertSame(2, Alcance::aplicar(
            ServiceOrder::query(), $usuario, ['cliente' => 'client_id'],
        )->count());
        $this->assertSame(
            ['OS-CARTEIRA'],
            ServiceOrder::query()->visiveisPara($usuario)->pluck('number')->all(),
        );
    }

    public function test_trabalho_do_tecnico_e_fonte_da_restricao_legada(): void
    {
        $empresa = $this->makeCompany('alcance-tecnico');
        $cliente = $this->cliente($empresa, '00000000000194');
        $usuario = $this->usuario($empresa, 'tecnico@test.local');
        $outro = $this->usuario($empresa, 'outro-tecnico@test.local');

        $tecnico = $this->tecnico($empresa, $usuario);
        $tecnicoOutro = $this->tecnico($empresa, $outro);

        $this->ordem($empresa, $cliente, 'OS-MEU', $tecnico->id);
        $this->ordem($empresa, $cliente, 'OS-DELE', $tecnicoOutro->id);

        $this->assertSame([Alcance::PROPRIO], Alcance::escopos($usuario)->pluck('scope')->all());
        $this->assertSame([], Alcance::idsClientes($usuario));
        $this->assertSame([$usuario->id], Alcance::idsUsuarios($usuario));
        $this->assertFalse(Alcance::temRecorteOrganizacional($usuario));

        $this->assertSame(2, Alcance::aplicar(
            ServiceOrder::query(), $usuario, ['tecnico' => 'technician_id'],
        )->count());
        $this->assertSame(
            ['OS-MEU'],
            ServiceOrder::query()->visiveisPara($usuario)->pluck('number')->all(),
        );
    }

    public function test_escopo_de_setor_alcanca_a_subarvore_inteira(): void
    {
        $empresa = $this->makeCompany('alcance-setor');
        [$filial, $raiz] = $this->estrutura($empresa);

        $filha = Department::create([
            'company_id' => $empresa->id, 'branch_id' => $filial->id, 'parent_id' => $raiz->id,
            'name' => 'Campo', 'slug' => 'campo',
        ]);
        $fora = Department::create([
            'company_id' => $empresa->id, 'branch_id' => $filial->id,
            'name' => 'Financeiro', 'slug' => 'financeiro',
        ]);

        $gestor = $this->usuario($empresa, 'gestor-setor@test.local');
        $naRaiz = $this->usuario($empresa, 'raiz-setor@test.local');
        $naFilha = $this->usuario($empresa, 'filha-setor@test.local');
        $foraArvore = $this->usuario($empresa, 'fora-setor@test.local');

        foreach ([[$gestor, $raiz], [$naRaiz, $raiz], [$naFilha, $filha], [$foraArvore, $fora]] as [$usuario, $setor]) {
            $this->emprego($empresa, $usuario, $setor, null, $filial);
        }

        $this->escopo($empresa, $gestor, Alcance::SETOR, $raiz->id);
        $ids = Alcance::idsUsuarios($gestor);

        $this->assertTrue(Alcance::temRecorteOrganizacional($gestor));
        $this->assertContains($gestor->id, $ids);
        $this->assertContains($naRaiz->id, $ids);
        $this->assertContains($naFilha->id, $ids);
        $this->assertNotContains($foraArvore->id, $ids);
        $this->assertSame(
            [$raiz->id, $filha->id],
            Department::query()->naArvore($raiz)->orderBy('id')->pluck('id')->all(),
        );
        $this->assertSame(
            3,
            Alcance::aplicar(User::query()->where('company_id', $empresa->id), $gestor, ['usuario' => 'id'])->count(),
        );
    }

    public function test_escopo_de_subordinados_alcanca_toda_a_cadeia(): void
    {
        $empresa = $this->makeCompany('alcance-subordinados');
        $gestor = $this->usuario($empresa, 'gestor-cadeia@test.local');
        $intermediario = $this->usuario($empresa, 'intermediario@test.local');
        $subordinado = $this->usuario($empresa, 'subordinado@test.local');
        $alheio = $this->usuario($empresa, 'alheio-cadeia@test.local');

        foreach ([[$gestor, null], [$intermediario, $gestor], [$subordinado, $intermediario], [$alheio, null]] as [$usuario, $chefe]) {
            $this->emprego($empresa, $usuario, null, $chefe);
        }

        $this->escopo($empresa, $gestor, Alcance::SUBORDINADOS, $gestor->id);
        $ids = Alcance::idsUsuarios($gestor);

        $this->assertContains($intermediario->id, $ids);
        $this->assertContains($subordinado->id, $ids);
        $this->assertNotContains($gestor->id, $ids);
        $this->assertNotContains($alheio->id, $ids);
    }

    public function test_escopo_inativo_e_ignorado_e_devolve_o_comportamento_legado(): void
    {
        $empresa = $this->makeCompany('alcance-inativo');
        $usuario = $this->usuario($empresa, 'inativo@test.local');

        OrganizationScope::create([
            'company_id' => $empresa->id,
            'user_id' => $usuario->id,
            'scope' => Alcance::CLIENTE,
            'scope_id' => 999,
            'active' => false,
        ]);

        $this->assertSame([Alcance::EMPRESA], Alcance::escopos($usuario)->pluck('scope')->all());
        $this->assertNull(Alcance::idsClientes($usuario));
        $this->assertFalse(Alcance::temRecorteOrganizacional($usuario));
    }

    public function test_comissao_de_ordem_sobrevive_ao_recorte_organizacional(): void
    {
        $empresa = $this->makeCompany('alcance-comissao');
        [$filial, $raiz] = $this->estrutura($empresa);
        $gestor = $this->usuario($empresa, 'gestor-comissao@test.local');
        $interno = $this->usuario($empresa, 'interno-comissao@test.local');
        $externo = $this->usuario($empresa, 'externo-comissao@test.local');

        $this->emprego($empresa, $gestor, $raiz, null, $filial);
        $this->emprego($empresa, $interno, $raiz, null, $filial);
        $tecnicoInterno = $this->tecnico($empresa, $interno);
        $tecnicoExterno = $this->tecnico($empresa, $externo);
        $this->escopo($empresa, $gestor, Alcance::SETOR, $raiz->id);

        $cliente = $this->cliente($empresa, '00000000000197');
        $comissionada = $this->ordem($empresa, $cliente, 'OS-COMISSAO', $tecnicoExterno->id);
        $this->ordem($empresa, $cliente, 'OS-FORA-SETOR', $tecnicoExterno->id);

        DB::table('service_order_assignments')->insert([
            'service_order_id' => $comissionada->id,
            'technician_id' => $tecnicoInterno->id,
            'assigned_at' => now(),
        ]);

        $this->assertSame(
            ['OS-COMISSAO'],
            ServiceOrder::query()->visiveisPara($gestor)->pluck('number')->all(),
        );
    }

    public function test_responsavel_de_chamado_sobrevive_ao_recorte_organizacional(): void
    {
        $empresa = $this->makeCompany('alcance-responsavel');
        [$filial, $raiz] = $this->estrutura($empresa);
        $gestor = $this->usuario($empresa, 'gestor-chamado@test.local');
        $responsavel = $this->usuario($empresa, 'responsavel-chamado@test.local');
        $externo = $this->usuario($empresa, 'externo-chamado@test.local');

        $this->emprego($empresa, $gestor, $raiz, null, $filial);
        $this->emprego($empresa, $responsavel, $raiz, null, $filial);
        $tecnicoExterno = $this->tecnico($empresa, $externo);
        $this->escopo($empresa, $gestor, Alcance::SETOR, $raiz->id);

        $cliente = $this->cliente($empresa, '00000000000198');
        $dados = [
            'company_id' => $empresa->id,
            'client_id' => $cliente->id,
            'subject' => 'Chamado de teste',
            'category' => 'geral',
            'opened_at' => now(),
            'technician_id' => $tecnicoExterno->id,
        ];

        Ticket::create($dados + ['protocol' => 'TKT-DENTRO', 'responsible_user_id' => $responsavel->id]);
        Ticket::create($dados + ['protocol' => 'TKT-FORA', 'responsible_user_id' => $externo->id]);

        $this->assertSame(
            ['TKT-DENTRO'],
            Ticket::query()->visiveisPara($gestor)->pluck('protocol')->all(),
        );
    }

    public function test_cliente_de_checkin_vem_da_ordem_e_o_tecnico_do_recorte(): void
    {
        $empresa = $this->makeCompany('alcance-checkin');
        [$filial, $raiz] = $this->estrutura($empresa);
        $carteira = $this->cliente($empresa, '00000000000199');
        $alheia = $this->cliente($empresa, '00000000000200');

        $gestor = $this->usuario($empresa, 'gestor-checkin@test.local', $carteira->id);
        $interno = $this->usuario($empresa, 'interno-checkin@test.local');
        $externo = $this->usuario($empresa, 'externo-checkin@test.local');

        $this->emprego($empresa, $gestor, $raiz, null, $filial);
        $this->emprego($empresa, $interno, $raiz, null, $filial);
        $tecnicoInterno = $this->tecnico($empresa, $interno);
        $tecnicoExterno = $this->tecnico($empresa, $externo);
        $this->escopo($empresa, $gestor, Alcance::SETOR, $raiz->id);

        $ordemPropria = $this->ordem($empresa, $carteira, 'OS-CHECKIN-PROPRIA', $tecnicoInterno->id);
        $ordemAlheia = $this->ordem($empresa, $alheia, 'OS-CHECKIN-ALHEIA', $tecnicoInterno->id);

        $proprio = ServiceOrderCheckin::create([
            'company_id' => $empresa->id,
            'service_order_id' => $ordemPropria->id,
            'technician_id' => $tecnicoInterno->id,
            'checkin_at' => now(),
        ]);
        ServiceOrderCheckin::create([
            'company_id' => $empresa->id,
            'service_order_id' => $ordemAlheia->id,
            'technician_id' => $tecnicoInterno->id,
            'checkin_at' => now(),
        ]);
        ServiceOrderCheckin::create([
            'company_id' => $empresa->id,
            'service_order_id' => $ordemPropria->id,
            'technician_id' => $tecnicoExterno->id,
            'checkin_at' => now(),
        ]);

        $this->assertSame(
            [$proprio->id],
            ServiceOrderCheckin::query()->visiveisPara($gestor)->pluck('id')->all(),
        );
    }

    public function test_estoque_de_quem_nao_tem_stock_adjust_continua_restrito(): void
    {
        $empresa = $this->makeCompany('alcance-estoque');
        [$filial, $raiz] = $this->estrutura($empresa);
        $usuario = $this->usuario($empresa, 'estoque-setor@test.local');
        $externo = $this->usuario($empresa, 'estoque-fora@test.local');

        $this->emprego($empresa, $usuario, $raiz, null, $filial);
        $tecnicoInterno = $this->tecnico($empresa, $usuario);
        $tecnicoExterno = $this->tecnico($empresa, $externo);
        $this->escopo($empresa, $usuario, Alcance::SETOR, $raiz->id);

        $produto = Product::create([
            'company_id' => $empresa->id, 'sku' => 'ALC-PRODUTO', 'name' => 'Produto de teste',
        ]);
        $dados = [
            'company_id' => $empresa->id,
            'product_id' => $produto->id,
            'type' => 'adjust',
            'quantity' => 1,
            'recorded_at' => now(),
        ];

        $dentro = StockMovement::create($dados + ['technician_id' => $tecnicoInterno->id]);
        StockMovement::create($dados + ['technician_id' => $tecnicoExterno->id]);

        $this->assertSame(
            [$dentro->id],
            StockMovement::query()->visiveisPara($usuario)->pluck('id')->all(),
        );
    }

    public function test_agenda_subconsultas_e_select_parcial_sobrevivem_ao_recorte(): void
    {
        $empresa = $this->makeCompany('alcance-agenda');
        [$filial, $raiz] = $this->estrutura($empresa);
        $usuario = $this->usuario($empresa, 'agenda-setor@test.local');
        $this->emprego($empresa, $usuario, $raiz, null, $filial);
        $tecnico = $this->tecnico($empresa, $usuario);
        $this->escopo($empresa, $usuario, Alcance::SETOR, $raiz->id);

        $cliente = $this->cliente($empresa, '00000000000201');
        $ordem = $this->ordem($empresa, $cliente, 'OS-AGENDA', $tecnico->id);
        Appointment::create([
            'company_id' => $empresa->id,
            'technician_id' => $tecnico->id,
            'service_order_id' => $ordem->id,
            'title' => 'Janela da ordem',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        $this->assertSame(1, Appointment::query()->visiveisPara($usuario)->count());
        $this->assertSame(
            [$ordem->id],
            ServiceOrder::query()->visiveisPara($usuario)->select('id')->pluck('id')->all(),
        );
        $this->assertSame(
            [$ordem->id],
            ServiceOrder::query()->visiveisPara($usuario)->select('id')->lazyById(2)->pluck('id')->all(),
        );
    }

    public function test_or_do_alcance_nao_anula_o_isolamento_da_empresa(): void
    {
        $empresaA = $this->makeCompany('alcance-isolamento-a');
        $empresaB = $this->makeCompany('alcance-isolamento-b');
        [$filialA, $raizA] = $this->estrutura($empresaA);

        $gestor = $this->usuario($empresaA, 'isolamento@test.local');
        $this->emprego($empresaA, $gestor, $raizA, null, $filialA);
        $tecnicoA = $this->tecnico($empresaA, $gestor);
        $this->escopo($empresaA, $gestor, Alcance::SETOR, $raizA->id);
        $this->escopo($empresaA, $gestor, Alcance::FILIAL, $filialA->id);

        $clienteA = $this->cliente($empresaA, '00000000000202');
        $clienteB = $this->cliente($empresaB, '00000000000203');
        $this->ordem($empresaA, $clienteA, 'OS-A', $tecnicoA->id);
        $this->ordem($empresaB, $clienteB, 'OS-B');

        try {
            TenantContext::set($empresaA->id, $empresaA);

            // Dois mapas inserem duas condicoes no grupo de pessoa e, portanto, o OR.
            $consulta = Alcance::aplicar(
                ServiceOrder::query(), $gestor,
                ['tecnico' => 'technician_id', 'atribuicoes' => 'assignments'],
            );

            $this->assertStringContainsString('company_id', $consulta->toSql());
            $this->assertSame(['OS-A'], $consulta->pluck('number')->all());
        } finally {
            TenantContext::forget();
        }
    }
}
