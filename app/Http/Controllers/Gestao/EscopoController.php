<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Gestao\EscopoDaGestao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Liga ou desliga a inclusão das contas de gestão no painel. Fica na sessão, para valer em todas as páginas.
 */
class EscopoController extends Controller
{
    public function __invoke(Request $request, EscopoDaGestao $escopo): RedirectResponse
    {
        $escopo->definir($request->boolean('incluir_gestores'));

        return back();
    }
}
