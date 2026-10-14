<?php

namespace App\Http\Controllers;

use App\Services\PrimeirosPassos\PrimeirosPassos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Escolhas da pessoa no roteiro de primeiros passos: pular um passo, adiar para amanhã, dispensar de vez ou avisar
 * que o link do formulário já foi enviado aos grupos.
 */
class PrimeirosPassosController extends Controller
{
    public function __construct(private readonly PrimeirosPassos $primeirosPassos) {}

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'acao' => ['required', Rule::in(PrimeirosPassos::ACOES)],
            'passo' => ['required_if:acao,pular', 'nullable', Rule::in($this->primeirosPassos->chaves())],
        ], [
            'acao.required' => 'Informe a ação.',
            'acao.in' => 'Ação desconhecida.',
            'passo.required_if' => 'Informe qual passo pular.',
            'passo.in' => 'Passo desconhecido.',
        ]);

        $this->primeirosPassos->aplicar($request->user(), $dados['acao'], $dados['passo'] ?? null);

        return back();
    }
}
