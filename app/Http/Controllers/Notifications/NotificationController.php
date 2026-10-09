<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Support\ListFilters;
use App\Support\Notifier;
use App\Support\StatusCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A central de notificações desta conta. A primeira regra aqui nem é permissão, é
 * posse: a listagem só lê o `user_id` de quem está logado, e o PATCH que marca uma
 * linha conferiu o dono outra vez antes de carimbar. `notifications.view` abre a
 * tela para qualquer papel da empresa — o que separa as bandejas é a coluna, não a
 * chave, e o sino do escritório nunca mostra o que a conta de cliente recebeu.
 *
 * Aviso não se apaga e não se esconde. Ele é um fato que alcançou uma conta num
 * momento, e "lida" é o único estado final que tem: a história fica na tela com o
 * carimbo da chegada e o da leitura, porque "quando avisaram" responde disputa
 * tanto quanto "o que aconteceu".
 */
class NotificationController extends Controller
{
    private const ORDENAVEIS = ['created_at', 'read_at', 'type', 'title'];

    private const FILTROS = ['busca', 'tipo', 'estado', 'inicio', 'fim'];

    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('notifications.index', [
            'avisos' => $this->consulta($request, $usuario)
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'created_at', 'desc'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'tipos' => StatusCatalog::options('notification'),
            'pendentes' => Notification::query()->where('user_id', $usuario->id)->unread()->count(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
        ]);
    }

    /**
     * Marcar uma linha lida. O aviso de outra conta não existe para esta tela: o
     * 404 aqui é o mesmo de uma linha que não há, sem mensagem que confirme que a
     * bandeja do lado existe — o servidor não dá mapa do que é dos outros.
     */
    public function marcar(Request $request, Notification $aviso): RedirectResponse
    {
        $usuario = $request->user();

        abort_unless((int) $aviso->user_id === (int) $usuario->id, 404);

        Notifier::marcarComoLida($aviso, $usuario);

        return back()->with('status', 'Aviso marcado como lido.');
    }

    public function marcarTodos(Request $request): RedirectResponse
    {
        $lidas = Notifier::marcarTudoComoLida($request->user());

        return $lidas > 0
            ? back()->with('status', $lidas === 1 ? 'Um aviso marcado como lido.' : "{$lidas} avisos marcados como lidos.")
            : back()->with('aviso', 'Nenhum aviso pendente de leitura.');
    }

    /** A consulta da tela. O dono entra antes de tudo: filtro nenhum atravessa a bandeja alheia. */
    private function consulta(Request $request, User $usuario): Builder
    {
        $query = Notification::query()->where('user_id', $usuario->id);

        $query = ListFilters::igual($query, $request, 'tipo', 'type', array_keys(StatusCatalog::options('notification')));
        $query = ListFilters::busca($query, $request, ['title', 'body']);

        match ((string) $request->query('estado')) {
            'pendente' => $query->unread(),
            'lida' => $query->whereNotNull('read_at'),
            default => null,
        };

        return ListFilters::periodo($query, $request, 'created_at');
    }
}
