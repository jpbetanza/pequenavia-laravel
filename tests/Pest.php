<?php

use App\Models\CampoCertificado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * PNG branco usado como fundo nos testes de geração de certificado.
 */
function fundoBranco(int $largura = 1200, int $altura = 800): string
{
    $imagem = new Imagick;
    $imagem->newImage($largura, $altura, new ImagickPixel('white'));
    $imagem->setImageFormat('png');

    return $imagem->getImageBlob();
}

/**
 * Caixa da tinta (pixels que não são brancos): esquerda, topo, direita e base, com direita e base
 * exclusivas. Só use em imagens que tenham alguma tinta.
 *
 * @return array{0: int, 1: int, 2: int, 3: int}
 */
function caixaDaTinta(string $png): array
{
    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    $imagem->trimImage(0);
    $pagina = $imagem->getImagePage();

    return [
        $pagina['x'],
        $pagina['y'],
        $pagina['x'] + $imagem->getImageWidth(),
        $pagina['y'] + $imagem->getImageHeight(),
    ];
}

/**
 * Campo em memória, sem banco: o factory criaria o modelo pai até com make().
 *
 * @param  array<string, mixed>  $atributos
 */
function campoDeTeste(array $atributos = []): CampoCertificado
{
    return new CampoCertificado(array_merge([
        'nome' => 'Nome',
        'x' => 100,
        'y' => 100,
        'cor' => '#000000',
        'tamanho_fonte' => 40,
        'fonte_familia' => 'Roboto',
        'peso_fonte' => 600,
        'espacamento_letras' => 0,
        'centralizado' => false,
    ], $atributos));
}
