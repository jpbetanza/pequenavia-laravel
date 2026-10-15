<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\TemaDePedido;
use Illuminate\Http\Request;

/**
 * Temas de pedido: lista, edição e junção. As ações chegam na Parte 4b.
 */
class TemaController extends Controller
{
    public function index(Request $request): never
    {
        abort(501);
    }

    public function update(Request $request, TemaDePedido $tema): never
    {
        abort(501);
    }

    public function juntar(Request $request, TemaDePedido $tema): never
    {
        abort(501);
    }
}
