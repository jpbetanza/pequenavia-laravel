<?php

namespace App\Services\Agenda;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Interrompe a gravação de reservas quando alguma data tem conflito. A transação é desfeita e a resposta é 422 com os conflitos por data.
 */
class ConflitosDeReservaException extends RuntimeException
{
    /**
     * @param  list<array{data: string, conflitos: list<array{tipo: string, mensagem: string}>}>  $conflitos
     */
    public function __construct(public readonly array $conflitos)
    {
        parent::__construct('Há conflitos nas datas escolhidas.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Há conflitos nas datas escolhidas. Nada foi salvo.',
            'conflitos' => $this->conflitos,
        ], 422);
    }
}
