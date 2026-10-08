<?php

namespace App\Http\Controllers;

use App\Support\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user()->loadMissing(['roles', 'company.plan']);

        return view('dashboard', [
            'usuario' => $usuario,
            'painel' => (new DashboardMetrics($usuario))->toArray(),
        ]);
    }
}
