<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\ReageARecusa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroRequest;
use App\Services\Auth\RegistradorDeAcesso;
use App\Services\Recusa;
use App\Support\Auditor;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Casca HTTP do cadastro público de acesso. A regra vive em `RegistradorDeAcesso`. */
class RegisterController extends Controller
{
    use ReageARecusa;

    public function __construct(private readonly RegistradorDeAcesso $registrador) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegistroRequest $request): RedirectResponse
    {
        try {
            $usuario = $this->registrador->registrar($request->validated());
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        // O registro autentica de imediato, como em qualquer site: sessão nova,
        // identificador trocado e tenant resolvido antes do primeiro painel.
        Auth::login($usuario);
        $request->session()->regenerate();
        TenantContext::resolveFromUser($usuario);

        Auditor::gravar('conta criada', $usuario, [], 'Cadastro público de acesso.');

        return redirect()->route('dashboard')
            ->with('status', 'Conta criada. Agora você pode cadastrar sua empresa em Cadastros › Empresas.');
    }
}
