<?php

namespace App\Services\Gestao;

use RuntimeException;

/**
 * Falha ao classificar uma conversa pelo JEV. O motivo (chave, credito, limite, pedido, servidor, timeout, resposta)
 * diz o que aconteceu para logs e testes; a mensagem é fixa e não vai para tela.
 */
class FalhaDoJev extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct('Não consegui classificar a conversa agora.');
    }
}
