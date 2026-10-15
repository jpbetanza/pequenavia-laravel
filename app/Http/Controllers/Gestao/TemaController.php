<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AtualizarTemaRequest;
use App\Http\Requests\Gestao\JuntarTemaRequest;
use App\Models\TemaDePedido;
use App\Services\Gestao\PainelDeGestao;
use App\Services\Gestao\TemasDaGestao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Temas de pedido: lista com a última rodada, edição e junção.
 */
class TemaController extends Controller
{
    public function __construct(
        private readonly PainelDeGestao $painel,
        private readonly TemasDaGestao $temas,
    ) {}

    public function index(Request $request): Response
    {
        $periodo = $this->painel->periodo($request->query('periodo'));
        $tema = $request->query('tema');

        return Inertia::render('gestao/temas', [
            'periodo' => $periodo->dias,
            'rodada' => $this->temas->rodada(),
            'temas' => $this->temas->temas($periodo),
            'selecionado' => $this->temas->selecionado($periodo, is_string($tema) && Str::isUuid($tema) ? $tema : null),
        ]);
    }

    public function update(AtualizarTemaRequest $request, TemaDePedido $tema): RedirectResponse
    {
        $tema->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tema atualizado.']);

        return back();
    }

    public function juntar(JuntarTemaRequest $request, TemaDePedido $tema): RedirectResponse
    {
        $destino = TemaDePedido::query()->findOrFail((string) $request->validated('destino_id'));

        $this->temas->juntar($tema, $destino);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tema juntado em {$destino->nome}."]);

        return back();
    }
}
