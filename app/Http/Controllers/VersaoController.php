<?php

namespace App\Http\Controllers;

use App\Support\VersaoDoSite;
use Illuminate\Http\JsonResponse;

/**
 * Versão do site no ar, para a página aberta saber que precisa recarregar. Público e sem cache.
 */
class VersaoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['versao' => VersaoDoSite::atual()])
            ->header('Cache-Control', 'no-store');
    }
}
