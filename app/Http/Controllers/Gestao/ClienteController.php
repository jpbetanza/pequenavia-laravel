<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Gestao\EscopoDaGestao;
use App\Services\Gestao\PainelDeGestao;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clientes (paróquias) do painel de gestão: a lista com busca e o detalhe de cada uma.
 */
class ClienteController extends Controller
{
    public function __construct(
        private readonly PainelDeGestao $painel,
        private readonly EscopoDaGestao $escopo,
    ) {}

    public function index(Request $request): Response
    {
        $periodo = $this->painel->periodo($request->query('periodo'));
        $busca = $request->query('busca');

        return Inertia::render('gestao/clientes', [
            'periodo' => $periodo->dias,
            'busca' => is_string($busca) && trim($busca) !== '' ? trim($busca) : null,
            'clientes' => $this->painel->clientes($periodo, is_string($busca) ? $busca : null),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        // Gestores não são paróquias: o detalhe só existe para clientes, a não ser que o gestor peça para incluí-los.
        abort_if($user->admin && ! $this->escopo->incluiGestores(), 404);

        $periodo = $this->painel->periodo($request->query('periodo'));

        return Inertia::render('gestao/cliente', [
            'periodo' => $periodo->dias,
            'cliente' => $this->painel->cliente($user),
            'situacao' => $this->painel->situacao($user, $periodo),
            'configuracao' => $this->painel->configuracao($user),
            'cadastros' => $this->painel->cadastros($user),
            'reservas' => $this->painel->reservas($user, $periodo),
            'assistente' => $this->painel->assistente($user, $periodo),
            'casos' => $this->painel->casosDoCliente($user, $periodo),
            'trava' => $this->painel->trava($user),
            'linha_do_tempo' => $this->painel->linhaDoTempo($user),
            'conversas' => $this->painel->conversasRecentes($user),
        ]);
    }
}
