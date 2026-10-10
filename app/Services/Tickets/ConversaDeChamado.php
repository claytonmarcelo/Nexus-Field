<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Recusa;
use App\Support\TextoSeguro;

/**
 * A escrita da conversa de um chamado — o degrau entre o editor da ficha e o banco.
 *
 * O que mora aqui é a parte que não se pode fazer por atalho. O corpo entra sanitizado:
 * o HTML que o Summernote entrega não vira byte no banco do jeito que chegou, porque
 * nota de atendimento é lida por todo mundo que tem o chamado na tela. E a marca de
 * nota interna é do escritório: sem `tickets.update` o pedido de interna é descartado,
 * não aplicado — uma conta de cliente não grava linha que a própria consulta dela não
 * lê.
 *
 * Depois de fechado não há resposta: o terminal do chamado é o fim da conversa, e o que
 * surge em seguida é outro protocolo. Essa recusa vem como `Recusa` para a tela devolver
 * o motivo em 302, em vez de a escrita simplesmente sumir.
 */
final class ConversaDeChamado
{
    /**
     * Registra a nota. A conta que escreve resolve a autoria dos dois lados: usuário
     * sempre, e a carteira quando a conta é de cliente — é ela que a ficha mostra
     * primeiro, e ela que diz de quem a nota saiu.
     *
     * @throws Recusa quando o chamado já terminou a conversa
     */
    public function responder(Ticket $chamado, User $autor, string $corpo, bool $pedeInterna): TicketComment
    {
        if ($chamado->estaEncerrado()) {
            throw new Recusa(sprintf(
                'O chamado %s está fechado: a conversa dele terminou. O que surgiu depois é outro protocolo.',
                $chamado->protocol,
            ));
        }

        return $chamado->comments()->create([
            'user_id' => $autor->id,
            'client_id' => $autor->client_id,
            'body' => TextoSeguro::sanitizar($corpo),
            'is_internal' => $pedeInterna && $autor->hasPermission('tickets.update'),
        ]);
    }

    /**
     * A frase que a tela mostra depois do registro. Ela é do serviço porque a
     * apresentação não pode adivinhar se a interna que o formulário pediu foi aplicada
     * ou descartada: quem decidiu isso foi a régua acima.
     */
    public function resumo(TicketComment $nota): string
    {
        return $nota->is_internal
            ? 'Nota interna registrada no chamado.'
            : 'Resposta enviada no chamado.';
    }
}
