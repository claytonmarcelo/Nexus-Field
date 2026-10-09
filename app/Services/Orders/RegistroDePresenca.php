<?php

namespace App\Services\Orders;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\Technician;
use App\Models\User;
use App\Services\Recusa;
use App\Support\Auditor;
use App\Support\Distancia;
use App\Support\Formatters;
use Illuminate\Support\Facades\DB;

/**
 * A escrita da presença em campo — o degrau entre o formulário de chegada/saída e o
 * banco, no módulo que o desenho chama de serviço.
 *
 * O que mora aqui é a parte que não se pode fazer por atalho. A visita nasce do
 * relógio do aplicativo, nunca do horário que o navegador enviou; a distância se mede
 * deste lado, entre a coordenada lida e o endereço gravado na ordem, porque um
 * `distancia` vindo do cliente seria a prova fabricada pelo próprio interessado; e a
 * chegada abre a execução pelo fluxo da ordem — o mesmo caminho do botão de estado,
 * com passagem e autor na trilha — em vez de trocar a coluna por fora. Tudo isso cabe
 * numa transação, com o carimbo de auditoria logo depois.
 *
 * Quem esteve lá, e com que permissão, é da camada de cima: a tela resolve o autor e
 * o escreve aqui já resolvido. Não existe edição nem exclusão de carimbo: o que o
 * campo mediu é histórico.
 */
final class RegistroDePresenca
{
    /** Estados de ordem que aceitam presença em campo: rascunho ainda não saiu da mesa. */
    private const ORDENS_ACEITAM_CHEGADA = ['open', 'in_progress', 'on_hold'];

    /**
     * Chegada: cria a visita, mede o vão até o endereço e leva a ordem para a
     * execução pelo fluxo. Recusa com `erro` quando a ordem não tem mais campo para
     * pisar, e com `aviso` quando aquele técnico já está lá dentro — duplicata de
     * passagem aberta não é histórico.
     *
     * @param  array<string, mixed>  $posicao  latitude, longitude e relato do aparelho
     */
    public function chega(ServiceOrder $ordem, Technician $tecnico, User $autor, array $posicao): ServiceOrderCheckin
    {
        if (! in_array($ordem->status, self::ORDENS_ACEITAM_CHEGADA, true)) {
            throw new Recusa($ordem->estaEncerrada()
                ? sprintf(
                    'A ordem %s já foi encerrada: presença em campo não se registra depois do fim do trabalho.',
                    $ordem->number,
                )
                : sprintf(
                    'A ordem %s ainda é rascunho: libere-a para o campo antes de registrar a chegada.',
                    $ordem->number,
                ));
        }

        if ($ordem->checkins()->where('technician_id', $tecnico->id)->open()->exists()) {
            throw Recusa::aviso(sprintf(
                '%s já está em campo nesta ordem: registre a saída antes de marcar uma nova chegada.',
                $tecnico->name,
            ));
        }

        $medida = Distancia::metros(
            $posicao['latitude'] ?? null,
            $posicao['longitude'] ?? null,
            $ordem->latitude,
            $ordem->longitude,
        );

        $visita = DB::transaction(function () use ($ordem, $autor, $tecnico, $posicao, $medida): ServiceOrderCheckin {
            $visita = ServiceOrderCheckin::query()->create([
                'service_order_id' => $ordem->id,
                'technician_id' => $tecnico->id,
                'checkin_at' => now(),
                'checkin_latitude' => $posicao['latitude'] ?? null,
                'checkin_longitude' => $posicao['longitude'] ?? null,
                'checkin_distance' => $medida,
                'status' => 'open',
                'observation' => $posicao['observacao'] ?? null,
            ]);

            // A chegada abre a execução pelo fluxo da ordem, com a passagem e o
            // autor na trilha — o mesmo caminho do botão de estado, não um atalho
            // que troca a coluna por fora.
            if ($ordem->podeMudarPara('in_progress')) {
                $ordem->mudarStatus('in_progress', $autor, 'Chegada registrada em campo pelo check-in.');
            }

            return $visita;
        });

        Auditor::gravar('chegada em campo', $visita, [], sprintf(
            '%s: %s chegou ao local%s.',
            $ordem->number,
            $tecnico->name,
            $this->resumoDaMedida($medida, $ordem),
        ));

        return $visita;
    }

    /**
     * Saída: encerra a passagem e mede o segundo ponto, mas não encerra a ordem — ir
     * embora não é terminar o serviço. Relato vazio não apaga o que a chegada
     * registrou, e repetir a saída devolve aviso em vez de reescrever o carimbo.
     *
     * @param  array<string, mixed>  $posicao
     *
     * @throws Recusa
     */
    public function sai(ServiceOrderCheckin $visita, ServiceOrder $ordem, array $posicao): ServiceOrderCheckin
    {
        if (! $visita->estaAberto()) {
            throw Recusa::aviso(sprintf(
                'A saída desta visita já foi registrada em %s.',
                Formatters::dateTime($visita->checkout_at),
            ));
        }

        $medida = Distancia::metros(
            $posicao['latitude'] ?? null,
            $posicao['longitude'] ?? null,
            $ordem->latitude,
            $ordem->longitude,
        );

        $visita->update([
            'checkout_at' => now(),
            'checkout_latitude' => $posicao['latitude'] ?? null,
            'checkout_longitude' => $posicao['longitude'] ?? null,
            'checkout_distance' => $medida,
            'status' => 'closed',
            'observation' => filled($posicao['observacao'] ?? null)
                ? $posicao['observacao']
                : $visita->observation,
        ]);

        Auditor::gravar('saída em campo', $visita, [], sprintf(
            '%s: %s deixou o local após %s%s.',
            $ordem->number,
            $visita->technician->name,
            Formatters::duration($visita->duracaoMinutos()),
            $medida === null ? '' : sprintf(' (%s m do endereço)', Formatters::decimal($medida)),
        ));

        return $visita;
    }

    /** O endereço da ordem pode não ter coordenada: aí não há vão a medir, e a trilha diz isso. */
    private function resumoDaMedida(?float $medida, ServiceOrder $ordem): string
    {
        if ($medida === null) {
            return $ordem->latitude === null || $ordem->longitude === null
                ? ' — sem medida: o endereço da ordem não tem coordenada'
                : ' — sem posição lida no aparelho';
        }

        return sprintf(' (%s m do endereço)', Formatters::decimal($medida));
    }
}
