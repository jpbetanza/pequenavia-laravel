<?php

namespace App\Support;

/**
 * Versão do site no ar: a da imagem (APP_VERSION, o SHA do commit) ou, sem ela, a impressão do manifesto do Vite,
 * que muda a cada build dos arquivos. O site compara com a versão com que abriu para avisar que há uma nova.
 */
final class VersaoDoSite
{
    public static function atual(): string
    {
        $configurada = config('app.versao');

        if (is_string($configurada) && $configurada !== '') {
            return $configurada;
        }

        $manifesto = public_path('build/manifest.json');

        return is_file($manifesto) ? (string) md5_file($manifesto) : 'dev';
    }
}
