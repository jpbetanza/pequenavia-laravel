<?php

namespace App\Http\Controllers;

use App\Models\GeracaoCertificado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoricoController extends Controller
{
    /**
     * Mostra as 50 gerações mais recentes do usuário.
     */
    public function index(Request $request): Response
    {
        $geracoes = $request->user()->geracoes()
            ->with('modelo.campos:id,modelo_id,nome,created_at')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (GeracaoCertificado $geracao) => [
                'id' => $geracao->id,
                'modelo_id' => $geracao->modelo_id,
                'modelo_title' => $geracao->modelo_title,
                'campos_valores' => $geracao->campos_valores,
                'ordem_campos' => $geracao->modelo?->campos->pluck('nome')->values()->all() ?? [],
                'created_at' => $geracao->created_at,
            ]);

        return Inertia::render('historico/index', ['geracoes' => $geracoes]);
    }
}
