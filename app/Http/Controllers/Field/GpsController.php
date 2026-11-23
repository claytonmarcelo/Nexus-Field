<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrderCheckin;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * O mapa do campo: quem está na rua e por onde passou.
 *
 * As duas camadas vêm do banco da empresa, nunca do navegador. A posição atual
 * é a última que a ficha do técnico recebeu; a rota é a sequência de chegadas e
 * saídas já registradas nas ordens. O mapa só desenha — ele não inventa traçado
 * e não recebe coordenada de outro usuário.
 *
 * O alcance é o mesmo das visitas de campo: o técnico vê a própria escala, a
 * conta de cliente vê quem passou pelas ordens da carteira dela, e o escritório
 * vê a empresa. O compartilhamento de posição é voluntário e só escreve na
 * ficha de quem está logado: não existe parâmetro de técnico no request.
 */
class GpsController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $tecnicos = $this->tecnicos($usuario)
            ->with([
                'checkins' => function ($q) {
                    return $q
                        ->whereNotNull('checkin_latitude')
                        ->whereNotNull('checkin_longitude')
                        ->with('serviceOrder:id,number')
                        ->orderBy('checkin_at');
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Technician $tecnico) => $this->cartao($tecnico))
            ->values()
            ->all();

        return view('gps.index', [
            'tecnicos' => $tecnicos,
            'comPartilhar' => $usuario->technician !== null,
            'rotaCompartilhamento' => route('gps.localizacao'),
            'totalRotas' => collect($tecnicos)->sum(fn (array $t) => count($t['rota'])),
        ]);
    }

    /**
     * Última posição informada pelo próprio técnico. O navegador pede permissão
     * antes de chamar; aqui a porta é a ficha ligada à conta logada, então um
     * técnico não consegue mudar a localização do colega chutando outro id.
     */
    public function localizacao(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['required', 'numeric', 'between:-180,180', 'decimal:0,7'],
        ]);

        $tecnico = $request->user()->technician;

        abort_unless($tecnico !== null, 403, 'Somente quem tem ficha de campo compartilha posição.');

        $tecnico->forceFill([
            'latitude' => $validado['latitude'],
            'longitude' => $validado['longitude'],
            'last_location_at' => now(),
        ])->save();

        return response()->json([
            'status' => 'ok',
            'atualizado_em' => $tecnico->last_location_at->toIso8601String(),
        ]);
    }

    /** Quem aparece no mapa, com o mesmo recorte da listagem de visitas. */
    private function tecnicos(User $usuario): Builder
    {
        $query = Technician::query()->active();

        if ($usuario->client_id !== null) {
            return $query->whereHas(
                'checkins.serviceOrder',
                fn (Builder $ordem) => $ordem->where('client_id', $usuario->client_id),
            );
        }

        if ($tecnico = $usuario->technician) {
            return $query->whereKey($tecnico->id);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function cartao(Technician $tecnico): array
    {
        $rota = $tecnico->checkins
            ->flatMap(fn (ServiceOrderCheckin $visita) => $this->pontos($visita))
            ->sortBy('hora')
            ->values()
            ->all();

        $ultimo = $tecnico->last_location_at;
        $posicaoAtual = $tecnico->latitude !== null && $tecnico->longitude !== null
            ? [
                'lat' => (float) $tecnico->latitude,
                'lng' => (float) $tecnico->longitude,
            ]
            : null;

        return [
            'id' => $tecnico->id,
            'nome' => $tecnico->name,
            'funcao' => $tecnico->region !== null && $tecnico->region !== '' ? $tecnico->region : 'Campo',
            'situacao' => $tecnico->status,
            'posicao' => $posicaoAtual,
            'atualizado_em' => $ultimo?->toIso8601String(),
            'rota' => $rota,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function pontos(ServiceOrderCheckin $visita): array
    {
        $numero = $visita->serviceOrder?->number ?? 'Ordem removida';
        $pontos = [];

        if ($visita->checkin_latitude !== null && $visita->checkin_longitude !== null) {
            $pontos[] = [
                'lat' => (float) $visita->checkin_latitude,
                'lng' => (float) $visita->checkin_longitude,
                'hora' => $visita->checkin_at?->toIso8601String(),
                'ordem' => $numero,
                'tipo' => 'Chegada',
            ];
        }

        if ($visita->checkout_latitude !== null && $visita->checkout_longitude !== null) {
            $pontos[] = [
                'lat' => (float) $visita->checkout_latitude,
                'lng' => (float) $visita->checkout_longitude,
                'hora' => $visita->checkout_at?->toIso8601String(),
                'ordem' => $numero,
                'tipo' => 'Saída',
            ];
        }

        return $pontos;
    }
}
