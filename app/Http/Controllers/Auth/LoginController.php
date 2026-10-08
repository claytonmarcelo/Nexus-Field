<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request, $credentials['email']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request, $credentials['email']));

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $credentials['email']));

        // Troca o ID da sessão após autenticar para impedir session fixation.
        $request->session()->regenerate();

        $user = $request->user();

        if ($user->status !== 'active') {
            $this->reject($request, 'Este usuário está inativo.');
        }

        $user->forceFill(['last_login_at' => now()])->save();
        TenantContext::resolveFromUser($user);

        if (! $user->company?->isActive()) {
            $this->reject($request, 'A assinatura desta empresa está inativa. Contate o suporte.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        TenantContext::forget();

        return redirect()->route('welcome');
    }

    /** Derruba sessão e contexto do tenant antes de recusar o acesso. */
    private function reject(Request $request, string $message): never
    {
        Auth::logout();
        TenantContext::forget();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return Str::lower($email).'|'.$request->ip();
    }

    private function ensureIsNotRateLimited(Request $request, string $email): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $email), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $email));

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas. Aguarde {$seconds} segundos.",
        ]);
    }
}
