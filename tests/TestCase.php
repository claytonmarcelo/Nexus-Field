<?php

namespace Tests;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A armadilha é real e já mordeu: `config:cache` grava bootstrap/cache/config.php
        // e esse arquivo vence os <env> do phpunit.xml — com ele na pasta, a suíte roda
        // contra o banco que estiver no .env, e o RefreshDatabase varre o desenvolvimento.
        // Nenhum teste desta casa toca schema que não termine em `_test`. Puro, óbvio,
        // e grita antes do primeiro migrate.
        $banco = (string) config('database.connections.mysql.database');

        if (! str_ends_with($banco, '_test')) {
            $this->fail(
                'Os testes se recusam a rodar contra o banco "'.$banco.'". '
                .'Há cache de configuração ativo (bootstrap/cache/config.php) com o .env de '
                .'desenvolvimento: rode `php artisan optimize:clear` e repita. A suíte pertence '
                .'ao nexusfield_test, e o RefreshDatabase apaga o que estiver pela frente.'
            );
        }
    }

    protected function tearDown(): void
    {
        // O contexto do tenant é estático: sem limpar, um teste invadiria o outro.
        TenantContext::forget();

        parent::tearDown();
    }
}
