<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Limpeza global dos caches compilados da aplicação.
 *
 * O botão do navbar só aparece para a raiz, e a rota repete a checagem com o
 * middleware `root`: esconder o controle nunca foi controle de acesso. O
 * comando `optimize:clear` apaga cache de dados, configuração, eventos, rotas e
 * views — todos os artefatos que podem ficar velhos depois de um deploy ou de
 * uma mudança de configuração — e cada execução entra na auditoria com autor e
 * endereço, porque o efeito alcança todas as empresas da instalação.
 */
class CacheController extends Controller
{
    public function clear(Request $request): RedirectResponse
    {
        $codigo = Artisan::call('optimize:clear');
        $saida = trim(Artisan::output());
        $etapas = $saida === '' ? 0 : count(preg_split('/\R+/', $saida) ?: []);

        Auditor::gravar(
            'cache limpa',
            'Aplicação',
            [],
            'Limpeza global executada pela conta raiz: '.$etapas.' etapas, código '.$codigo.'.'
        );

        return back()->with('status', 'Cache limpo. Nenhum dado de empresa, cliente ou ordem foi apagado — apenas configuração, rotas, eventos e views compiladas.');
    }
}
