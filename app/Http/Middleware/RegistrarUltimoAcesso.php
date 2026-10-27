<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda quando a pessoa abriu o app pela última vez. Conta o login e também a volta com o cookie
 * "lembrar de mim", que não dispara o evento de login. Grava no máximo uma vez por hora.
 */
class RegistrarUltimoAcesso
{
    private const INTERVALO_EM_MINUTOS = 60;

    /**
     * Rotas que o navegador consulta sozinho, sem ninguém usando o app.
     */
    private const IGNORADAS = ['versao'];

    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        $usuario = $request->user();

        if ($usuario instanceof User && ! $request->routeIs(self::IGNORADAS) && $this->desatualizado($usuario)) {
            // Direto na tabela, para não mexer no updated_at.
            DB::table('users')->where('id', $usuario->id)->update(['ultimo_acesso_em' => now()]);
            $usuario->setAttribute('ultimo_acesso_em', now());
            $usuario->syncOriginal();
        }

        return $resposta;
    }

    private function desatualizado(User $usuario): bool
    {
        return $usuario->ultimo_acesso_em === null
            || $usuario->ultimo_acesso_em->lt(now()->subMinutes(self::INTERVALO_EM_MINUTOS));
    }
}
