<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;
use Throwable;

class GestaoChavesOauth extends Command
{
    /**
     * Caminho da cópia criptografada no disco configurado.
     */
    public const CAMINHO = 'oauth/chaves-passport';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gestao:chaves-oauth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Garante as chaves do OAuth (Passport) do MCP da gestão, reaproveitando a cópia guardada';

    public function handle(): int
    {
        if (filled(config('passport.private_key')) && filled(config('passport.public_key'))) {
            $this->info('Chaves do OAuth vindas do ambiente.');

            return self::SUCCESS;
        }

        $privada = Passport::keyPath('oauth-private.key');
        $publica = Passport::keyPath('oauth-public.key');

        if (file_exists($privada) && file_exists($publica)) {
            $this->info('Chaves do OAuth já estão no lugar.');

            return self::SUCCESS;
        }

        try {
            $disco = Storage::disk((string) config('gestao.disco_das_chaves_oauth'));

            if ($disco->exists(self::CAMINHO)) {
                /** @var array{privada: string, publica: string} $chaves */
                $chaves = json_decode(Crypt::decryptString((string) $disco->get(self::CAMINHO)), true, flags: JSON_THROW_ON_ERROR);
                $origem = 'recuperadas da cópia guardada';
            } else {
                $chave = RSA::createKey(4096);
                $chaves = ['privada' => (string) $chave, 'publica' => (string) $chave->getPublicKey()];
                $disco->put(self::CAMINHO, Crypt::encryptString(json_encode($chaves, JSON_THROW_ON_ERROR)));
                $origem = 'geradas e guardadas';
            }
        } catch (Throwable $erro) {
            // Sem as chaves o app funciona; só o MCP da gestão fica sem login.
            $this->error('Não foi possível preparar as chaves do OAuth: '.$erro->getMessage());

            return self::FAILURE;
        }

        file_put_contents($privada, $chaves['privada']);
        file_put_contents($publica, $chaves['publica']);
        chmod($privada, 0600);
        chmod($publica, 0660);

        $this->info("Chaves do OAuth {$origem}.");

        return self::SUCCESS;
    }
}
