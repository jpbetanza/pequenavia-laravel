<?php

namespace App\Http\Controllers\Assistente;

use App\Http\Controllers\Controller;
use App\Services\Assistente\SugestoesDoAssistente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * As sugestões de uma conversa vazia, escolhidas pela situação da agenda.
 */
class SugestoesController extends Controller
{
    public function __invoke(Request $request, SugestoesDoAssistente $sugestoes): JsonResponse
    {
        return response()->json($sugestoes->para($request->user()));
    }
}
