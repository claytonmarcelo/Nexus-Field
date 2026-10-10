<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tickets\Concerns\EnxergaOChamado;
use App\Models\Ticket;
use App\Services\Recusa;
use App\Services\Tickets\ConversaDeChamado;
use App\Support\TextoSeguro;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Nota da conversa de um chamado. Todo papel que lê o chamado pode responder — é
 * por isso que a rota está em `tickets.view`, não em `tickets.create`: a conta de
 * cliente não cria documento nenhum, ela continua a conversa que abriu.
 *
 * A marca de nota interna é do escritório: sem `tickets.update` ela não é nem
 * aceita, nem lida, nem desenhada na tela. Quem aplica essa régua e quem grava a
 * linha é `ConversaDeChamado`; aqui fica a validação do campo e a cara da resposta.
 */
class TicketCommentController extends Controller
{
    use EnxergaOChamado;

    public function __construct(private readonly ConversaDeChamado $conversa) {}

    public function store(Request $request, Ticket $chamado): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($chamado, $usuario);

        $validado = $request->validate([
            'nota' => ['required', 'string', 'max:20000', $this->textoComPalavras()],
        ], [
            'nota.required' => 'Nota vazia não é resposta para quem está esperando.',
        ]);

        try {
            $nota = $this->conversa->responder(
                $chamado,
                $usuario,
                (string) $validado['nota'],
                $request->boolean('interna'),
            );
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return back()->with('status', $this->conversa->resumo($nota));
    }

    /**
     * O editor entrega HTML, e um parágrafo vazio é HTML de sobra: `required` passa
     * por ele. A prova do que foi escrito é o texto que sobra depois de tirar o
     * rótulo.
     */
    private function textoComPalavras(): Closure
    {
        return function (string $atributo, $valor, Closure $falha): void {
            if (TextoSeguro::textoPlano(TextoSeguro::sanitizar((string) $valor)) === '') {
                $falha('O texto não passou do formato vazio: escreva o que a outra parte precisa ler.');
            }
        };
    }
}
