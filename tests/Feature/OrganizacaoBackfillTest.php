<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\Employment;
use App\Models\OrganizationScope;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Technician;
use App\Models\User;
use App\Services\Recusa;
use App\Support\Roles;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

class OrganizacaoBackfillTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private function empresaComPapeis(): \App\Models\Company
    {
        $empresa = $this->makeCompany('organizacao');
        $this->seedPermissions();
        Roles::provision($empresa, Permission::query()->pluck('id', 'slug')->all());

        return $empresa;
    }

    private function usuarioComPapel(\App\Models\Company $empresa, string $slug, string $email): User
    {
        $usuario = User::create([
            'company_id' => $empresa->id,
            'name' => 'Usuario '.$slug,
            'email' => $email,
            'password' => 'Senha-Forte-123',
            'status' => 'active',
        ]);

        $papel = Role::query()->where('company_id', $empresa->id)->where('slug', $slug)->firstOrFail();
        $usuario->roles()->attach($papel->id);

        return $usuario;
    }

    public function test_backfill_cria_estrutura_padrao_e_preserva_os_usuarios(): void
    {
        $empresa = $this->empresaComPapeis();
        $admin = $this->usuarioComPapel($empresa, 'administrator', 'admin-organizacao@test.local');

        $antes = [
            'company_id' => $admin->company_id,
            'status' => $admin->status,
            'papeis' => $admin->roles()->pluck('roles.id')->all(),
        ];

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        $this->assertSame(1, Branch::query()->where('company_id', $empresa->id)->count());
        $this->assertSame('matriz', Branch::query()->value('slug'));
        $this->assertSame(6, Department::query()->where('company_id', $empresa->id)->count());
        $this->assertSame(6, Position::query()->where('company_id', $empresa->id)->count());
        $this->assertSame(1, Employment::query()->where('company_id', $empresa->id)->count());
        $this->assertSame(1, OrganizationScope::query()->where('company_id', $empresa->id)->count());

        $vinculo = Employment::query()->firstOrFail();
        $this->assertSame('administracao', $vinculo->department->slug);
        $this->assertSame('Diretor', $vinculo->position->name);

        $this->assertSame('company', OrganizationScope::query()->value('scope'));
        $this->assertSame($antes, [
            'company_id' => $admin->fresh()->company_id,
            'status' => $admin->fresh()->status,
            'papeis' => $admin->fresh()->roles()->pluck('roles.id')->all(),
        ]);
    }

    public function test_backfill_e_idempotente(): void
    {
        $empresa = $this->empresaComPapeis();
        $this->usuarioComPapel($empresa, 'administrator', 'admin-idempotente@test.local');

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        $contagens = [
            Branch::query()->count(),
            Department::query()->count(),
            Position::query()->count(),
            Employment::query()->count(),
            OrganizationScope::query()->count(),
        ];

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        $this->assertSame($contagens, [
            Branch::query()->count(),
            Department::query()->count(),
            Position::query()->count(),
            Employment::query()->count(),
            OrganizationScope::query()->count(),
        ]);
    }

    public function test_conta_de_cliente_recebe_alcance_da_propria_carteira(): void
    {
        $empresa = $this->empresaComPapeis();
        $cliente = Client::create([
            'company_id' => $empresa->id,
            'name' => 'Cliente Ficticio',
            'document' => '00000000000191',
            'status' => 'active',
        ]);

        $usuario = User::create([
            'company_id' => $empresa->id,
            'name' => 'Conta do Cliente',
            'email' => 'cliente-organizacao@test.local',
            'password' => 'Senha-Forte-123',
            'status' => 'active',
            'client_id' => $cliente->id,
        ]);

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        $alcance = OrganizationScope::query()->where('user_id', $usuario->id)->firstOrFail();

        $this->assertSame('client', $alcance->scope);
        $this->assertSame($cliente->id, $alcance->scope_id);
    }

    public function test_tecnico_de_campo_recebe_alcance_proprio_como_no_comportamento_legado(): void
    {
        $empresa = $this->empresaComPapeis();
        $usuario = $this->usuarioComPapel($empresa, 'technician', 'tecnico-organizacao@test.local');

        Technician::create([
            'company_id' => $empresa->id,
            'user_id' => $usuario->id,
            'name' => 'Tecnico Ficticio',
            'status' => 'available',
        ]);

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        $alcance = OrganizationScope::query()->where('user_id', $usuario->id)->firstOrFail();

        $this->assertSame('self', $alcance->scope);
        $this->assertSame($usuario->id, $alcance->scope_id);
    }

    public function test_backfill_respeita_o_isolamento_entre_empresas(): void
    {
        $empresaA = $this->empresaComPapeis();
        $empresaB = $this->makeCompany('organizacao-b');

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        try {
            TenantContext::set($empresaA->id, $empresaA);
            $this->assertSame(6, Department::query()->count());
            $this->assertSame([$empresaA->id], Department::query()->distinct()->pluck('company_id')->all());

            TenantContext::set($empresaB->id, $empresaB);
            $this->assertSame(6, Department::query()->count());
            $this->assertSame([$empresaB->id], Department::query()->distinct()->pluck('company_id')->all());
        } finally {
            TenantContext::forget();
        }
    }

    public function test_hierarquia_recusa_ciclo(): void
    {
        $empresaA = $this->empresaComPapeis();

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        $raiz = Department::query()->where('company_id', $empresaA->id)->where('slug', 'administracao')->firstOrFail();
        $filha = Department::query()->where('company_id', $empresaA->id)->where('slug', 'financeiro')->firstOrFail();

        try {
            TenantContext::set($empresaA->id, $empresaA);

            $this->expectException(Recusa::class);
            $raiz->parent_id = $filha->id;
            $raiz->save();
        } finally {
            TenantContext::forget();
        }
    }

    public function test_hierarquia_recusa_pai_de_outra_empresa(): void
    {
        $empresaA = $this->empresaComPapeis();
        $empresaB = $this->makeCompany('organizacao-pai-alheio');

        $this->artisan('nf:organizacao:backfill')->assertSuccessful();

        $setorA = Department::query()->where('company_id', $empresaA->id)->where('slug', 'administracao')->firstOrFail();
        $setorB = Department::query()->where('company_id', $empresaB->id)->where('slug', 'operacao')->firstOrFail();

        try {
            TenantContext::set($empresaA->id, $empresaA);

            $this->expectException(Recusa::class);
            $setorA->parent_id = $setorB->id;
            $setorA->save();
        } finally {
            TenantContext::forget();
        }
    }
}
