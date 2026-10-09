<?php

use App\Services\CatalogoFontes;

it('tem ao menos um arquivo de fonte para cada família do catálogo', function () {
    $semArquivo = collect(CatalogoFontes::pastas())
        ->reject(fn (string $pasta) => CatalogoFontes::pesosDisponiveis($pasta) !== [])
        ->keys()
        ->all();

    expect($semArquivo)->toBe([]);
});

it('guarda só arquivos TrueType de verdade, e não uma página de erro salva como .ttf', function () {
    $invalidos = collect(glob(config('fontes.diretorio').'/*/*.ttf'))
        ->reject(fn (string $arquivo) => file_get_contents($arquivo, false, null, 0, 4) === "\x00\x01\x00\x00")
        ->map(fn (string $arquivo) => basename(dirname($arquivo)).'/'.basename($arquivo))
        ->values()
        ->all();

    expect($invalidos)->toBe([]);
});
