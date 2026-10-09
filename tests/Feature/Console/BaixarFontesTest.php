<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/** @var list<string> $GLOBALS['downloads_temporarios'] */
$GLOBALS['downloads_temporarios'] = [];

afterEach(function () {
    foreach ($GLOBALS['downloads_temporarios'] as $diretorio) {
        File::deleteDirectory($diretorio);
    }

    $GLOBALS['downloads_temporarios'] = [];
});

/**
 * Aponta o catálogo para um diretório vazio e para duas famílias: Lora e Arial (baixada como Arimo).
 */
function catalogoParaDownload(): string
{
    $diretorio = sys_get_temp_dir().'/baixar-fontes-'.uniqid();
    $GLOBALS['downloads_temporarios'][] = $diretorio;

    config([
        'fontes.diretorio' => $diretorio,
        'fontes.grupos' => ['Serif' => ['Lora' => 'lora'], 'Sistema' => ['Arial' => 'arimo']],
        'fontes.google' => ['Arial' => 'Arimo'],
    ]);

    return $diretorio;
}

/**
 * Simula o Google Fonts: só os pesos 400 e 700 existem; os outros respondem 400, como o serviço real.
 */
function googleFontsFalso(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'fonts.googleapis.com/css2*' => function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $consulta);
            [$familia, $peso] = explode(':wght@', $consulta['family']);

            if (! in_array($peso, ['400', '700'], true)) {
                return Http::response('Invalid', 400);
            }

            $pasta = strtolower($familia);

            return Http::response("@font-face { font-family: '{$familia}'; src: url(https://fonts.gstatic.com/s/{$pasta}/{$peso}.ttf) format('truetype'); }");
        },
        'fonts.gstatic.com/*' => Http::response('bytes-da-fonte'),
    ]);
}

it('baixa um arquivo por peso existente e ignora os pesos que a família não tem', function () {
    $diretorio = catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar')->assertSuccessful();

    expect(File::get("{$diretorio}/lora/400.ttf"))->toBe('bytes-da-fonte');
    expect(File::exists("{$diretorio}/lora/700.ttf"))->toBeTrue();
    expect(File::exists("{$diretorio}/lora/100.ttf"))->toBeFalse();
});

it('baixa as fontes de sistema pelas equivalentes livres do Google', function () {
    $diretorio = catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'family=Arimo'));
    expect(File::exists("{$diretorio}/arimo/400.ttf"))->toBeTrue();
});

it('baixa só a família pedida', function () {
    $diretorio = catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar', ['--familia' => ['Lora']])->assertSuccessful();

    expect(File::isDirectory("{$diretorio}/lora"))->toBeTrue()
        ->and(File::isDirectory("{$diretorio}/arimo"))->toBeFalse();
});

it('pede o CSS sem User-Agent de navegador, para o Google devolver TTF estático', function () {
    catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar', ['--familia' => ['Lora']])->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'googleapis')
        && str_starts_with($request->header('User-Agent')[0], 'curl/'));
});

it('falha quando o Google não devolve nenhum arquivo da família', function () {
    catalogoParaDownload();
    Http::preventStrayRequests();
    Http::fake(['fonts.googleapis.com/*' => Http::response('Invalid', 400)]);

    $this->artisan('fontes:baixar')->assertFailed();
});
