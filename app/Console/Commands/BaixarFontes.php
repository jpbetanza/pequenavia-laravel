<?php

namespace App\Console\Commands;

use App\Services\CatalogoFontes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class BaixarFontes extends Command
{
    /**
     * Sem User-Agent de navegador o Google Fonts devolve um TTF estático por peso, que o ImageMagick
     * sabe usar. Com um navegador ele serviria fontes variáveis em WOFF2.
     */
    private const USER_AGENT = 'curl/8.7.1';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fontes:baixar {--familia=* : Baixa só estas famílias (nome exibido no editor)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Baixa do Google Fonts os arquivos TTF do catálogo de fontes';

    public function handle(): int
    {
        $familias = collect(CatalogoFontes::pastas());

        if ($this->option('familia') !== []) {
            $familias = $familias->only($this->option('familia'));
        }

        $falhas = 0;

        foreach ($familias as $familia => $pasta) {
            $baixados = $this->baixarFamilia($familia, $pasta);

            if ($baixados === 0) {
                $this->error("{$familia}: o Google Fonts não devolveu nenhum arquivo.");
                $falhas++;

                continue;
            }

            $this->info("{$familia}: {$baixados} arquivo(s) em {$pasta}/");
        }

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Baixa um arquivo por peso que a família oferece e devolve quantos baixou.
     */
    private function baixarFamilia(string $familia, string $pasta): int
    {
        $nomeNoGoogle = config("fontes.google.{$familia}", $familia);
        $baixados = 0;

        foreach (CatalogoFontes::PESOS as $peso) {
            $css = Http::withUserAgent(self::USER_AGENT)->get($this->urlDoCss($nomeNoGoogle, $peso));

            // A família não tem este peso.
            if ($css->status() === 400) {
                continue;
            }

            $css->throw();

            if (! preg_match('/url\(([^)]+\.ttf)\)/', $css->body(), $achado)) {
                $this->warn("{$familia} {$peso}: a resposta do Google não traz um arquivo .ttf.");

                continue;
            }

            $destino = config('fontes.diretorio')."/{$pasta}";
            File::ensureDirectoryExists($destino);
            File::put("{$destino}/{$peso}.ttf", Http::get($achado[1])->throw()->body());

            $baixados++;
        }

        return $baixados;
    }

    private function urlDoCss(string $familia, int $peso): string
    {
        return 'https://fonts.googleapis.com/css2?family='.urlencode($familia).":wght@{$peso}&display=swap";
    }
}
