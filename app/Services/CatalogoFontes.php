<?php

namespace App\Services;

use App\Exceptions\FonteNaoEncontradaException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SplFileInfo;

class CatalogoFontes
{
    /** @var list<int> */
    public const PESOS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

    /**
     * Grupos de fontes: grupo => (família exibida => pasta dos arquivos).
     *
     * @return array<string, array<string, string>>
     */
    public static function grupos(): array
    {
        return config('fontes.grupos');
    }

    /**
     * @return list<string>
     */
    public static function familias(): array
    {
        return collect(self::grupos())
            ->flatMap(fn (array $familias) => array_keys($familias))
            ->values()
            ->all();
    }

    /**
     * Família exibida => pasta dos arquivos.
     *
     * @return array<string, string>
     */
    public static function pastas(): array
    {
        return collect(self::grupos())->collapse()->all();
    }

    /**
     * Pesos que têm arquivo na pasta, em ordem crescente.
     *
     * @return list<int>
     */
    public static function pesosDisponiveis(string $pasta): array
    {
        $diretorio = config('fontes.diretorio')."/{$pasta}";

        $arquivos = File::isDirectory($diretorio) ? File::files($diretorio) : [];

        $pesos = collect($arquivos)
            ->filter(fn (SplFileInfo $arquivo) => $arquivo->getExtension() === 'ttf')
            ->map(fn (SplFileInfo $arquivo) => $arquivo->getBasename('.ttf'))
            ->filter(fn (string $nome) => preg_match('/^\d+$/', $nome) === 1)
            ->map(fn (string $nome) => (int) $nome)
            ->sort()
            ->values()
            ->all();

        return $pesos;
    }

    /**
     * @return array<string, list<int>>
     */
    public static function pesosPorPasta(): array
    {
        return collect(self::pastas())
            ->unique()
            ->mapWithKeys(fn (string $pasta) => [$pasta => self::pesosDisponiveis($pasta)])
            ->all();
    }

    /**
     * Caminho do arquivo da fonte. Se a família não tem o peso pedido, usa o mais próximo
     * (no empate, o mais pesado) e registra um aviso.
     *
     * @throws FonteNaoEncontradaException
     */
    public static function arquivo(string $familia, int $peso): string
    {
        $pasta = self::pastas()[$familia]
            ?? throw new FonteNaoEncontradaException("A fonte \"{$familia}\" não está no catálogo.");

        $pesos = self::pesosDisponiveis($pasta);

        if ($pesos === []) {
            throw new FonteNaoEncontradaException("Não há arquivos de fonte para \"{$familia}\".");
        }

        $usado = self::pesoMaisProximo($pesos, $peso);

        if ($usado !== $peso) {
            Log::warning('Peso de fonte indisponível; usando o mais próximo.', [
                'familia' => $familia,
                'pedido' => $peso,
                'usado' => $usado,
            ]);
        }

        return config('fontes.diretorio')."/{$pasta}/{$usado}.ttf";
    }

    /**
     * @param  non-empty-list<int>  $pesos
     */
    private static function pesoMaisProximo(array $pesos, int $pedido): int
    {
        $melhor = $pesos[0];

        foreach ($pesos as $peso) {
            $distancia = abs($peso - $pedido);
            $melhorDistancia = abs($melhor - $pedido);

            if ($distancia < $melhorDistancia || ($distancia === $melhorDistancia && $peso > $melhor)) {
                $melhor = $peso;
            }
        }

        return $melhor;
    }
}
