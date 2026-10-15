<?php

namespace App\Console\Commands;

use App\Services\PrimeirosPassos\LembreteDosPrimeirosPassos;
use Illuminate\Console\Command;

class PrimeirosPassosLembrar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'primeiros-passos:lembrar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lembra quem parou no meio da configuração da agenda de continuar do próximo passo';

    public function handle(LembreteDosPrimeirosPassos $lembrete): int
    {
        $pendentes = $lembrete->pendentes();

        foreach ($pendentes as $pendente) {
            $lembrete->enviar($pendente['usuario'], $pendente['passo']);
        }

        $this->info("Lembretes enviados: {$pendentes->count()}.");

        return self::SUCCESS;
    }
}
