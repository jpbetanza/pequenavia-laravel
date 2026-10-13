<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GerarChavesPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:gerar-chaves';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera o par de chaves VAPID das notificações no celular';

    public function handle(): int
    {
        $chaves = VAPID::createVapidKeys();

        $this->info('Coloque no ambiente (.env ou variáveis da stack). Trocar as chaves depois desliga as inscrições já feitas.');
        $this->line('VAPID_PUBLIC_KEY='.$chaves['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$chaves['privateKey']);

        return self::SUCCESS;
    }
}
