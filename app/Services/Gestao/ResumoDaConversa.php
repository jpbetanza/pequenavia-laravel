<?php

namespace App\Services\Gestao;

/**
 * Resumo de uma conversa pelo Haiku. O pedido só vem preenchido quando a secretaria pediu algo que o app não faz.
 */
readonly class ResumoDaConversa
{
    public function __construct(
        public string $resumo,
        public ?string $pedidoNome,
        public ?string $pedidoDescricao,
        public float $custo,
    ) {}
}
