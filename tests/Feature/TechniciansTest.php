<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Client;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\Specialty;
use App\Models\Team;
use App\Models\Technician;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Fase 10 fecha quando a escala, as equipes e as especialidades obedecem ao banco
 * da empresa aberta: a listagem filtra, o servidor recusa vínculo cruzado e a
 * saída de um técnico da equipe vira registro com data, não apagamento.
 */
class TechniciansTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_a_listagem_traz_so_os_tecnicos_da_empresa_aberta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $outra = $this->makeCompany('bravo');

        $this->tecnico($empresa, 'Ricardo Prado');
        $this->tecnico($empresa, 'Ana Beltrão', ['region' => 'Litoral']);
        $this->tecnico($outra, 'Técnico de outra empresa');

        $resposta = $this->actingAs($usuario)->get(route('technicians.index'));

        $resposta->assertOk()
            ->assertSee('Ricardo Prado')
            ->assertSee('Litoral')
            ->assertDontSee('Técnico de outra empresa');
    }

    public function test_filtro_por_situacao_regiao_e_especialidade_veem_do_banco(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $gas = $this->especialidade($empresa, 'Gás e aquecedores');
        $gelo = $this->especialidade($empresa, 'Refrigeração');

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado', ['region' => 'Zona Norte', 'status' => 'available']);
        $ana = $this->tecnico($empresa, 'Ana Beltrão', ['region' => 'Litoral', 'status' => 'off']);

        TenantContext::set($empresa->id);
        $ricardo->specialties()->attach($gas->id);
        $ana->specialties()->attach($gelo->id);
        TenantContext::forget();

        $this->actingAs($usuario)->get(route('technicians.index', ['regiao' => 'Litoral']))
            ->assertOk()->assertSee('Ana Beltrão')->assertDontSee('Ricardo Prado');

        $this->actingAs($usuario)->get(route('technicians.index', ['situacao' => 'off']))
            ->assertOk()->assertSee('Ana Beltrão')->assertDontSee('Ricardo Prado');

        $porEspecialidade = $this->actingAs($usuario)->get(route('technicians.index', ['especialidade' => $gas->id]));
        $porEspecialidade->assertOk()->assertSee('Ricardo Prado')->assertDontSee('Ana Beltrão');

        // O seletor de região é montado com as regiões gravadas, não com lista fixa.
        $this->actingAs($usuario)->get(route('technicians.index'))
            ->assertSee('Zona Norte')
            ->assertSee('Litoral');
    }

    public function test_a_paginacao_e_a_contagem_vem_da_consulta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        TenantContext::set($empresa->id);
        foreach (range(1, 12) as $n) {
            Technician::query()->create([
                'name' => 'Técnico '.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]);
        }
        TenantContext::forget();

        $primeira = $this->actingAs($usuario)->get(route('technicians.index', ['por_pagina' => 10]));
        $segunda = $this->actingAs($usuario)->get(route('technicians.index', ['por_pagina' => 10, 'page' => 2]));

        $primeira->assertOk()->assertSee('Técnico 01')->assertDontSee('Técnico 11');
        $segunda->assertOk()->assertSee('Técnico 11')->assertSee('Técnico 12');
        $primeira->assertSee('12 técnicos na escala');
    }

    public function test_criar_tecnico_grava_no_contexto_e_amarra_especialidades_da_empresa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $gas = $this->especialidade($empresa, 'Gás e aquecedores');
        $estranha = $this->especialidade($this->makeCompany('bravo'), 'Cozinhas industriais');

        $this->actingAs($usuario)->post(route('technicians.store'), [
            'name' => 'Ricardo Prado',
            'document' => '123.456.789-00',
            'status' => 'available',
            'region' => 'Zona Norte',
            'especialidades' => [$gas->id, $estranha->id],
        ])->assertSessionHasNoErrors();

        $tecnico = Technician::anyCompany()->firstWhere('name', 'Ricardo Prado');

        $this->assertNotNull($tecnico);
        $this->assertSame($empresa->id, $tecnico->company_id, 'A empresa vem do contexto, não do formulário.');

        // A especialidade da empresa ao lado não entra no pivô, mesmo enviada no form.
        $this->assertSame([$gas->id], $tecnico->specialties()->pluck('specialties.id')->all());
    }

    public function test_documento_e_conta_de_acesso_valem_só_dentro_da_empresa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $estranha = $this->makeCompany('bravo');

        $this->tecnico($empresa, 'Ricardo Prado', ['document' => '123.456.789-00']);
        $contaAlheia = $this->makeUser('technician', $estranha, 'campo@bravo.local');

        $this->actingAs($usuario)->post(route('technicians.store'), [
            'name' => 'Duplicado',
            'document' => '123.456.789-00',
            'status' => 'available',
        ])->assertSessionHasErrors('document');

        // Vincular conta de outro tenant quebraria o escopo na hora do login.
        $this->actingAs($usuario)->post(route('technicians.store'), [
            'name' => 'Sem vínculo cruzado',
            'status' => 'available',
            'user_id' => $contaAlheia->id,
        ])->assertSessionHasErrors('user_id');

        // Duas fichas na mesma conta também não passam (`technicians.user_id` é único).
        $propria = $this->makeUser('technician', $empresa, 'campo@alfa.local');
        $this->tecnico($empresa, 'Ana Beltrão', ['user_id' => $propria->id]);

        $this->actingAs($usuario)->post(route('technicians.store'), [
            'name' => 'Terceiro',
            'status' => 'available',
            'user_id' => $propria->id,
        ])->assertSessionHasErrors('user_id');
    }

    public function test_a_ficha_e_a_base_do_tecnico_sao_da_empresa_aberta(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $estranha = $this->tecnico($this->makeCompany('bravo'), 'Técnico invasor');
        $baseAlheia = $this->endereco($estranha->company_id, $estranha);

        $this->actingAs($usuario)->get(route('technicians.show', $estranha))->assertNotFound();
        $this->actingAs($usuario)->put(route('technicians.addresses.update', [$estranha, $baseAlheia]), [
            'endereco_tipo' => 'service',
            'endereco_logradouro' => 'Rua atravessada',
            'endereco_cidade' => 'São Paulo',
            'endereco_uf' => 'SP',
        ])->assertNotFound();
    }

    public function test_excluir_recusa_quem_ja_foi_a_campo_e_deixa_fora_da_escala_quem_nao_foi(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $novo = $this->tecnico($empresa, 'Ricardo Prado');
        $usado = $this->tecnico($empresa, 'Ana Beltrão');

        TenantContext::set($empresa->id);
        ServiceOrder::query()->create([
            'company_id' => $empresa->id,
            'client_id' => $this->cliente($empresa, 'Padaria Sant’Anna')->id,
            'technician_id' => $usado->id,
            'number' => 'OS-0001',
            'title' => 'Troca da resistência',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        TenantContext::forget();

        $this->from(route('technicians.show', $usado))->actingAs($usuario)
            ->delete(route('technicians.destroy', $usado))
            ->assertRedirect(route('technicians.show', $usado))
            ->assertSessionHas('erro');

        $this->assertDatabaseHas('technicians', ['id' => $usado->id, 'deleted_at' => null]);

        $this->actingAs($usuario)->delete(route('technicians.destroy', $novo))
            ->assertRedirect(route('technicians.index'));
        $this->assertSoftDeleted($novo);

        // A prova é a contagem que o banco faz: saiu um, resta um na escala.
        $this->actingAs($usuario)->get(route('technicians.index'))
            ->assertSee('1 técnico na escala');

        $this->actingAs($usuario)->patch(route('technicians.restore', $novo))
            ->assertRedirect(route('technicians.show', $novo));
        $this->assertDatabaseHas('technicians', ['id' => $novo->id, 'deleted_at' => null]);
    }

    public function test_equipe_so_aceita_lider_e_membros_da_mesma_empresa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $estranha = $this->makeCompany('bravo');

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $invasor = $this->tecnico($estranha, 'Técnico de outra empresa');

        $this->actingAs($usuario)->post(route('teams.store'), [
            'name' => 'Equipe Zona Norte',
            'leader_id' => $invasor->id,
            'status' => 'active',
        ])->assertSessionHasErrors('leader_id');

        $this->actingAs($usuario)->post(route('teams.store'), [
            'name' => 'Equipe Zona Norte',
            'leader_id' => $ricardo->id,
            'region' => 'Zona Norte',
            'status' => 'active',
            'membros' => [$ricardo->id, $invasor->id],
        ])->assertSessionHasNoErrors();

        $equipe = Team::anyCompany()->firstWhere('name', 'Equipe Zona Norte');

        $this->assertSame([$ricardo->id], $equipe->technicians()->pluck('technicians.id')->all(),
            'Membro de outra empresa não entra no quadro.');

        $this->actingAs($usuario)->post(route('teams.store'), [
            'name' => 'Equipe Zona Norte',
            'status' => 'active',
        ])->assertSessionHasErrors('name');
    }

    public function test_saida_de_membro_registra_data_e_preserva_a_passagem(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $equipe = $this->equipe($empresa, 'Equipe Litoral', $ricardo, [$ricardo, $ana]);

        $this->actingAs($usuario)->delete(route('teams.members.destroy', [$equipe, $ana]))
            ->assertRedirect();

        $pivot = $equipe->technicians()->where('technicians.id', $ana->id)->first();
        $this->assertNotNull($pivot, 'A linha do quadro não é apagada.');
        $this->assertNotNull($pivot->pivot->left_at);
        $this->assertNotNull($pivot->pivot->joined_at, 'A entrada continua registrada depois da saída.');

        // Retirar o líder do quadro deixa a equipe sem liderança, sem apagar a ficha.
        $this->actingAs($usuario)->delete(route('teams.members.destroy', [$equipe, $ricardo]));
        $this->assertNull($equipe->fresh()->leader_id);
        $this->assertSame(0, $equipe->membrosAtivos()->count());

        // A listagem conta o quadro no SQL e é ela que o usuário abre primeiro.
        $this->actingAs($usuario)->get(route('teams.index'))
            ->assertOk()
            ->assertSee('Equipe Litoral');
    }

    public function test_adicionar_membro_pela_ficha_só_aceita_tecnico_da_empresa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();
        $estranha = $this->makeCompany('bravo');

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $ana = $this->tecnico($empresa, 'Ana Beltrão');
        $invasor = $this->tecnico($estranha, 'Técnico de outra empresa');

        $equipe = $this->equipe($empresa, 'Equipe Zona Norte', $ricardo, [$ricardo]);

        $this->actingAs($usuario)->post(route('teams.members.store', $equipe), ['tecnico_id' => $invasor->id])
            ->assertSessionHasErrors('tecnico_id');

        $this->actingAs($usuario)->post(route('teams.members.store', $equipe), ['tecnico_id' => $ana->id])
            ->assertRedirect();

        $this->assertSame(2, $equipe->technicians()->wherePivotNull('left_at')->count());
    }

    public function test_excluir_equipe_exige_quadro_vazio(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        $cheia = $this->equipe($empresa, 'Equipe Zona Norte', $ricardo, [$ricardo]);
        $vazia = $this->equipe($empresa, 'Equipe sem Quadro');

        $this->from(route('teams.show', $cheia))->actingAs($usuario)
            ->delete(route('teams.destroy', $cheia))
            ->assertSessionHas('erro');
        $this->assertDatabaseHas('teams', ['id' => $cheia->id, 'deleted_at' => null]);

        $this->actingAs($usuario)->delete(route('teams.destroy', $vazia))
            ->assertRedirect(route('teams.index'));
        $this->assertSoftDeleted($vazia);
    }

    public function test_especialidade_nasce_com_slug_do_nome_e_protege_quem_a_usa(): void
    {
        [$empresa, $usuario] = $this->empresaComAdmin();

        $this->actingAs($usuario)->post(route('specialties.store'), [
            'name' => 'Automação e painel',
            'description' => 'CLP, inversor e lógica de comando.',
        ])->assertSessionHasNoErrors();

        $especialidade = Specialty::anyCompany()->firstWhere('slug', 'automacao-e-painel');
        $this->assertNotNull($especialidade, 'O slug vem do nome, ninguém digita identificação.');

        $ricardo = $this->tecnico($empresa, 'Ricardo Prado');
        TenantContext::set($empresa->id);
        $ricardo->specialties()->attach($especialidade->id);
        TenantContext::forget();

        $this->actingAs($usuario)->delete(route('specialties.destroy', $especialidade))
            ->assertRedirect(route('specialties.index', ['busca' => 'Automação e painel']))
            ->assertSessionHas('erro');
        $this->assertDatabaseHas('specialties', ['id' => $especialidade->id]);

        $this->actingAs($usuario)->get(route('specialties.index'))
            ->assertOk()
            ->assertSee('Automação e painel')
            ->assertSee('1 técnico');
    }

    public function test_o_servidor_recusa_quem_nao_tem_a_permissao_do_modulo(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        $tecnico = $this->makeUser('technician', $empresa, 'campo@test.local');
        $funcionario = $this->makeUser('employee', $empresa, 'opc@test.local');

        $this->actingAs($tecnico)->get(route('technicians.index'))->assertForbidden();
        $this->actingAs($tecnico)->post(route('technicians.store'), ['name' => 'X', 'status' => 'available'])
            ->assertForbidden();
        $this->actingAs($tecnico)->get(route('teams.index'))->assertForbidden();
        $this->actingAs($tecnico)->post(route('teams.store'), ['name' => 'Equipe', 'status' => 'active'])
            ->assertForbidden();

        // O funcionário enxerga a escala e as especialidades, mas não mexe nelas
        // e nem entra no quadro de equipes — é o que o catálogo define.
        $this->actingAs($funcionario)->get(route('technicians.index'))->assertOk();
        $this->actingAs($funcionario)->get(route('technicians.create'))->assertForbidden();
        $this->actingAs($funcionario)->get(route('specialties.index'))->assertOk();
        $this->actingAs($funcionario)->get(route('teams.index'))->assertForbidden();
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');

        return [$empresa, $this->makeUser('administrator', $empresa, 'a@test.local')];
    }

    /** @param  array<string, mixed>  $extras */
    private function tecnico(Company $empresa, string $nome, array $extras = []): Technician
    {
        TenantContext::set($empresa->id);
        $tecnico = Technician::query()->create($extras + ['name' => $nome, 'status' => 'available']);
        TenantContext::forget();

        return $tecnico;
    }

    private function especialidade(Company $empresa, string $nome): Specialty
    {
        TenantContext::set($empresa->id);
        $especialidade = Specialty::query()->create(['name' => $nome]);
        TenantContext::forget();

        return $especialidade;
    }

    /** @param  array<int, Technician>  $membros */
    private function equipe(Company $empresa, string $nome, ?Technician $lider = null, array $membros = []): Team
    {
        TenantContext::set($empresa->id);
        $equipe = Team::query()->create(['name' => $nome, 'leader_id' => $lider?->id, 'status' => 'active']);

        foreach ($membros as $membro) {
            $equipe->technicians()->attach($membro->id, ['joined_at' => now()->subDays(3)]);
        }

        TenantContext::forget();

        return $equipe;
    }

    /** @param  array<string, mixed>  $extras */
    private function endereco(int $empresaId, Technician $tecnico, array $extras = []): Address
    {
        TenantContext::set($empresaId);
        $endereco = $tecnico->addresses()->create($extras + [
            'type' => 'service',
            'street' => 'Rua da Base',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);
        TenantContext::forget();

        return $endereco;
    }

    private function cliente(Company $empresa, string $nome): Client
    {
        TenantContext::set($empresa->id);
        $cliente = Client::query()->create(['name' => $nome, 'status' => 'active']);
        TenantContext::forget();

        return $cliente;
    }
}
