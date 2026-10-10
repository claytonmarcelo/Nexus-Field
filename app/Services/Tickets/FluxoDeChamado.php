<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Services\Recusa;
use App\Support\Notifier;
use App\Support\StatusCatalog;
use App\Support\TextoSeguro;
use Illuminate\Support\Facades\DB;

/**
 * A camada de serviço dos chamados: o degrau entre o formulário e o banco.
 *
 * O chamado é a voz do cliente registrada, e a escrita dele tem duas coisas que não
 * cabem num controller. Abrir são linhas que não se separam — o protocolo, que nasce
 * do contador sequencial da empresa sob trava, e a passagem que registra o nascimento
 * com quem abriu; uma delas fora da transação deixaria chamado sem origem na linha do
 * tempo da ficha. Fechar o meio do caminho é o mesmo cuidado ao contrário: estado,
 * carimbo e passagem juntos, e o sino de quem esperava a resposta depois.
 *
 * A máquina de estados mora no modelo (`Ticket::FLUXO`) e daqui também não sai. O
 * serviço acrescenta o que a máquina não sabe — se este autor pode mandar no passo, e
 * qual frase explica por que não pode — e é a mesma régua que oferece o botão na
 * tela, não uma segunda versão dela. Estado não se digita: `atualizar()` escreve o
 * cadastro, com o texto rico já sanitizado antes de virar byte, e deixa a travessia
 * para o caminho que tem nota, carimbo e permissão próprios.
 */
final class FluxoDeChamado
{
    /**
     * O nascimento do chamado: protocolo sequencial da empresa, estado aberto e a
     * passagem de origem, tudo numa transação — e o sino depois, quando já há
     * protocolo para linkar.
     *
     * @param  array<string, mixed>  $dados  o que a tela validou
     */
    public function abrir(User $autor, array $dados): Ticket
    {
        $chamado = DB::transaction(function () use ($dados, $autor): Ticket {
            $chamado = Ticket::create([
                ...$this->normalizar($dados, $autor),
                'protocol' => Ticket::proximoProtocolo(),
                'status' => 'open',
                'opened_at' => now(),
            ]);

            TicketStatusHistory::query()->create([
                'ticket_id' => $chamado->id,
                'user_id' => $autor->id,
                'from_status' => null,
                'to_status' => $chamado->status,
                'note' => 'Chamado aberto na tela de chamados.',
                'created_at' => now(),
            ]);

            return $chamado;
        });

        $this->avisarAbertura($chamado, $autor);

        return $chamado;
    }

    /**
     * Cadastro do chamado. O estado não está entre os campos aceitos: a travessia é do
     * botão com carimbo, e um `status` vindo do formulário morre antes daqui, na
     * validação da tela.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(Ticket $chamado, array $dados, User $autor): Ticket
    {
        $chamado->update($this->normalizar($dados, $autor));

        return $chamado;
    }

    /**
     * Aplicar um estado é a escrita completa: a máquina, o carimbo do momento e a
     * passagem registrada com quem fez. Devolve o estado de origem porque a tela conta
     * a travessia — "Aberto → Em atendimento" — e depois do write o chamado só sabe
     * para onde foi.
     *
     * @throws Recusa no salto que o fluxo não tem, ou no passo que a conta não conduz
     */
    public function mudarStatus(Ticket $chamado, User $autor, string $destino, ?string $nota = null): string
    {
        if (! $chamado->podeMudarPara($destino)) {
            throw new Recusa(sprintf(
                'O chamado %s está “%s” e não pode ir para “%s”: o fluxo do módulo é o que vale.',
                $chamado->protocol,
                StatusCatalog::label('ticket', $chamado->status),
                StatusCatalog::label('ticket', $destino),
            ));
        }

        if (! $autor->hasPermission('tickets.execute')) {
            throw new Recusa('Conduzir um chamado — tirar da fila, pausar, retomar — pede a permissão de atendimento.');
        }

        if (in_array($destino, Ticket::ESTADOS_APROVADOS, true) && ! $autor->hasPermission('tickets.close')) {
            throw new Recusa('Resolver e fechar um chamado pedem a permissão de encerramento, que esta conta não tem.');
        }

        $origem = $chamado->status;

        DB::transaction(function () use ($chamado, $destino, $autor, $nota): void {
            $chamado->mudarStatus($destino, $autor, $nota);
        });

        if ($destino === 'resolved') {
            $this->avisarResolucao($chamado, $autor, $nota);
        }

        return $origem;
    }

