<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Conversa;
use Illuminate\Http\Request;

/**
 * Conversas classificadas: lista, detalhe e correção. As ações chegam na Parte 4b.
 */
class ConversaController extends Controller
{
    public function index(Request $request): never
    {
        abort(501);
    }

    public function corrigir(Request $request, Conversa $conversa): never
    {
        abort(501);
    }
}
