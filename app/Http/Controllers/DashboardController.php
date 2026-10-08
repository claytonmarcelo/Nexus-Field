<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Primeira tela autenticada: quem entrou, em qual empresa, com que papel, e o
 * que já existe de base. Nenhum número aqui é decorativo — todos saem de uma
 * consulta ao MySQL. KPIs operacionais chegam com os módulos que os produzem.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user()->loadMissing(['roles', 'company.plan']);

        $base = [
            'usuarios' => User::query()->count(),
            'clientes' => Client::query()->count(),
            'papeis' => Role::query()->count(),
            'permissoes' => Permission::query()->count(),
        ];

        return view('dashboard', [
            'usuario' => $usuario,
            'base' => $base,
        ]);
    }
}
