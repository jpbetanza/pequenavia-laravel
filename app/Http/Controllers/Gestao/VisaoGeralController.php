<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Gestao\PainelDeGestao;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visão geral do painel de gestão: números do período, casos de uso, alertas e a classificação.
 */
class VisaoGeralController extends Controller
{
    private const CLIENTES_NA_VISAO = 8;

    public function __construct(private readonly PainelDeGestao $painel) {}

    public function __invoke(Request $request): Response
    {
        $periodo = $this->painel->periodo($request->query('periodo'));

        return Inertia::render('gestao/visao-geral', [
            'periodo' => $periodo->dias,
            'resumo' => $this->painel->resumo($periodo),
            'por_dia' => $this->painel->porDia($periodo),
            'casos' => $this->painel->casos($periodo),
            'alertas' => $this->painel->alertas($periodo),
            'clientes' => $this->painel->clientes($periodo, null, self::CLIENTES_NA_VISAO),
            'classificacao' => $this->painel->classificacao(),
        ]);
    }
}
