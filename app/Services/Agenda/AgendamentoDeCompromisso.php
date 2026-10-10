<?php

namespace App\Services\Agenda;

use App\Models\Appointment;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Recusa;
use App\Support\StatusCatalog;
use Illuminate\Support\Carbon;

/**
 * A escrita do compromisso: o degrau entre o calendário e o banco.
 *
 * A agenda é a única tela desta casa que escreve em duas linguagens. O formulário devolve
 * 302 com o recado na manga; o arraste de um bloco devolve JSON, porque quem move a janela
 * está olhando o quadro e não uma página nova. As duas saem pela mesma caneta — o que está
 * certo é um só — e a fachada só decide em que idioma a resposta chega.
 *
 * **O estado não se digita, se conduz.** `agendar()` escreve o cadastro e deixa a linha
 * nascer agendada; `mudarStatus()` é o único caminho que mexe em `status`, e ele confere as
 * duas réguas que a tela também usa: a máquina de estados (`Appointment::FLUXO`, concluído
 * é terminal porque reabrir seria reescrever um dia que já passou) e quem conduz a escala
 * (`agenda.update`). `proximosEstados()` mora aqui de propósito — é a mesma lista que desenha
 * os botões da ficha, então a régua que oferece o passo é a que barra o atalho pela URL.
 *
 * **A carteira e o cliente herdado mandam.** Quem tem vínculo de cliente escreve na carteira
 * dele, e o `client_id` de um compromisso preso a uma ordem ou a um chamado vem do que ele
 * prende, não do que a tela desenhou — agenda dizendo um cliente e ficha dizendo outro é
 * como se inventa uma visita que ninguém marcou.
 *
 * **Arrastar não é reescrever a janela.** Dia inteiro desloca pelos dias inteiros entre a
 * queda e a origem, preservando a hora e a duração que já estavam gravadas; com hora, o
 * arraste simples entrega só o novo início e a duração gravada continua mandando — o resize
 * é que manda os dois lados.
 *
 * **Concluído é fato passado**, e as duas frases que dizem isso não são a mesma escrita duas
 * vezes: a do cadastro cai num 302 para quem abriu o formulário, a do arraste é curta porque
 * vive num 422 que o calendário mostra sem recarregar nada.
 */
final class AgendamentoDeCompromisso
{
    /**
     * Marca a janela, e o estado de entrada é escrito aqui: `scheduled`. Uma janela recém
     * marcada não pode nascer concluída nem cancelada, porque esses estados são fatos que
     * já aconteceram. Mover estado é verbo de `mudarStatus()`, com a régua do fluxo.
     *
     * @param  array<string, mixed>  $dados  o que o formulário validou
     */
    public function agendar(array $dados, User $autor): Appointment
    {
        $preparado = $this->prepara($dados, $autor);

        // O formulário não tem campo de estado e a rota também não manda um. Ainda assim
        // a porta se fecha aqui: um `status` entrado no payload viraria visita concluída
        // sem visita, sem check-in e sem trabalho contado.
        $preparado['status'] = 'scheduled';

        return Appointment::query()->create($preparado);
    }

    /**
     * Reescreve o cadastro de uma janela que ainda pode mudar. O estado não passa por
     * aqui: um cancelado que volta a agendado é condução, e condução tem régua — o verbo
     * certo é `mudarStatus()`, que confere o fluxo antes de escrever.
     *
     * @param  array<string, mixed>  $dados
     */
    public function alterar(Appointment $compromisso, array $dados, User $autor): Appointment
    {
        $this->garantirEditavel($compromisso);

        $compromisso->update($this->prepara($dados, $autor));

        return $compromisso;
    }

    /**
     * Conduz o estado e devolve os dois lados da travessia em vocabulário cru, porque a
     * frase que a tela mostra — `agendado → concluído` — é formada com o rótulo do catálogo,
     * e rótulo é apresentação.
     *
     * @return array{de: string, para: string}
     *
     * @throws Recusa no salto que o fluxo não tem, ou no passo de quem não conduz a escala
     */
    public function mudarStatus(Appointment $compromisso, User $autor, string $destino): array
    {
        if (! $compromisso->podeMudarPara($destino)) {
            throw new Recusa(sprintf(
                'O compromisso está “%s” e não pode ir para “%s”: o fluxo da agenda é o que vale.',
                StatusCatalog::label('appointment', $compromisso->status),
                StatusCatalog::label('appointment', $destino),
            ));
        }

        if (! array_key_exists($destino, $this->proximosEstados($compromisso, $autor))) {
            throw new Recusa('Esta conta não conduz o estado de um compromisso da agenda.');
        }

        $origem = $compromisso->status;
        $compromisso->update(['status' => $destino]);

        return ['de' => $origem, 'para' => $destino];
    }

