<?php

namespace App\Services\Gestao;

use RuntimeException;

/**
 * Falha ao resumir uma conversa pelo Haiku. O motivo (timeout, http, json) é para logs e testes.
 */
class FalhaDoResumo extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct('Não consegui resumir a conversa agora.');
    }
}
