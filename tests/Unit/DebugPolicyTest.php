<?php

namespace Tests\Unit;

use App\Support\DebugPolicy;
use Tests\TestCase;

class DebugPolicyTest extends TestCase
{
    public function test_producao_ignora_app_debug_ligado(): void
    {
        $this->assertFalse(DebugPolicy::habilitado(true, 'production'));
        $this->assertFalse(DebugPolicy::habilitado(true, 'Production'));
        $this->assertFalse(DebugPolicy::habilitado(false, 'production'));
    }

    public function test_fora_de_producao_a_bandeira_continua_valendo(): void
    {
        $this->assertTrue(DebugPolicy::habilitado(true, 'local'));
        $this->assertTrue(DebugPolicy::habilitado(true, 'testing'));
        $this->assertFalse(DebugPolicy::habilitado(false, 'local'));
    }
}
