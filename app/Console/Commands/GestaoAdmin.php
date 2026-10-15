<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GestaoAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gestao:admin {email : E-mail do usuário} {--remover : Tira o acesso ao painel de gestão}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liga ou desliga o acesso ao painel de gestão de um usuário';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $usuario = User::query()->where('email', $email)->first();

        if ($usuario === null) {
            $this->error("Nenhum usuário com o e-mail {$email}.");

            return self::FAILURE;
        }

        $usuario->admin = ! $this->option('remover');
        $usuario->save();

        $this->info($usuario->admin
            ? "Acesso ao painel de gestão ligado para {$usuario->email}."
            : "Acesso ao painel de gestão desligado para {$usuario->email}.");

        return self::SUCCESS;
    }
}
