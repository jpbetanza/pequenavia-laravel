<?php

use App\Exceptions\ImagemInvalidaException;
use App\Exceptions\TextoGrandeDemaisException;
use App\Services\GeradorCertificado;
use Illuminate\Support\Facades\Log;

it('devolve um PNG com as dimensões do fundo', function () {
    $png = (new GeradorCertificado)->gerar(fundoBranco(1000, 700), [campoDeTeste()], ['Nome' => 'Maria']);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);

    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and($imagem->getImageWidth())->toBe(1000)
        ->and($imagem->getImageHeight())->toBe(700);
});

it('devolve o fundo intacto quando não há texto para desenhar', function (array $valores) {
    $fundo = fundoBranco();

    $png = (new GeradorCertificado)->gerar($fundo, [campoDeTeste()], $valores);

    $original = new Imagick;
    $original->readImageBlob($fundo);
    $gerada = new Imagick;
    $gerada->readImageBlob($png);
    [, $diferenca] = $original->compareImages($gerada, Imagick::METRIC_ROOTMEANSQUAREDERROR);

    expect($diferenca)->toBe(0.0);
})->with([
    'sem valores' => [[]],
    'valor vazio' => [['Nome' => '']],
    'só espaços' => [['Nome' => '   ']],
    'chave que não é campo do modelo' => [['Outro' => 'texto']],
]);

/*
| Valores medidos no Chrome com `line-height: 1`, como o editor posiciona o texto: distância do topo
| do campo (`top: y`) até a linha de base. A letra "H" assenta exatamente nela. Dancing Script fica
| de fora porque o "H" dela tem um floreio que desce da linha de base.
*/
dataset('fontes com a linha de base medida no navegador', [
    'Lora 400 a 48px' => ['Lora', 400, 48, 41.5],
    'Cinzel 600 a 60px' => ['Cinzel', 600, 60, 48.0],
    'Roboto 600 a 32px' => ['Roboto', 600, 32, 26.5],
    'Gelasio 600 a 40px (Georgia)' => ['Georgia', 600, 40, 31.5],
    'Arimo 400 a 24px (Arial)' => ['Arial', 400, 24, 20.0],
]);

it('assenta a linha de base onde o navegador a coloca', function (string $familia, int $peso, int $tamanho, float $linhaDeBase) {
    $campo = campoDeTeste(['fonte_familia' => $familia, 'peso_fonte' => $peso, 'tamanho_fonte' => $tamanho]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => 'H']);

    [, , , $base] = caixaDaTinta($png);
    expect($base - 100)->toEqualWithDelta($linhaDeBase, 1.5);
})->with('fontes com a linha de base medida no navegador');

it('centraliza o texto na largura da imagem, com ou sem espaçamento', function (float $espacamento) {
    $campo = campoDeTeste(['centralizado' => true, 'espacamento_letras' => $espacamento]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(1200), [$campo], ['Nome' => 'HHH']);

    [$esquerda, , $direita] = caixaDaTinta($png);
    expect(($esquerda + $direita) / 2)->toEqualWithDelta(600, 1.0);
})->with([
    'sem espaçamento' => [0],
    'com espaçamento de 4px' => [4],
]);

it('soma o espaçamento entre letras a cada vão', function () {
    $larguraDaTinta = function (float $espacamento): int {
        $png = (new GeradorCertificado)->gerar(
            fundoBranco(),
            [campoDeTeste(['espacamento_letras' => $espacamento])],
            ['Nome' => 'HHHH'],
        );
        [$esquerda, , $direita] = caixaDaTinta($png);

        return $direita - $esquerda;
    };

    // "HHHH" tem 3 vãos entre letras.
    expect($larguraDaTinta(10) - $larguraDaTinta(0))->toEqualWithDelta(30, 2);
});

it('desenha com a cor exata do campo', function () {
    $campo = campoDeTeste(['cor' => '#7a1f1f', 'tamanho_fonte' => 100]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => 'H']);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    $encontrados = 0;

    foreach ($imagem->getPixelIterator() as $linha) {
        foreach ($linha as $pixel) {
            $cor = $pixel->getColor();
            $encontrados += [$cor['r'], $cor['g'], $cor['b']] === [122, 31, 31] ? 1 : 0;
        }
    }

    expect($encontrados)->toBeGreaterThan(500);
});

it('gira o fundo conforme a orientação EXIF, como o navegador exibe', function () {
    $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00";
    $exif = "Exif\x00\x00".$tiff;
    $fundo = new Imagick;
    $fundo->newImage(600, 400, new ImagickPixel('white'));
    $fundo->setImageFormat('jpeg');
    $jpeg = $fundo->getImageBlob();
    $comOrientacao = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    $png = (new GeradorCertificado)->gerar($comOrientacao, [campoDeTeste()], ['Nome' => 'Maria']);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    expect($imagem->getImageWidth())->toBe(400)
        ->and($imagem->getImageHeight())->toBe(600);
});

it('usa o peso mais próximo quando a família não tem o peso do campo', function () {
    Log::spy();
    $campo = campoDeTeste(['fonte_familia' => 'Lato', 'peso_fonte' => 600]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => 'Maria']);

    Log::shouldHaveReceived('warning')->once();
    expect($png)->not->toBeEmpty();
});

it('recusa um fundo que não é imagem', function () {
    (new GeradorCertificado)->gerar('isto não é uma imagem', [campoDeTeste()], ['Nome' => 'Maria']);
})->throws(ImagemInvalidaException::class, 'Não foi possível ler a imagem do modelo.');

it('recusa o texto grande demais para ser desenhado', function () {
    $campo = campoDeTeste(['tamanho_fonte' => 1000]);

    (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => str_repeat('W', 500)]);
})->throws(TextoGrandeDemaisException::class, 'Nome');

it('recusa um fundo em formato que não é PNG, JPEG nem WEBP', function (string $bytes) {
    (new GeradorCertificado)->gerar($bytes, [campoDeTeste()], ['Nome' => 'Maria']);
})->with([
    'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="red"/></svg>'],
    'GIF' => [(function () {
        $gif = new Imagick;
        $gif->newImage(50, 50, new ImagickPixel('white'));
        $gif->setImageFormat('gif');

        return $gif->getImageBlob();
    })()],
])->throws(ImagemInvalidaException::class, 'Não foi possível ler a imagem do modelo.');

it('aceita fundo JPEG e WEBP e devolve PNG com as mesmas dimensões', function (string $formato) {
    $fundo = new Imagick;
    $fundo->newImage(640, 480, new ImagickPixel('white'));
    $fundo->setImageFormat($formato);

    $png = (new GeradorCertificado)->gerar($fundo->getImageBlob(), [campoDeTeste()], ['Nome' => 'Maria']);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    expect($imagem->getImageFormat())->toBe('PNG')
        ->and($imagem->getImageWidth())->toBe(640)
        ->and($imagem->getImageHeight())->toBe(480);
})->with(['JPEG' => ['jpeg'], 'WEBP' => ['webp']]);
