<?php

use App\Models\AcaoProposta;
use App\Models\CampoCertificado;
use App\Models\Conversa;
use App\Models\Espaco;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ResultadoDeFerramenta;
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

/**
 * Corpo SSE como o OpenRouter manda: comentário de keep-alive, um `data:` por chunk e `[DONE]`.
 *
 * @param  list<array<string, mixed>>  $chunks
 */
function sseDoOpenRouter(array $chunks): string
{
    $corpo = ": OPENROUTER PROCESSING\n\n";

    foreach ($chunks as $chunk) {
        $corpo .= 'data: '.json_encode($chunk)."\n\n";
    }

    return $corpo."data: [DONE]\n\n";
}

/** @return array<string, mixed> */
function chunkDeTexto(string $pedaco): array
{
    return ['choices' => [['index' => 0, 'delta' => ['content' => $pedaco]]]];
}

/** @return array<string, mixed> */
function chunkDeFerramenta(int $indice, ?string $id, ?string $nome, string $argumentos): array
{
    $chamada = ['index' => $indice, 'function' => ['arguments' => $argumentos]];

    if ($id !== null) {
        $chamada['id'] = $id;
        $chamada['type'] = 'function';
        $chamada['function']['name'] = $nome;
    }

    return ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [$chamada]]]]];
}

/** @return array<string, mixed> */
function chunkDeUso(int $entrada, int $saida): array
{
    return ['choices' => [], 'usage' => ['prompt_tokens' => $entrada, 'completion_tokens' => $saida]];
}

/** Usuário com uma conversa nova, para as ferramentas do assistente. @return array{0: User, 1: Conversa} */
function usuarioComConversa(): array
{
    $usuario = User::factory()->create();

    return [$usuario, Conversa::factory()->for($usuario)->create()];
}

/** Conversa nova do usuário. */
function conversaDe(User $usuario): Conversa
{
    return Conversa::factory()->for($usuario)->create();
}

/** Executa a ferramenta com uma conversa nova e devolve o resultado. */
function executar(string $classe, User $usuario, array $argumentos): ResultadoDeFerramenta
{
    return app($classe)->executar($usuario, conversaDe($usuario), $argumentos);
}

/** Executa a ferramenta de proposta e devolve a AcaoProposta criada. */
function propostaDeCadastro(string $ferramenta, User $usuario, array $argumentos): AcaoProposta
{
    app($ferramenta)->executar($usuario, conversaDe($usuario), $argumentos);

    return AcaoProposta::latest('created_at')->firstOrFail();
}

/** Seg–sex 08:00–22:00, dois bloqueios de teste e recursos, para os casos de alteração. */
function espacoComAgenda(User $usuario, string $nome = 'Salão'): Espaco
{
    $espaco = Espaco::factory()->for($usuario)->create([
        'nome' => $nome, 'capacidade' => 10, 'recursos_fixos' => ['Piano'], 'cor' => 'verde', 'ativo' => true,
    ]);

    $espaco->horarios()->createMany(array_map(
        fn (int $dia) => ['dia_semana' => $dia, 'abre' => '08:00', 'fecha' => '22:00'],
        [1, 2, 3, 4, 5],
    ));
    $espaco->bloqueios()->create(['inicio' => '2026-11-02', 'fim' => '2026-11-02', 'motivo' => 'Feriado']);

    return $espaco;
}