    /**
     * Move a janela que o calendário entregou. `inicio` é obrigatório, `fim` só vem quando
     * o gesto foi redimensionar; sem ele, a duração gravada é que decide o fim.
     *
     * @param  array<string, mixed>  $validado
     *
     * @throws Recusa na janela torta e no dia que já passou
     */
    public function reagendar(Appointment $compromisso, array $validado): Appointment
    {
        if ($compromisso->estaTravado()) {
            throw new Recusa('Compromisso concluído não muda mais de janela.');
        }

        $novoInicio = Carbon::parse($validado['inicio']);

        if ($compromisso->all_day) {
            // Um compromisso de dia inteiro não tem hora a mover: o que o arraste diz é em
            // que dia ele cai. Desloca a janela inteira pelos dias de diferença, preservando
            // a duração que já estava gravada. O `copy()` não é ornamento: sem ele a hora
            // original seria zerada na própria modelo.
            $dias = $compromisso->starts_at->copy()->startOfDay()
                ->diffInDays($novoInicio->copy()->startOfDay(), false);

            $compromisso->update([
                'starts_at' => $compromisso->starts_at->copy()->addDays((int) $dias),
                'ends_at' => $compromisso->ends_at->copy()->addDays((int) $dias),
            ]);

            return $compromisso;
        }

        $fimBruto = $validado['fim'] ?? null;
        $novoFim = $fimBruto !== null
            ? Carbon::parse($fimBruto)
            : $novoInicio->copy()->addMinutes($compromisso->minutos());

        if (! $novoFim->greaterThan($novoInicio)) {
            throw new Recusa('A janela terminou antes de começar: arraste de novo.');
        }

        $compromisso->update(['starts_at' => $novoInicio, 'ends_at' => $novoFim]);

        return $compromisso;
    }

    /**
     * Tira a janela da escala. O trabalho que ela prendia não sai com ela: ordem e chamado
     * continuam onde estavam, porque apagar um recado do calendário nunca foi verbo de
     * operação.
     */
    public function apagar(Appointment $compromisso): void
    {
        $compromisso->delete();
    }

    /**
     * Os estados que esta conta pode conduzir a partir do atual, em slug => rótulo. É a
     * lista que a ficha desenha e a mesma que `mudarStatus()` confere — a régua do botão e a
     * da rota não podem ser duas contas diferentes.
     *
     * @return array<string, string>
     */
    public function proximosEstados(Appointment $compromisso, User $autor): array
    {
        if (! $autor->hasPermission('agenda.update')) {
            return [];
        }

        return collect(Appointment::FLUXO[$compromisso->status] ?? [])
            ->mapWithKeys(fn (string $destino) => [$destino => StatusCatalog::label('appointment', $destino)])
            ->all();
    }

    /**
     * A porta do cadastro: janela concluída não se edita. A rota de leitura que desenha o
     * formulário também passa por aqui, para o formulário nunca abrir prometendo uma
     * escrita que o serviço já recusou.
     *
     * @throws Recusa
     */
    public function garantirEditavel(Appointment $compromisso): void
    {
        if ($compromisso->estaTravado()) {
            throw new Recusa('Compromisso concluído é fato passado: a janela dele não se edita mais.');
        }
    }

    /**
     * O que a tela não decide, o servidor decide: a carteira de quem escreve e o cliente
     * herdado daquilo que o compromisso prende. A empresa entra pela trait, nunca pelo
     * payload.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function prepara(array $dados, User $autor): array
    {
        if ($autor->client_id !== null) {
            $dados['client_id'] = $autor->client_id;
        }

        // Prender o compromisso a uma ordem ou a um chamado sem levar o cliente junto
        // deixaria a agenda dizer uma coisa e a ficha dizer outra.
        if (! empty($dados['service_order_id'])) {
            $dados['client_id'] = ServiceOrder::query()->findOrFail($dados['service_order_id'])->client_id;
        } elseif (! empty($dados['ticket_id'])) {
            $dados['client_id'] = Ticket::query()->findOrFail($dados['ticket_id'])->client_id;
        }

        return $dados;
    }
}
