<?php

namespace App\Services\Assistente;

use Illuminate\Http\UploadedFile;

/**
 * Um anexo como chegou na mensagem, já validado: texto (planilha convertida pelo navegador, .txt) ou arquivo (imagem, PDF).
 */
final readonly class AnexoRecebido
{
    /**
     * @param  'planilha'|'texto'|'imagem'|'pdf'  $tipo
     */
    public function __construct(
        public string $nome,
        public string $tipo,
        public ?string $conteudo = null,
        public bool $truncado = false,
        public ?UploadedFile $arquivo = null,
    ) {}
}
