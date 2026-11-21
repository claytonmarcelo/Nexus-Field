<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Alcance;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organograma lido do banco, não desenhado à mão. A estrutura é da empresa;
 * as pessoas que aparecem nela passam pela mesma política de alcance usada
 * nas ordens, chamados e folha de contas.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $ids = Alcance::idsUsuarios($usuario);

        $filiais = Branch::query()
            ->active()
            ->orderBy('name')
            ->with([
                'departments' => function ($query) use ($ids): void {
                    $query->active()->orderBy('path')->withCount([
                        'employments as pessoas_count' => function ($vinculos) use ($ids): void {
                            $vinculos->where('status', 'ativo')->whereNull('ended_at');

                            if ($ids !== null) {
                                $vinculos->whereIn('user_id', $ids);
                            }
                        },
                    ]);
                },
            ])
            ->get();

        return view('organization.index', [
            'filiais' => $filiais,
            'totais' => [
                'filiais' => $filiais->count(),
                'setores' => $filiais->sum(fn (Branch $filial) => $filial->departments->count()),
                'pessoas' => $filiais->sum(fn (Branch $filial) => $filial->departments->sum('pessoas_count')),
            ],
            'recortado' => $ids !== null,
        ]);
    }
}
