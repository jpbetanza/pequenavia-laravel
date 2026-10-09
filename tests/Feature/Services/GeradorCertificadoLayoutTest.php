<?php

use App\Services\GeradorCertificado;

/*
| Layout sem imagens de referência: confere por varredura de pixels onde a tinta cai. As tolerâncias
| são frações do tamanho da imagem, para o teste passar igual no host e na imagem de produção, mesmo
| com ImageMagick de versões (e delegates, como raqm) diferentes.
|
| Expectativa vertical: o editor posiciona o texto com `top: y` e `line-height: 1`, então o conteúdo
| fica centralizado numa caixa de altura igual ao tamanho da fonte. Texto em maiúsculas (sem
| descendentes) tem o centro da tinta perto de y + tamanho/2.
*/

/**
 * Caixa (esquerda, topo, direita, base; direita e base exclusivas) dos pixels claros de uma faixa
 * de linhas. Pixels com intensidade a partir de 128 são tinta, pois o fundo do teste é escuro.
 *
 * @param  list<int>  $intensidade  valores de cinza em ordem de linha
 * @return array{0: int, 1: int, 2: int, 3: int}
 */
function caixaDoTextoClaro(array $intensidade, int $largura, int $linhaInicial, int $linhaFinal): array
{
    $esquerda = $topo = PHP_INT_MAX;
    $direita = $base = -1;

    for ($y = $linhaInicial; $y < $linhaFinal; $y++) {
        for ($x = 0; $x < $largura; $x++) {
            if ($intensidade[$y * $largura + $x] < 128) {
                continue;
            }

            $esquerda = min($esquerda, $x);
            $topo = min($topo, $y);
            $direita = max($direita, $x);
            $base = max($base, $y);
        }
    }

    return [$esquerda, $topo, $direita + 1, $base + 1];
}

it('posiciona o texto à esquerda e centralizado com espaçamento, independente do ambiente', function () {
    $largura = 1200;
    $altura = 800;

    $fundo = new Imagick;
    $fundo->newImage($largura, $altura, new ImagickPixel('#101820'));
    $fundo->setImageFormat('png');

    $alinhadoEsquerda = campoDeTeste([
        'nome' => 'Nome', 'fonte_familia' => 'Dancing Script', 'peso_fonte' => 700,
        'tamanho_fonte' => 120, 'cor' => '#ffffff', 'x' => 100, 'y' => 150,
    ]);
    $centralizado = campoDeTeste([
        'nome' => 'Titulo', 'fonte_familia' => 'Cinzel', 'peso_fonte' => 600,
        'tamanho_fonte' => 80, 'cor' => '#ffffff', 'espacamento_letras' => 6,
        'centralizado' => true, 'y' => 500,
    ]);

    $png = (new GeradorCertificado)->gerar($fundo->getImageBlob(), [$alinhadoEsquerda, $centralizado], [
        'Nome' => 'Maria da Silva',
        'Titulo' => 'CERTIFICADO',
    ]);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    expect([$imagem->getImageWidth(), $imagem->getImageHeight()])->toBe([$largura, $altura]);

    $intensidade = $imagem->exportImagePixels(0, 0, $largura, $altura, 'I', Imagick::PIXEL_CHAR);

    // Campo à esquerda na metade de cima; o centralizado na de baixo.
    [$esq, $topo, $dir, $base] = caixaDoTextoClaro($intensidade, $largura, 0, intdiv($altura, 2));
    expect($dir - $esq)->toBeGreaterThan(0)
        ->and(abs($esq - 100))->toBeLessThanOrEqual(0.02 * $largura)
        ->and(abs(($topo + $base) / 2 - (150 + 120 / 2)))->toBeLessThanOrEqual(0.03 * $altura);

    [$esq, $topo, $dir, $base] = caixaDoTextoClaro($intensidade, $largura, intdiv($altura, 2), $altura);
    expect($dir - $esq)->toBeGreaterThan(0)
        ->and($dir - $esq)->toBeLessThan(0.9 * $largura)
        ->and(abs(($esq + $dir) / 2 - $largura / 2))->toBeLessThanOrEqual(0.015 * $largura);
});
