<?php

namespace Tests\Feature;

use App\Support\UserContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesFixtures;
use Tests\TestCase;

class UserContextTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_identidade_hidratada_não_volta_ao_banco_para_ler_permissões(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'contexto@test.local');

        UserContext::hydrate($user);

        $consultas = [];
        DB::listen(static function ($query) use (&$consultas): void {
            $consultas[] = $query->sql;
        });

        $this->assertTrue($user->hasPermission('dashboard.view'));
        $this->assertTrue($user->hasAnyPermission(['users.view', 'financeiro.nunca']));

        $consultasDeAcl = array_values(array_filter(
            $consultas,
            static fn (string $sql): bool => str_contains($sql, 'role_user') || str_contains($sql, 'permission_role'),
        ));

        $this->assertSame([], $consultasDeAcl);
    }

    public function test_invalidação_após_troca_de_papel_desfaz_a_identidade_em_memória(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('alfa');
        $user = $this->makeUser('administrator', $company, 'contexto@test.local');

        UserContext::hydrate($user);

        $this->assertTrue($user->hasPermission('dashboard.view'));

        $user->roles()->sync([]);
        UserContext::invalidate();

        $this->assertFalse($user->hasPermission('dashboard.view'));
    }
}