    /**
     * Estados que a ficha oferece como botão: o fluxo do modelo decide o caminho, a
     * permissão de atendimento decide se o passo é conduzível e a de encerramento
     * decide se o passo fecha. Para a conta de cliente a lista vem vazia, então nenhum
     * botão de estado é desenhado na tela dela. É a mesma régua que `mudarStatus()`
     * usa para deixar ou não passar.
     *
     * @return array<string, string> slug => rótulo
     */
    public function proximosEstados(Ticket $chamado, User $autor): array
    {
        if (! $autor->hasPermission('tickets.execute')) {
            return [];
        }

        $podeFechar = $autor->hasPermission('tickets.close');

        return collect(Ticket::FLUXO[$chamado->status] ?? [])
            ->reject(fn (string $destino) => in_array($destino, Ticket::ESTADOS_APROVADOS, true) && ! $podeFechar)
            ->mapWithKeys(fn (string $destino) => [$destino => StatusCatalog::label('ticket', $destino)])
            ->all();
    }

    /**
     * Nota obrigatória nos passos que encerram: resolver sem dizer o que foi feito e
     * fechar sem resolução registrada são duas maneiras de perder o que aconteceu. A
     * tela usa isto para montar a regra do campo; o serviço é quem sabe o passo.
     */
    public function notaObrigatoria(Ticket $chamado, string $destino): bool
    {
        return $destino === 'resolved' || ($destino === 'closed' && $chamado->status !== 'resolved');
    }

    /** A frase que explica a exigência, escrita uma vez só para o campo e para a trilha. */
    public function mensagemDaNota(string $destino): string
    {
        return $destino === 'resolved'
            ? 'Resolver um chamado pede o que foi feito: é a frase que o cliente vai ler.'
            : 'Fechar um chamado sem resolução registrada pede por que ele está sendo fechado.';
    }

    /**
     * O que a tela não decide, o serviço decide: a carteira de quem escreve é a dela,
     * seja o que vier do formulário, e o texto rico sai sanitizado antes de virar byte
     * no banco.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function normalizar(array $dados, User $autor): array
    {
        if ($autor->client_id !== null) {
            $dados['client_id'] = $autor->client_id;
        }

        if (array_key_exists('description', $dados)) {
            $dados['description'] = TextoSeguro::sanitizar($dados['description']);
        }

        return $dados;
    }

    /**
     * Chamado novo toca para quem pode atendê-lo: toda conta ativa da empresa com a
     * permissão `tickets.execute`. Um sino por chamado — a mesma permissão não dobra o
     * aviso, e quem abriu o chamado não ouve o ato que acabou de praticar.
     */
    private function avisarAbertura(Ticket $chamado, User $autor): void
    {
        Notifier::paraQuemPode('tickets.execute', 'chamdo.aberto', sprintf(
            'Chamado %s aberto: %s',
            $chamado->protocol,
            $chamado->subject,
        ), null, route('tickets.show', $chamado), [
            'ticket_id' => $chamado->id,
        ], $autor);
    }

    /**
     * Resolver é o único meio-de-percurso que toca sino: a conta de cliente dona do
     * chamado e o responsável apontado. A nota da resolução viaja como corpo do aviso
     * porque o que o outro lado espera é a resposta — o que foi feito — e não a notícia
     * burocrática de que um estado mudou.
     */
    private function avisarResolucao(Ticket $chamado, User $autor, ?string $nota): void
    {
        $link = route('tickets.show', $chamado);
        $título = sprintf('Chamado %s resolvido: %s', $chamado->protocol, $chamado->subject);

        $destinos = User::query()
            ->where('company_id', $chamado->company_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->where('id', $chamado->responsible_user_id)
                ->orWhere('client_id', $chamado->client_id))
            ->where('id', '!=', $autor->id)
            ->get();

        foreach ($destinos as $destino) {
            Notifier::para($destino, 'chamdo.resolvido', $título, $nota, $link, [
                'ticket_id' => $chamado->id,
            ]);
        }
    }
}
