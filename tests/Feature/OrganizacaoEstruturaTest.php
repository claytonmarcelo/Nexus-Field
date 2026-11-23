<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employment;
use App\Models\Notification;
use App\Models\OrganizationScope;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * Etapa 4. A estrutura é escrita só pelo serviço, lida com o mesmo alcance das
 * outras folhas e protegida no servidor — esconder botão não é autorização.
 * Aqui se prova o degrau por papel, a fronteira da empresa, a árvore sem ciclo,
 * o histórico de passagem e o que a trilha e o sino registram.
 */
class OrganizacaoEstruturaTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_admin_abre_cada_tela_da_estrutura(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);

        $rotas = [
            route('organization.index'),
            route('organization.branches.index'),
            route('organization.branches.create'),
            route('organization.branches.show', $estrutura['filial']),
            route('organization.branches.edit', $estrutura['filial']),
            route('organization.departments.index'),
            route('organization.departments.create'),
            route('organization.departments.show', $estrutura['setor']),
            route('organization.departments.edit', $estrutura['setor']),
            route('organization.positions.index'),
            route('organization.positions.create'),
            route('organization.positions.show', $estrutura['cargo']),
            route('organization.positions.edit', $estrutura['cargo']),
            route('organization.employments.index'),
            route('organization.employments.create'),
        ];

        foreach ($rotas as $rota) {
            $this->actingAs($admin)->get($rota)->assertOk();
        }
    }

    public function test_supervisor_le_e_exclui_a_estrutura_mas_nao_cria_nem_edita(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $supervisor = $this->contaComPapel($empresa, 'supervisor', 'supervisor-estrutura@test.local');
        $funcionario = $this->contaComPapel($empresa, 'employee', 'funcionario-estrutura@test.local');
        $tecnico = $this->contaComPapel($empresa, 'technician', 'tecnico-estrutura@test.local');
        $cliente = $this->contaComPapel($empresa, 'client', 'cliente-estrutura@test.local');
        $estrutura = $this->criarEstrutura($empresa);

        $this->actingAs($supervisor)->get(route('organization.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('organization.branches.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('organization.branches.create'))->assertForbidden();
        $this->actingAs($supervisor)->post(route('organization.branches.store'), $this->dadosFilial([
            'name' => 'Filial negada',
        ]))->assertForbidden();
        $this->actingAs($supervisor)->put(
            route('organization.branches.update', $estrutura['filial']),
            $this->dadosFilial(['name' => 'Filial alterada pelo supervisor'], $estrutura['filial']),
        )->assertForbidden();
        foreach ([$funcionario, $tecnico, $cliente] as $semAcesso) {
            $this->actingAs($semAcesso)->get(route('organization.index'))->assertForbidden();
            $this->actingAs($semAcesso)->delete(route('organization.branches.destroy', $estrutura['filial']))
                ->assertForbidden();
        }

        // A filial do quadro tem setor e não pode sair: a exclusão do gestor
        // continua passando pela regra de integridade do serviço.
        $this->actingAs($supervisor)->delete(route('organization.branches.destroy', $estrutura['filial']))
            ->assertSessionHas('erro');
        $this->assertDatabaseHas('branches', ['id' => $estrutura['filial']->id]);

        TenantContext::set($empresa->id);
        $vazia = Branch::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Filial vazia para exclusão',
            'type' => 'filial',
            'active' => true,
        ]);
        TenantContext::forget();

        $this->actingAs($supervisor)->delete(route('organization.branches.destroy', $vazia))
            ->assertRedirect()
            ->assertSessionMissing('erro');
        $this->assertSoftDeleted('branches', ['id' => $vazia->id]);

        $this->assertTrue($admin->hasPermission('organization.delete'));
        $this->assertDatabaseMissing('branches', ['name' => 'Filial negada']);
    }

    public function test_filial_e_criada_atualizada_e_excluida_com_trilha_semantica(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->post(route('organization.branches.store'), $this->dadosFilial([
            'name' => 'Matriz Central',
        ]))->assertRedirect();

        $filial = Branch::query()->where('company_id', $empresa->id)->firstOrFail();
        $this->assertSame('matriz-central', $filial->slug);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $empresa->id,
            'action' => 'filial criada',
            'entity_type' => 'Branch',
            'entity_id' => (string) $filial->id,
        ]);

        $this->actingAs($admin)->put(
            route('organization.branches.update', $filial),
            $this->dadosFilial(['name' => 'Matriz Atualizada'], $filial),
        )->assertRedirect(route('organization.branches.show', $filial));

        $this->assertSame('Matriz Atualizada', $filial->fresh()->name);

        $atualizacao = AuditLog::query()->where('action', 'filial atualizada')->firstOrFail();
        $this->assertArrayHasKey('name', $atualizacao->changes ?? []);
        $this->assertSame('Matriz Central', $atualizacao->changes['name'][0]);
        $this->assertSame('Matriz Atualizada', $atualizacao->changes['name'][1]);

        $this->actingAs($admin)->delete(route('organization.branches.destroy', $filial))
            ->assertRedirect(route('organization.branches.index'));

        $this->assertSoftDeleted('branches', ['id' => $filial->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'filial excluída']);
    }

    public function test_segunda_matriz_ativa_e_recusada_com_flash_sem_erro_500(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        $this->actingAs($admin)->post(route('organization.branches.store'), $this->dadosFilial([
            'name' => 'Matriz Unica',
            'type' => 'matriz',
        ]))->assertRedirect();

        $resposta = $this->actingAs($admin)->post(route('organization.branches.store'), $this->dadosFilial([
            'name' => 'Matriz Segunda',
            'type' => 'matriz',
        ]));

        $resposta->assertRedirect();
        $resposta->assertSessionHas('erro', fn (?string $mensagem): bool => str_contains(
            (string) $mensagem,
            'Já existe uma matriz ativa',
        ));
        $this->assertSame(1, Branch::query()->where('company_id', $empresa->id)->count());
    }

    public function test_setor_com_ciclo_ou_pai_de_outra_empresa_e_recusado_pelo_request(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);

        $filho = Department::query()->create([
            'company_id' => $empresa->id,
            'branch_id' => $estrutura['filial']->id,
            'parent_id' => $estrutura['setor']->id,
            'name' => 'Setor Filho',
            'slug' => 'setor-filho',
            'type' => 'operacao',
            'active' => true,
        ]);

        $this->actingAs($admin)->put(
            route('organization.departments.update', $estrutura['setor']),
            $this->dadosSetor($estrutura['filial']->id, [
                'name' => $estrutura['setor']->name,
                'parent_id' => $filho->id,
            ]),
        )->assertSessionHasErrors('parent_id');

        $outra = $this->makeCompany('outra-estrutura');
        TenantContext::set($outra->id);
        $alheio = Department::query()->create([
            'company_id' => $outra->id,
            'name' => 'Setor Alheio',
            'slug' => 'setor-alheio',
            'type' => 'operacao',
            'active' => true,
        ]);
        TenantContext::forget();

        $this->actingAs($admin)->post(
            route('organization.departments.store'),
            $this->dadosSetor($estrutura['filial']->id, [
                'name' => 'Setor Com Pai Alheio',
                'parent_id' => $alheio->id,
            ]),
        )->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('departments', ['name' => 'Setor Com Pai Alheio']);
        $this->assertNotSame($filho->id, $estrutura['setor']->fresh()->parent_id);
    }

    public function test_mover_setor_recalculate_caminho_da_subarvore(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);

        $filho = Department::query()->create([
            'company_id' => $empresa->id,
            'branch_id' => $estrutura['filial']->id,
            'parent_id' => $estrutura['setor']->id,
            'name' => 'Setor Filho',
            'slug' => 'setor-filho',
            'type' => 'operacao',
            'active' => true,
        ]);
        $neto = Department::query()->create([
            'company_id' => $empresa->id,
            'branch_id' => $estrutura['filial']->id,
            'parent_id' => $filho->id,
            'name' => 'Setor Neto',
            'slug' => 'setor-neto',
            'type' => 'operacao',
            'active' => true,
        ]);

        $this->assertSame('/'.$estrutura['setor']->id.'/'.$filho->id.'/'.$neto->id.'/', $neto->fresh()->path);

        $this->actingAs($admin)->put(
            route('organization.departments.update', $filho),
            $this->dadosSetor($estrutura['filial']->id, [
                'name' => $filho->name,
                'parent_id' => null,
            ]),
        )->assertRedirect();

        $this->assertSame('/'.$filho->id.'/', $filho->fresh()->path);
        $this->assertSame(0, (int) $filho->fresh()->depth);
        $this->assertSame('/'.$filho->id.'/'.$neto->id.'/', $neto->fresh()->path);
        $this->assertSame(1, (int) $neto->fresh()->depth);
    }

    public function test_mudar_passagem_encerra_linha_abre_outra_e_notifica(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);
        $outroSetor = Department::query()->create([
            'company_id' => $empresa->id,
            'branch_id' => $estrutura['filial']->id,
            'name' => 'Setor Destino',
            'slug' => 'setor-destino',
            'type' => 'operacao',
            'active' => true,
        ]);
        $pessoa = $this->contaComPapel($empresa, 'employee', 'pessoa-vinculo@test.local');

        $this->actingAs($admin)->post(
            route('organization.employments.store'),
            $this->dadosVinculo($pessoa, $estrutura, ['employee_code' => 'FUNC-001']),
        )->assertRedirect();

        $primeira = Employment::query()->where('employee_code', 'FUNC-001')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'company_id' => $empresa->id,
            'user_id' => $pessoa->id,
            'type' => 'organizacao.vinculo',
        ]);

        $this->actingAs($admin)->put(
            route('organization.employments.update', $primeira),
            $this->dadosVinculo($pessoa, $estrutura, [
                'employee_code' => 'FUNC-001',
                'department_id' => $outroSetor->id,
            ], $primeira),
        )->assertRedirect();

        $this->assertSame(2, Employment::query()->where('company_id', $empresa->id)->count());
        $this->assertSame('encerrado', $primeira->fresh()->status);
        $this->assertNotNull($primeira->fresh()->ended_at);

        $nova = Employment::query()->where('employee_code', 'FUNC-001-2')->firstOrFail();
        $this->assertSame('ativo', $nova->status);
        $this->assertSame($outroSetor->id, (int) $nova->department_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vínculo movimentado']);
        $this->assertSame(2, Notification::query()->where('user_id', $pessoa->id)->count());
    }

    public function test_editar_sem_mudar_passagem_nao_abre_nova_linha(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);
        $pessoa = $this->contaComPapel($empresa, 'employee', 'pessoa-estavel@test.local');

        $this->actingAs($admin)->post(
            route('organization.employments.store'),
            $this->dadosVinculo($pessoa, $estrutura, ['employee_code' => 'FUNC-002']),
        )->assertRedirect();

        $vinculo = Employment::query()->firstOrFail();

        $this->actingAs($admin)->put(
            route('organization.employments.update', $vinculo),
            $this->dadosVinculo($pessoa, $estrutura, [
                'employee_code' => 'FUNC-002',
                'status' => 'afastado',
            ], $vinculo),
        )->assertRedirect(route('organization.employments.show', $vinculo));

        $this->assertSame(1, Employment::query()->where('company_id', $empresa->id)->count());
        $this->assertSame('afastado', $vinculo->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vínculo atualizado']);
    }

    public function test_desligamento_preserva_historico(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);
        $pessoa = $this->contaComPapel($empresa, 'employee', 'pessoa-desligada@test.local');

        $this->actingAs($admin)->post(
            route('organization.employments.store'),
            $this->dadosVinculo($pessoa, $estrutura, ['employee_code' => 'FUNC-003']),
        )->assertRedirect();

        $vinculo = Employment::query()->firstOrFail();

        $this->actingAs($admin)->delete(route('organization.employments.destroy', $vinculo))
            ->assertRedirect(route('organization.employments.index'));

        $this->assertSame(1, Employment::query()->where('company_id', $empresa->id)->count());
        $this->assertNotSoftDeleted('employments', ['id' => $vinculo->id]);
        $this->assertSame('desligado', $vinculo->fresh()->status);
        $this->assertNotNull($vinculo->fresh()->ended_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vínculo desligado']);
    }

    public function test_ficha_de_outra_empresa_da_404_e_request_nao_aceita_id_alheio(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);

        $outra = $this->makeCompany('empresa-vizinha');
        TenantContext::set($outra->id);
        $filialAlheia = Branch::query()->create([
            'company_id' => $outra->id,
            'name' => 'Filial Vizinha',
            'type' => 'filial',
            'active' => true,
        ]);
        $pessoaAlheia = User::query()->create([
            'company_id' => $outra->id,
            'name' => 'Pessoa Vizinha',
            'email' => 'vizinho@test.local',
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);
        TenantContext::forget();

        $this->actingAs($admin)->get(route('organization.branches.show', $filialAlheia))->assertNotFound();

        $this->actingAs($admin)->post(route('organization.employments.store'), [
            'user_id' => $pessoaAlheia->id,
            'employee_code' => 'ALHEIO-1',
            'branch_id' => $filialAlheia->id,
            'department_id' => $estrutura['setor']->id,
            'position_id' => $estrutura['cargo']->id,
            'manager_id' => null,
            'employment_type' => 'clt',
            'status' => 'ativo',
            'started_at' => '2026-01-01',
            'ended_at' => null,
        ])->assertSessionHasErrors(['user_id', 'branch_id']);

        $this->assertDatabaseMissing('employments', ['employee_code' => 'ALHEIO-1']);
    }

    public function test_alcance_recorta_folha_de_vinculos_e_avisa_organograma(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();
        $estrutura = $this->criarEstrutura($empresa);

        $segunda = Branch::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Filial Norte',
            'slug' => 'filial-norte',
            'type' => 'filial',
            'active' => true,
        ]);
        $outroSetor = Department::query()->create([
            'company_id' => $empresa->id,
            'branch_id' => $segunda->id,
            'name' => 'Setor Norte',
            'slug' => 'setor-norte',
            'type' => 'operacao',
            'active' => true,
        ]);

        $dentro = $this->contaComPapel($empresa, 'employee', 'pessoa-dentro@test.local');
        $fora = $this->contaComPapel($empresa, 'employee', 'pessoa-fora@test.local');
        $supervisor = $this->contaComPapel($empresa, 'supervisor', 'gestor-escopo@test.local');

        $this->actingAs($admin)->post(
            route('organization.employments.store'),
            $this->dadosVinculo($dentro, $estrutura, ['employee_code' => 'DENTRO-1']),
        )->assertRedirect();
        $this->actingAs($admin)->post(
            route('organization.employments.store'),
            $this->dadosVinculo($fora, [
                'filial' => $segunda,
                'setor' => $outroSetor,
                'cargo' => $estrutura['cargo'],
            ], ['employee_code' => 'FORA-1']),
        )->assertRedirect();

        OrganizationScope::query()->create([
            'company_id' => $empresa->id,
            'user_id' => $supervisor->id,
            'scope' => \App\Support\Alcance::FILIAL,
            'scope_id' => $estrutura['filial']->id,
            'active' => true,
        ]);

        // O POST anterior deixou flash "Vínculo de FORA-1 registrado." na sessão;
        // sem limpar, essa mensagem contaminaria a leitura da folha pelo supervisor.
        $this->flushSession();

        $this->actingAs($supervisor)->get(route('organization.employments.index'))
            ->assertOk()
            ->assertSee('DENTRO-1')
            ->assertDontSee('FORA-1');

        $this->actingAs($supervisor)->get(route('organization.index'))
            ->assertOk()
            ->assertSee('Organograma recortado pelo seu alcance');
    }

    public function test_filtro_de_situacao_funciona_sobre_coluna_booleana(): void
    {
        [$empresa, $admin] = $this->empresaComAdmin();

        Branch::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Unidade Ativa',
            'type' => 'filial',
            'active' => true,
        ]);
        Branch::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Unidade Baixada',
            'type' => 'filial',
            'active' => false,
        ]);

        $this->actingAs($admin)->get(route('organization.branches.index', ['situacao' => 'inactive']))
            ->assertOk()
            ->assertSee('Unidade Baixada')
            ->assertDontSee('Unidade Ativa');

        $this->actingAs($admin)->get(route('organization.branches.index', ['situacao' => 'active']))
            ->assertOk()
            ->assertSee('Unidade Ativa')
            ->assertDontSee('Unidade Baixada');
    }

    public function test_tabelas_tem_legenda_e_cabecalho_com_escopo(): void
    {
        foreach (File::allFiles(resource_path('views/organization')) as $arquivo) {
            $conteudo = File::get($arquivo->getPathname());

            if (! str_contains($conteudo, '<table')) {
                continue;
            }

            $this->assertStringContainsString('<caption', $conteudo, "Tabela sem legenda: {$arquivo->getFilename()}");
            $this->assertStringContainsString('scope="col"', $conteudo, "Cabeçalho sem escopo: {$arquivo->getFilename()}");
        }
    }

    public function test_controllers_apresentam_e_servico_escreve(): void
    {
        $controllers = File::allFiles(app_path('Http/Controllers/Organization'));
        $proibidas = ['DB::transaction', 'Auditor::', 'Notifier::', '::create(', '->save(', '->delete(', '->update('];

        foreach ($controllers as $arquivo) {
            $conteudo = File::get($arquivo->getPathname());

            foreach ($proibidas as $proibida) {
                $this->assertStringNotContainsString(
                    $proibida,
                    $conteudo,
                    "Escrita {$proibida} voltou para {$arquivo->getFilename()}.",
                );
            }
        }

        $servico = File::get(app_path('Services/Organizacao/GestorDeEstrutura.php'));

        foreach (['DB::transaction', 'Auditor::silenciar', 'Auditor::gravar', 'Notifier::para'] as $esperada) {
            $this->assertStringContainsString($esperada, $servico);
        }
    }

    /** @return array{0: Company, 1: User} */
    private function empresaComAdmin(): array
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('estrutura');
        Roles::provision($empresa, Permission::query()->pluck('id', 'slug')->all());

        return [$empresa, $this->contaComPapel($empresa, 'administrator', 'admin-estrutura@test.local')];
    }

    private function contaComPapel(Company $empresa, string $slug, string $email): User
    {
        $papel = Role::query()->where('company_id', $empresa->id)->where('slug', $slug)->first()
            ?? $this->makeRole($slug, $empresa);

        $usuario = User::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Conta '.$slug,
            'email' => $email,
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);
        $usuario->roles()->attach($papel->id);

        return $usuario->fresh('roles.permissions');
    }

    /** @return array{filial: Branch, setor: Department, cargo: Position} */
    private function criarEstrutura(Company $empresa): array
    {
        TenantContext::set($empresa->id);

        $filial = Branch::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Matriz Principal',
            'type' => 'matriz',
            'active' => true,
        ]);
        $setor = Department::query()->create([
            'company_id' => $empresa->id,
            'branch_id' => $filial->id,
            'name' => 'Operações',
            'slug' => 'operacoes',
            'type' => 'operacao',
            'active' => true,
        ]);
        $cargo = Position::query()->create([
            'company_id' => $empresa->id,
            'department_id' => $setor->id,
            'name' => 'Coordenador',
            'level' => 5,
            'active' => true,
        ]);

        TenantContext::forget();

        return ['filial' => $filial, 'setor' => $setor, 'cargo' => $cargo];
    }

    /** @param array<string, mixed> $extras @return array<string, mixed> */
    private function dadosFilial(array $extras = [], ?Branch $atual = null): array
    {
        return $extras + [
            'name' => $atual?->name ?? 'Filial Nova '.mt_rand(1000, 9999),
            'type' => $atual?->type ?? 'filial',
            'manager_user_id' => null,
            'active' => 1,
        ];
    }

    /** @param array<string, mixed> $extras @return array<string, mixed> */
    private function dadosSetor(int $filialId, array $extras = []): array
    {
        return $extras + [
            'name' => 'Setor Novo '.mt_rand(1000, 9999),
            'branch_id' => $filialId,
            'parent_id' => null,
            'description' => null,
            'manager_user_id' => null,
            'type' => 'operacao',
            'active' => 1,
        ];
    }

    /**
     * @param  array{filial: Branch, setor: Department, cargo: Position}  $estrutura
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function dadosVinculo(
        User $pessoa,
        array $estrutura,
        array $extras = [],
        ?Employment $atual = null,
    ): array {
        return $extras + [
            'user_id' => $pessoa->id,
            'employee_code' => $atual?->employee_code ?? 'FUNC-'.mt_rand(1000, 9999),
            'branch_id' => $estrutura['filial']->id,
            'department_id' => $estrutura['setor']->id,
            'position_id' => $estrutura['cargo']->id,
            'manager_id' => null,
            'employment_type' => 'clt',
            'status' => 'ativo',
            'started_at' => '2026-01-01',
            'ended_at' => null,
        ];
    }
}
