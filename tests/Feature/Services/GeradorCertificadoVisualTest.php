<?php

use App\Services\GeradorCertificado;
use Illuminate\Support\Facades\File;

/*
| Imagens de referência em tests/Fixtures/certificados. Para recriá-las depois de uma mudança
| proposital no desenho, rode `UPDATE_GOLDEN=1 php artisan test --filter=GeradorCertificadoVisualTest`
| e confira as imagens antes de commitar.
*/
dataset('certificados de referência', [
    'a: Lora 400 à esquerda' => ['a', [
        'fonte_familia' => 'Lora', 'peso_fonte' => 400, 'tamanho_fonte' => 48,
        'cor' => '#1a1a1a', 'x' => 100, 'y' => 100,
    ], 'Maria da Silva Pereira'],
    'b: Cinzel 600 centralizado com espaçamento' => ['b', [
        'fonte_familia' => 'Cinzel', 'peso_fonte' => 600, 'tamanho_fonte' => 60,
        'cor' => '#7a1f1f', 'espacamento_letras' => 2, 'centralizado' => true, 'y' => 200,
    ], 'CERTIDÃO DE BATISMO'],
    'c: Dancing Script 700' => ['c', [
        'fonte_familia' => 'Dancing Script', 'peso_fonte' => 700, 'tamanho_fonte' => 72,
        'x' => 300, 'y' => 400,
    ], 'João Pedro'],
    'd: Roboto 600 centralizado' => ['d', [
        'fonte_familia' => 'Roboto', 'peso_fonte' => 600, 'tamanho_fonte' => 32,
        'cor' => '#223344', 'espacamento_letras' => 1.5, 'centralizado' => true, 'y' => 520,
    ], 'Texto com acentuação: ção ñ'],
    'e: Georgia (Gelasio) 600' => ['e', [
        'fonte_familia' => 'Georgia', 'peso_fonte' => 600, 'tamanho_fonte' => 40,
        'x' => 50, 'y' => 650,
    ], 'Gjpqy Typography'],
    'f: Arial (Arimo) 400 com espaçamento negativo' => ['f', [
        'fonte_familia' => 'Arial', 'peso_fonte' => 400, 'tamanho_fonte' => 24,
        'espacamento_letras' => -0.5, 'x' => 700, 'y' => 650,
    ], 'Espaçamento negativo'],
]);

it('reproduz a imagem de referência', function (string $id, array $campo, string $texto) {
    $png = (new GeradorCertificado)->gerar(fundoBranco(), [campoDeTeste($campo)], ['Nome' => $texto]);
    $caminho = base_path("tests/Fixtures/certificados/{$id}.png");

    if (getenv('UPDATE_GOLDEN')) {
        File::ensureDirectoryExists(dirname($caminho));
        File::put($caminho, $png);
        $this->markTestSkipped('Imagem de referência atualizada; confira-a antes de commitar.');
    }

    $this->assertFileExists($caminho, 'Falta a imagem de referência. Rode com UPDATE_GOLDEN=1 e confira o resultado.');

    $referencia = new Imagick($caminho);
    $gerada = new Imagick;
    $gerada->readImageBlob($png);
    [, $diferenca] = $referencia->compareImages($gerada, Imagick::METRIC_ROOTMEANSQUAREDERROR);

    expect($diferenca)->toBeLessThan(0.01);
})->with('certificados de referência');
