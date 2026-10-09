<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class RemoverImagemModelo implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $path) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Storage::disk('s3')->delete($this->path);
    }
}
