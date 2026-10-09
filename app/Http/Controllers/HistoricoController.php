<?php

namespace App\Http\Controllers;

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
        return Inertia::render('historico/index', [
            'geracoes' => $request->user()->geracoes()
                ->latest('created_at')
                ->limit(50)
                ->get(['id', 'modelo_id', 'modelo_title', 'campos_valores', 'created_at']),
        ]);
    }
}
