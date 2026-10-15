<?php

namespace App\Services\Assistente;

/**
 * Resposta final de uma rodada: texto completo, chamadas de ferramenta, uso de tokens (com os lidos do cache) e o custo
 * em dólares, quando o provedor informa.
 */
readonly class RespostaDoModelo
{
    /**
     * @param  list<ChamadaDeFerramenta>  $chamadas
     */
    public function __construct(
        public string $texto,
        public array $chamadas,
        public ?int $tokensEntrada,
        public ?int $tokensSaida,
        public ?int $tokensDoCache = null,
        public ?float $custo = null,
    ) {}
}
