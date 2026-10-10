<?php

namespace App\Services\Agenda;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Interrompe uma ação sobre a solicitação (aprovar, recusar ou editar) quando ela não pode seguir.
 * A transação é desfeita e a resposta é 422 com a mensagem e, quando houver, os conflitos.
 */
class SolicitacaoInvalidaException extends RuntimeException
{
    /**
     * @param  list<array{tipo: string, mensagem: string}>  $conflitos
     */
    public function __construct(string $mensagem, public readonly array $conflitos = [])
    {
        parent::__construct($mensagem);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'conflitos' => $this->conflitos,
        ], 422);
    }
}
