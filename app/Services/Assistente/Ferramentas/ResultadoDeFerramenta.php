<?php

namespace App\Services\Assistente\Ferramentas;

/**
 * Resultado de uma ferramenta: o que volta para o modelo e, opcionalmente, o cartão mostrado ao usuário.
 */
final readonly class ResultadoDeFerramenta
{
    /**
     * @param  array<string, mixed>  $paraOModelo
     * @param  array{tipo: string, dados: array<string, mixed>}|null  $cartao
     */
    public function __construct(
        public array $paraOModelo,
        public ?array $cartao = null,
    ) {}
}
