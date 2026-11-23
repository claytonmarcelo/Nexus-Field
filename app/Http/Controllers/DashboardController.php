<?php

namespace App\Http\Controllers;

use App\Support\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user()->loadMissing(['roles', 'company.plan']);

        // O painel reunia dezenas de contagens no TiDB a cada abertura. Em banco
        // remoto isso dominava o tempo da página. O TTL curto mantém os números
        // vivos sem pagar o preço das consultas em cada clique. A chave inclui as
        // permissões para uma mudança de papel aparecer na hora.
        $chave = 'painel:v1:'.$usuario->id.':'.md5(implode('|', $usuario->permissionSlugs()));

        // Nos testes cada caso monta seu próprio banco e espera ler o que acabou
        // de gravar; o memo de produção esconderia essa mudança.
        $painel = app()->runningUnitTests()
            ? (new DashboardMetrics($usuario))->toArray()
            : Cache::remember($chave, now()->addSeconds(15), fn () => (new DashboardMetrics($usuario))->toArray());

        return view('dashboard', [
            'usuario' => $usuario,
            'painel' => $painel,
        ]);
    }
}
