<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * O Google é o segundo fator neste caminho: o login por ele não passa pelo desafio de 2FA do Fortify.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->voltarAoLogin('Não foi possível entrar com o Google. Tente novamente.');
        }

        // O Google devolve email_verified=false para contas criadas com o email de outra pessoa.
        // Aceitar esse email permitiria assumir a conta de quem já usa o PequenaVia com ele.
        if (! Arr::get($google->getRaw(), 'email_verified')) {
            return $this->voltarAoLogin('O Google não confirmou o seu email. Confirme-o na sua conta Google e tente novamente.');
        }

        $user = User::firstWhere('google_id', $google->getId())
            ?? User::firstWhere('email', Str::lower($google->getEmail()));

        if ($user?->google_id !== null && $user->google_id !== $google->getId()) {
            return $this->voltarAoLogin('Este email já está vinculado a outra conta Google.');
        }

        $user ??= new User([
            'name' => $google->getName() ?: $google->getEmail(),
            'email' => $google->getEmail(),
        ]);

        $this->descartarCadastroNaoVerificado($user);

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar_url' => $google->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Quem se cadastrou com este email sem nunca verificá-lo pode não ser o dono dele. Como o Google acaba
     * de provar a posse, a senha e as sessões desse cadastro deixam de valer. Senão, quem cadastrou o email
     * de outra pessoa continuaria entrando na conta depois que o dono a vinculasse ao Google.
     */
    private function descartarCadastroNaoVerificado(User $user): void
    {
        if (! $user->exists || $user->hasVerifiedEmail()) {
            return;
        }

        $user->password = null;
        $user->remember_token = null;

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
    }

    private function voltarAoLogin(string $mensagem): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $mensagem]);

        return redirect()->route('login');
    }
}
