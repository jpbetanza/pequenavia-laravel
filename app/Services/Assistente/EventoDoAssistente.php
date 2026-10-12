<?php

namespace App\Services\Assistente;

/**
 * Evento enviado ao navegador pelo stream SSE: `status`, `texto`, `cartao`, `fim` ou `erro`.
 */
final readonly class EventoDoAssistente
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function __construct(
        public string $tipo,
        public array $dados,
    ) {}
}
