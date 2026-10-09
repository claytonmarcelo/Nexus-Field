<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * A conta olhando para si mesma no espelho.
 *
 * Não tem permissão no caminho de propósito: perfil não é módulo da empresa, é a
 * única coisa que pertence de fato a quem está logado. O que ele pode mexer é
 * estreito e caro — nome, telefone, a chave de acesso (o e-mail) e a senha —, e
 * justamente por isso a senha atual entra como prova nos dois últimos formulários.
 * Sem essa prova, um computador deixado aberto vira a porta para assumir a
 * identidade alheia: o e-mail troca, a senha troca, e o dono original fica do lado
 * de fora da própria conta.
 *
 * A conta raiz tem a mesma tela e a mesma guarda do resto do sistema: o modelo
 * recusa o e-mail trocado dela, então a recusa chega aqui antes de virar 500.
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $conta = $request->user();

        return view('profile.index', [
            'conta' => $conta->load(['roles', 'company.plan', 'client', 'technician.teams']),
            'janelas' => $this->proximasJanelas($conta),
        ]);
    }

    /**
     * Nome e telefone: como a pessoa se apresenta para a casa. Nada aqui é chave
     * de acesso, então nada aqui pede senha.
     */
    public function update(Request $request): RedirectResponse
    {
        $conta = $request->user();

        $validado = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Sem nome, a casa não sabe com quem está falando.',
        ]);

        $mudancas = [];

        if ($conta->name !== $validado['name']) {
            $mudancas['name'] = [$conta->name, $validado['name']];
        }

        if ((string) $conta->phone !== (string) ($validado['phone'] ?? '')) {
            $mudancas['phone'] = [$conta->phone, $validado['phone'] ?? null];
        }

        if ($mudancas === []) {
            return redirect()->route('profile.show')->with('info', 'Nada mudou: a ficha está igual.');
        }

        $conta->update([
            'name' => $validado['name'],
            'phone' => $validado['phone'] ?? null,
        ]);

        Auditor::gravar(
            'perfil atualizado',
            $conta,
            $mudancas,
            sprintf('%s revisou os próprios dados de apresentação.', $conta->name),
        );

        return redirect()->route('profile.show')->with('status', 'Seus dados foram atualizados.');
    }

    /**
     * A chave de acesso. Muda com a senha atual na mesa, porque o e-mail é o que o
     * login digita: trocá-lo sem prova é a forma mais limpa de tomar uma conta.
     */
    public function email(Request $request): RedirectResponse
    {
        $conta = $request->user();

        $validado = $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($conta->id)],
            'senha_atual' => ['required', 'current_password'],
        ], [
            'email.unique' => 'Este e-mail já é a chave de outra conta.',
            'senha_atual.required' => 'Trocar a chave de acesso exige a senha atual.',
            'senha_atual.current_password' => 'A senha atual não confere: sem ela o e-mail fica onde está.',
        ]);

        if ($conta->isRoot()) {
            return back()
                ->with('erro', 'A conta raiz não troca de e-mail: ela é a identidade administrativa do sistema.')
                ->withInput();
        }

        $novo = Str::lower(trim($validado['email']));

        if ($novo === Str::lower($conta->email)) {
            return back()->with('info', 'A chave já é essa aí.');
        }

        $antigo = $conta->email;
        $conta->update(['email' => $novo]);

        Auditor::gravar(
            'chave de acesso alterada',
            $conta,
            ['email' => [$antigo, $novo]],
            sprintf('%s trocou o próprio e-mail de acesso.', $conta->name),
        );

        return redirect()->route('profile.show')
            ->with('status', 'Chave atualizada: é este e-mail que se digita no login daqui para frente.');
    }

    /**
     * Senha própria. Além de gravar a nova, derruba as outras sessões da conta:
     * quem está com a senha antiga em outro dispositivo perde o acesso na hora,
     * que é exatamente o motivo pelo qual alguém renova uma senha.
     */
    public function password(Request $request): RedirectResponse
    {
        $conta = $request->user();

        $validado = $request->validate([
            'senha_atual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:senha_atual', Password::min(10)->letters()->numbers()],
        ], [
            'senha_atual.current_password' => 'A senha atual não confere.',
            'password.different' => 'A senha nova precisa ser diferente da atual.',
            'password.min' => 'A senha nova precisa de pelo menos 10 caracteres.',
        ]);

        $conta->update(['password' => $validado['password']]);

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $conta->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        Auditor::gravar(
            'senha alterada',
            $conta,
            [],
            sprintf('%s renovou a própria senha; as demais sessões da conta foram encerradas.', $conta->name),
        );

        return redirect()->route('profile.show')
            ->with('status', 'Senha renovada. Esta sessão continua aberta; as outras foram para a rua.');
    }

    /**
     * As janelas que ainda vão acontecer na mão de quem conduz o campo. Perfil de
     * técnico sem agenda seria retrato de parede: aqui a tela puxa do mesmo banco
     * que o calendário puxa, e aponta para a mesma ficha.
     *
     * @return Collection<int, Appointment>
     */
    private function proximasJanelas(User $conta): Collection
    {
        if ($conta->technician === null) {
            return Collection::make();
        }

        return Appointment::query()
            ->where('technician_id', $conta->technician->id)
            ->scheduled()
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->with(['client', 'serviceOrder'])
            ->limit(5)
            ->get();
    }
}
