<?php

namespace App\Http\Controllers\Deploy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Endpoint único do cron da Vercel.
 *
 * O agendador do Laravel continua sendo a fonte da verdade: esta rota apenas
 * dispara `schedule:run` quando a Vercel bate de volta na aplicação. Ela não
 * usa sessão nem contexto de tenant e falha fechada quando CRON_SECRET não está
 * configurado — um cron aberto permitiria disparar varreduras de qualquer IP.
 */
final class CronController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('deploy.cron_secret');
        $token = $this->bearerToken($request->header('Authorization'));

        if (! is_string($secret) || $secret === '' || $token === '' || ! hash_equals($secret, $token)) {
            Log::warning('cron.schedule.recusado', [
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'motivo' => (! is_string($secret) || $secret === '')
                    ? 'segredo_nao_configurado'
                    : 'credencial_invalida',
            ]);

            return response('Nao autorizado', 401);
        }

        $resultado = Artisan::call('schedule:run');

        if ($resultado !== 0) {
            Log::error('cron.schedule.falhou', ['codigo' => $resultado]);

            return response('Falha ao executar o agendador', 500);
        }

        return response('OK', 200);
    }

    /** Aceita somente `Authorization: Bearer <token>`, case-insensitive no esquema. */
    private function bearerToken(mixed $authorization): string
    {
        if (! is_string($authorization)
            || ! preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches)) {
            return '';
        }

        return $matches[1];
    }
}
