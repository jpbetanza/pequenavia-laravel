<?php

namespace App\Http\Controllers\Assistente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistente\EnviarMensagemRequest;
use App\Models\Conversa;
use App\Services\Assistente\Anexos;
use App\Services\Assistente\Assistente;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recebe a pergunta do usuário e responde em stream SSE. Validação, autorização e a gravação dos anexos no bucket
 * rodam antes do stream começar.
 */
class MensagemController extends Controller
{
    public function __construct(
        private readonly Assistente $assistente,
        private readonly Anexos $anexos,
    ) {}

    public function store(EnviarMensagemRequest $request, Conversa $conversa): StreamedResponse
    {
        $anexos = $this->anexos->guardar($conversa, $request->anexos());

        return response()->eventStream(function () use ($request, $conversa, $anexos) {
            // Sem isso o PHP aborta o script quando o cliente fecha a aba, e o `finally` do gerador não grava o texto parcial.
            ignore_user_abort(true);

            foreach ($this->assistente->responder($request->user(), $conversa, $request->validated('texto'), $anexos) as $evento) {
                yield new StreamedEvent(event: $evento->tipo, data: $evento->dados);
            }
        }, ['X-Accel-Buffering' => 'no'], endStreamWith: null);
    }
}
