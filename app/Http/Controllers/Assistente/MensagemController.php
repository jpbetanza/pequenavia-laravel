<?php

namespace App\Http\Controllers\Assistente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistente\EnviarMensagemRequest;
use App\Models\Conversa;
use App\Services\Assistente\Assistente;
use Illuminate\Http\StreamedEvent;

/**
 * Recebe a pergunta do usuário e responde em stream SSE. Validação e autorização rodam antes do stream começar.
 */
class MensagemController extends Controller
{
    public function __construct(private readonly Assistente $assistente) {}

    public function store(EnviarMensagemRequest $request, Conversa $conversa)
    {
        return response()->eventStream(function () use ($request, $conversa) {
            foreach ($this->assistente->responder($request->user(), $conversa, $request->validated('texto')) as $evento) {
                yield new StreamedEvent(event: $evento->tipo, data: $evento->dados);
            }
        }, ['X-Accel-Buffering' => 'no'], endStreamWith: null);
    }
}
