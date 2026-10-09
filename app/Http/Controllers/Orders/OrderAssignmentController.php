<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderAssignment;
use App\Models\Technician;
use App\Support\Auditor;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quadro de comissão da ordem. A tabela `service_order_assignments` tem índice
 * único por (ordem, técnico), então a passagem de um técnico por uma ordem é uma
 * linha só: sair não apaga a linha, marca `released_at`, e voltar a colocar o
 * mesmo técnico reabre a mesma linha. É assim que "quem esteve nesta ordem" continua
 * respondível depois.
 */
class OrderAssignmentController extends Controller
{
    use EnxergaAOrdem;

    public function store(Request $request, ServiceOrder $ordem): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        if ($ordem->estaEncerrada()) {
            return $this->recusar($ordem);
        }

        $validado = $request->validate([
            'tecnico_id' => ['required', Rule::exists('technicians', 'id')
                ->where(fn ($consulta) => $consulta->where('company_id', TenantContext::id())
                    ->where('status', '<>', 'inactive')->whereNull('deleted_at')),
            ],
            'comissao_nota' => ['nullable', 'string', 'max:500'],
        ], [
            'tecnico_id.exists' => 'O técnico precisa estar na sua escala.',
        ]);

        $id = (int) $validado['tecnico_id'];

        if ($ordem->assignments()->where('technician_id', $id)->whereNull('released_at')->exists()) {
            return back()->with('aviso', 'Este técnico já está no quadro desta ordem.');
        }

        $comissao = $ordem->assignments()->where('technician_id', $id)->latest('assigned_at')->first();

        if ($comissao === null) {
            $comissao = ServiceOrderAssignment::query()->create([
                'service_order_id' => $ordem->id,
                'technician_id' => $id,
                'assigned_by' => $usuario->id,
                'assigned_at' => now(),
                'note' => $validado['comissao_nota'] ?? null,
            ]);
        } else {
            // Volta quem já saiu: a linha da passagem continua a mesma.
            $comissao->update([
                'released_at' => null,
                'assigned_by' => $usuario->id,
                'assigned_at' => now(),
                'note' => $validado['comissao_nota'] ?? null,
            ]);
        }

        if ($ordem->technician_id === null) {
            $ordem->update(['technician_id' => $comissao->technician_id]);
        }

        Auditor::gravar(
            'técnico comissionado',
            $ordem,
            [],
            sprintf('%s: %s entrou no quadro de comissão.', $ordem->number, $comissao->technician->name),
        );

        return back()->with('status', sprintf(
            '%s comissionado na ordem %s.',
            $comissao->technician->name,
            $ordem->number,
        ));
    }

    /**
     * Liberar não risca: `released_at` guarda a data de saída, e se o liberado era
     * o responsável, a ordem passa ao próximo do quadro que ainda está dentro — ou
     * fica sem responsável, que é o estado honesto de uma ordem sem ninguém.
     */
    public function destroy(Request $request, ServiceOrder $ordem, Technician $tecnico): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        if ($ordem->estaEncerrada()) {
            return $this->recusar($ordem);
        }

        $comissao = $ordem->assignments()
            ->where('technician_id', $tecnico->id)
            ->whereNull('released_at')
            ->first();

        if ($comissao === null) {
            return back()->with('aviso', sprintf('%s não está no quadro desta ordem.', $tecnico->name));
        }

        $comissao->update(['released_at' => now()]);

        if ((int) $ordem->technician_id === (int) $tecnico->id) {
            $restante = $ordem->assignments()
                ->where('technician_id', '!=', $tecnico->id)
                ->whereNull('released_at')
                ->orderBy('assigned_at')
                ->first();

            $ordem->update(['technician_id' => $restante?->technician_id]);
        }

        Auditor::gravar(
            'técnico liberado',
            $ordem,
            [],
            sprintf('%s: %s saiu do quadro de comissão.', $ordem->number, $tecnico->name),
        );

        return back()->with('status', sprintf(
            '%s liberado da ordem %s; a passagem ficou registrada.',
            $tecnico->name,
            $ordem->number,
        ));
    }

    private function recusar(ServiceOrder $ordem): RedirectResponse
    {
        return redirect()
            ->route('orders.show', $ordem)
            ->with('erro', sprintf(
                'A ordem %s está %s: o quadro de comissão dela é histórico e não se mexe mais.',
                $ordem->number,
                mb_strtolower(StatusCatalog::label('order', $ordem->status)),
            ));
    }
}
