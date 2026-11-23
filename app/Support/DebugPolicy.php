<?php

namespace App\Support;

/**
 * Produção nunca mostra detalhe de exceção, mesmo que APP_DEBUG tenha ficado
 * ligado no .env ou no cache de configuração. Fora de produção a bandeira
 * continua obedecendo ao ambiente, para o diagnóstico local não perder valor.
 */
final class DebugPolicy
{
    public static function habilitado(bool $debug, string $ambiente): bool
    {
        return $debug && strtolower($ambiente) !== 'production';
    }
}
