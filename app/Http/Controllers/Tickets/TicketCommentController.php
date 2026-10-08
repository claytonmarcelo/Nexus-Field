<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tickets\Concerns\EnxergaOChamado;
use App\Models\Ticket;
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
 * aceita, nem lida, nem desenhada na tela.
 */
class TicketCommentController extends Controller
{
    use EnxergaOChamado;

    public function store(Request $request, Ticket $chamado): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($chamado, $usuario);

        if ($chamado->estaEncerrado()) {
            return back()->with('erro', sprintf(
                'O chamado %s está fechado: a conversa dele terminou. O que surgiu depois é outro protocolo.',
                $chamado->protocol,
            ));
        }

        $validado = $request->validate([
            'nota' => ['required', 'string', 'max:20000', $this->textoComPalavras()],
        ], [
            'nota.required' => 'Nota vazia não é resposta para quem está esperando.',
        ]);

        $interna = $usuario->hasPermission('tickets.update') && $request->boolean('interna');

        $chamado->comments()->create([
            'user_id' => $usuario->id,
            // Em conta de cliente a coluna já veio com o usuário: é ela que diz de
            // qual carteira a nota saiu, e é ela que a ficha mostra primeiro.
            'client_id' => $usuario->client_id,
            'body' => TextoSeguro::sanitizar($validado['nota']),
            'is_internal' => $interna,
        ]);

        return back()->with('status', $interna
            ? 'Nota interna registrada no chamado.'
            : 'Resposta enviada no chamado.');
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
