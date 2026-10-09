<?php

namespace App\Services;

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
}
