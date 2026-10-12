<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia as rotas do assistente enquanto a chave da OpenRouter não estiver configurada.
 */
class GarantirAssistenteConfigurado
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(filled(config('assistente.chave')), 503, 'O assistente não está configurado.');

        return $next($request);
    }
}
