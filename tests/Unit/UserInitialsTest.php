<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

/**
 * Iniciais do avatar.
 *
 * O avatar aparece no cabeçalho de toda tela autenticada, então nome com
 * parêntese, de uma palavra só ou acentuado não pode gerar glifo solto ("A(").
 */
class UserInitialsTest extends TestCase
{
    private function iniciais(string $nome): string
    {
        return (new User(['name' => $nome]))->initials();
    }

    public function test_nome_completo_usa_a_primeira_e_a_ultima_palavra(): void
    {
        $this->assertSame('MP', $this->iniciais('Marina Prado'));
        $this->assertSame('AO', $this->iniciais('Ana Clara de Oliveira'));
    }

    public function test_token_entre_parenteses_nao_entra_no_calculo(): void
    {
        $this->assertSame('AD', $this->iniciais('Administrador (demo)'));
    }

    public function test_nome_de_uma_palavra_só_usa_as_duas_primeiras_letras(): void
    {
        $this->assertSame('AN', $this->iniciais('Ana'));
    }

    public function test_acento_mantem_a_letra_com_acento(): void
    {
        $this->assertSame('CR', $this->iniciais('Cléber Ramos'));
    }

    public function test_nome_sem_letra_cai_na_marca(): void
    {
        $this->assertSame('NF', $this->iniciais('123 (456)'));
    }

    public function test_nome_vazio_usa_o_placeholder_da_conta(): void
    {
        $this->assertSame('US', $this->iniciais('   '));
    }
}
