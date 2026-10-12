<?php

namespace App\Services\Assistente;

use RuntimeException;

/**
 * Falha ao falar com o modelo. A mensagem é sempre a mesma, em pt-BR, para ser mostrada ao usuário;
 * o motivo (chave, credito, limite, servidor, timeout, stream) diz o que aconteceu para logs e testes.
 */
class FalhaDoModelo extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct('Não consegui responder agora. Tente de novo.');
    }
}
