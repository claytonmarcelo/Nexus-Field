<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\ReageARecusa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\EnviarDocumentoRequest;
use App\Http\Requests\Organization\IniciarCadastroRequest;
use App\Models\CompanyRegistration;
use App\Services\Cadastro\GestorDeCadastro;
use App\Services\Recusa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Casca HTTP do cadastro de empresas no painel. Toda decisão fica em `GestorDeCadastro`. */
class CompanyRegistrationController extends Controller
{
    use ReageARecusa;

    public function __construct(private readonly GestorDeCadastro $gestor) {}

    public function create(): View
    {
        return view('organization.registration.inicio');
    }

    public function store(IniciarCadastroRequest $request): RedirectResponse
    {
        try {
            $resultado = $this->gestor->iniciar(
                $request->validated(),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        // O token de acesso vai na sessão, não na URL: links copiados de e-mail
        // não carregam credencial de etapa, e o navegador não guarda o segredo no
        // histórico. O link de confirmação, com os dois tokens, vai por e-mail.
        $request->session()->put('cadastro.access_token', $resultado['access_token']);
        $request->session()->put('cadastro.protocolo', $resultado['registro']->protocol);

        return redirect()->route('cadastro.confirmar')
            ->with('status', 'Pedido registrado sob o protocolo '.$resultado['registro']->protocol.'. Enviamos um link de confirmação ao e-mail informado.');
    }

    public function confirmar(): View
    {
        return view('organization.registration.confirmar', [
            'protocolo' => session('cadastro.protocolo'),
        ]);
    }

    public function confirmarToken(Request $request, string $token): RedirectResponse
    {
        try {
            $this->gestor->confirmarEmail($token, $request->ip(), $request->userAgent());
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        $acesso = (string) $request->query('acesso', '');
        if ($acesso !== '') {
            $request->session()->put('cadastro.access_token', $acesso);
        }

        return redirect()->route('cadastro.documentos')
            ->with('status', 'E-mail confirmado. Agora envie os documentos para análise.');
    }

    public function documentos(Request $request): View|RedirectResponse
    {
        $token = (string) ($request->query('token') ?: $request->session()->get('cadastro.access_token', ''));

        if ($token === '') {
            return redirect()->route('cadastro.create')
                ->with('erro', 'Abra o cadastro para receber o link de acesso.');
        }

        try {
            $registro = $this->gestor->porTokenDeAcesso($token);
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        $request->session()->put('cadastro.access_token', $token);

        return view('organization.registration.documentos', [
            'registro' => $registro,
            'documentos' => $registro->documents()->latest()->get(),
        ]);
    }

    public function anexar(EnviarDocumentoRequest $request): RedirectResponse
    {
        $registro = $this->registroDaSessao($request);

        if ($registro === null) {
            return redirect()->route('cadastro.create')->with('erro', 'Sessão de cadastro expirada.');
        }

        try {
            $this->gestor->anexarDocumento(
                $registro,
                $request->file('arquivo'),
                (string) $request->validated('tipo'),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return back()->with('status', 'Documento recebido.');
    }

    public function aceitar(Request $request): RedirectResponse
    {
        $registro = $this->registroDaSessao($request);

        if ($registro === null) {
            return redirect()->route('cadastro.create')->with('erro', 'Sessão de cadastro expirada.');
        }

        $dados = $request->validate([
            'kind' => ['required', Rule::in(['termos_de_uso', 'politica_de_privacidade'])],
            'versao' => ['required', 'string', 'max:16'],
        ]);

        try {
            $this->gestor->registrarAceite(
                $registro,
                (string) $dados['kind'],
                (string) $dados['versao'],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        return back()->with('status', 'Aceite registrado.');
    }

    public function submeter(Request $request): RedirectResponse
    {
        $registro = $this->registroDaSessao($request);

        if ($registro === null) {
            return redirect()->route('cadastro.create')->with('erro', 'Sessão de cadastro expirada.');
        }

        try {
            $this->gestor->submeter($registro, $request->ip(), $request->userAgent());
        } catch (Recusa $recusa) {
            return $this->recusar($recusa);
        }

        $request->session()->forget('cadastro.access_token');

        return redirect()->route('cadastro.enviado', $registro->protocol)
            ->with('status', 'Cadastro enviado para análise.');
    }

    public function enviado(string $protocolo): View
    {
        $registro = CompanyRegistration::query()->where('protocol', $protocolo)->firstOrFail();

        return view('organization.registration.enviado', ['registro' => $registro]);
    }

    /** Busca o pedido pelo token guardado na sessão da etapa. */
    private function registroDaSessao(Request $request): ?CompanyRegistration
    {
        $token = (string) $request->session()->get('cadastro.access_token', '');

        if ($token === '') {
            return null;
        }

        try {
            return $this->gestor->porTokenDeAcesso($token);
        } catch (Recusa) {
            return null;
        }
    }
}
