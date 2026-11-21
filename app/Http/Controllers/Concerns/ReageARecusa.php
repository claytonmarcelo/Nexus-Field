<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Recusa;
use Illuminate\Http\RedirectResponse;

/**
 * Regra de negócio recusada não é erro 500: volta para a tela de origem com o
 * motivo escrito no mesmo idioma das outras mensagens do sistema.
 */
trait ReageARecusa
{
    protected function recusar(Recusa $recusa): RedirectResponse
    {
        return back()
            ->with('erro', $recusa->getMessage())
            ->withInput();
    }
}
