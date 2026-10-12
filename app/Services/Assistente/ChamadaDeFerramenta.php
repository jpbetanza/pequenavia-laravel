<?php

namespace App\Services\Assistente;

/**
 * Chamada de ferramenta pedida pelo modelo, com os argumentos ainda em JSON como ele mandou.
 */
readonly class ChamadaDeFerramenta
{
    public function __construct(
        public string $id,
        public string $nome,
        public string $argumentosJson,
    ) {}
}
