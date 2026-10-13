<?php

namespace App\Jobs;

use App\Services\Assistente\Anexos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga do bucket as imagens e PDFs anexados numa conversa que foi excluída.
 */
class RemoverAnexosDaConversa implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $conversaId) {}

    public function handle(): void
    {
        Storage::disk('s3')->deleteDirectory(Anexos::pastaDa($this->conversaId));
    }
}
