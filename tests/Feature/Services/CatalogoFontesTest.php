<?php

use App\Exceptions\FonteNaoEncontradaException;
use App\Services\CatalogoFontes;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/** @var list<string> $GLOBALS['fontes_temporarias'] */
$GLOBALS['fontes_temporarias'] = [];

afterEach(function () {
    foreach ($GLOBALS['fontes_temporarias'] as $diretorio) {
        File::deleteDirectory($diretorio);
    }

    $GLOBALS['fontes_temporarias'] = [];
});

/**
 * Cria um diretório temporário de fontes com os pesos indicados e aponta o catálogo para ele.
 *
 * @param  array<string, list<int>>  $pesosPorPasta
 */
function fontesTemporarias(array $pesosPorPasta): string
{
    $diretorio = sys_get_temp_dir().'/fontes-'.uniqid();
    $GLOBALS['fontes_temporarias'][] = $diretorio;
    File::ensureDirectoryExists($diretorio);

    foreach ($pesosPorPasta as $pasta => $pesos) {
        File::ensureDirectoryExists("{$diretorio}/{$pasta}");

        foreach ($pesos as $peso) {
            File::put("{$diretorio}/{$pasta}/{$peso}.ttf", 'ttf');
        }
    }

    config(['fontes.diretorio' => $diretorio]);

    return $diretorio;
}

it('devolve o arquivo do peso pedido quando ele existe', function () {
    $diretorio = fontesTemporarias(['lora' => [400, 700]]);
    Log::spy();

    expect(CatalogoFontes::arquivo('Lora', 700))->toBe("{$diretorio}/lora/700.ttf");
    Log::shouldNotHaveReceived('warning');
});

it('usa o peso mais próximo e avisa no log; no empate vence o mais pesado', function (int $pedido, int $esperado) {
    $diretorio = fontesTemporarias(['lora' => [400, 700]]);
    Log::spy();

    expect(CatalogoFontes::arquivo('Lora', $pedido))->toBe("{$diretorio}/lora/{$esperado}.ttf");
    Log::shouldHaveReceived('warning')->once();
})->with([
    'mais perto do 400' => [450, 400],
    'mais perto do 700' => [650, 700],
    'empate entre 400 e 700' => [550, 700],
    'abaixo de todos' => [100, 400],
    'acima de todos' => [900, 700],
]);

it('lança exceção para uma família fora do catálogo', function () {
    fontesTemporarias(['lora' => [400]]);

    CatalogoFontes::arquivo('Comic Sans MS', 400);
})->throws(FonteNaoEncontradaException::class, 'Comic Sans MS');

it('lança exceção quando a família do catálogo não tem nenhum arquivo', function () {
    fontesTemporarias(['lora' => [400]]);

    CatalogoFontes::arquivo('Cinzel', 400);
})->throws(FonteNaoEncontradaException::class, 'Cinzel');

it('lista os pesos disponíveis de cada pasta em ordem crescente', function () {
    fontesTemporarias(['lora' => [700, 400]]);

    $pesos = CatalogoFontes::pesosPorPasta();

    expect($pesos['lora'])->toBe([400, 700])
        ->and($pesos['cinzel'])->toBe([]);
});

it('ignora arquivos cujo nome não é um peso numérico', function () {
    $diretorio = fontesTemporarias(['lora' => [400, 700]]);
    File::put("{$diretorio}/lora/Lora-Regular.ttf", 'ttf');

    expect(CatalogoFontes::pesosPorPasta()['lora'])->toBe([400, 700])
        ->and(CatalogoFontes::arquivo('Lora', 400))->toBe("{$diretorio}/lora/400.ttf");
});
