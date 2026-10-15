<?php

namespace App\Console\Commands;

use App\Services\Gestao\ClassificadorDeConversas;
use Illuminate\Console\Command;

class GestaoClassificar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gestao:classificar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classifica as conversas paradas para o painel de gestão (JEV e Haiku)';

    public function handle(ClassificadorDeConversas $classificador): int
    {
        $rodada = $classificador->rodar();

        $this->info("Rodada {$rodada->id}: {$rodada->conversas_lidas} conversas lidas, {$rodada->resumidas} resumidas, {$rodada->temas_novos} temas novos, {$rodada->falhas} falhas.");
        $this->line("Custos: JEV US$ {$rodada->custo_jev}, Haiku US$ {$rodada->custo_haiku}.");

        if ($rodada->erro !== null) {
            $this->error($rodada->erro);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
