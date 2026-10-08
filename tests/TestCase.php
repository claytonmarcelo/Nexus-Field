<?php

namespace Tests;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // O contexto do tenant é estático: sem limpar, um teste invadiria o outro.
        TenantContext::forget();

        parent::tearDown();
    }
}
