<?php

namespace App\Http\Controllers;

use App\Services\CatalogoFontes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FonteController extends Controller
{
    /**
     * Serve o TTF de uma fonte do catálogo. Só chegam aqui pastas e pesos que existem no catálogo.
     */
    public function show(string $pasta, int $peso): BinaryFileResponse
    {
        abort_unless(in_array($pasta, CatalogoFontes::pastas(), true), 404);
        abort_unless(in_array($peso, CatalogoFontes::pesosDisponiveis($pasta), true), 404);

        return response()->file(config('fontes.diretorio')."/{$pasta}/{$peso}.ttf", [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
