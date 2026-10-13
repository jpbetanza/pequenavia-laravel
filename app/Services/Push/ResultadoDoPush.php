<?php

namespace App\Services\Push;

/**
 * O que o serviço de push respondeu para uma inscrição. `expirada`: o aparelho cancelou ou desinstalou (404/410).
 */
final readonly class ResultadoDoPush
{
    public function __construct(
        public string $endpoint,
        public bool $entregue,
        public bool $expirada = false,
        public ?string $motivo = null,
    ) {}
}
