<?php

use App\Services\Assistente\ClienteOpenRouter;
use App\Services\Assistente\FalhaDoModelo;
use App\Services\Assistente\RespostaDoModelo;
use GuzzleHttp\Psr7\FnStream;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['assistente.chave' => 'chave-de-teste', 'assistente.modelo' => 'anthropic/claude-haiku-5.5']);
});

/** @return array{0: list<string>, 1: RespostaDoModelo} */
function rodar(array $mensagens = [['role' => 'user', 'content' => 'Oi']], array $ferramentas = []): array
{
    $gerador = app(ClienteOpenRouter::class)->conversar($mensagens, $ferramentas);
    $pedacos = [];

    foreach ($gerador as $pedaco) {
        $pedacos[] = $pedaco;
    }

    return [$pedacos, $gerador->getReturn()];
}

test('repassa os pedaços de texto e devolve a resposta com o uso de tokens', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([
        chunkDeTexto('O salão '), chunkDeTexto('está livre.'), chunkDeUso(120, 8),
    ]), 200, ['Content-Type' => 'text/event-stream'])]);

    [$pedacos, $resposta] = rodar();

    expect($pedacos)->toBe(['O salão ', 'está livre.'])
        ->and($resposta->texto)->toBe('O salão está livre.')
        ->and($resposta->chamadas)->toBe([])
        ->and($resposta->tokensEntrada)->toBe(120)
        ->and($resposta->tokensSaida)->toBe(8);

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer chave-de-teste')
        && $r['model'] === 'anthropic/claude-haiku-5.5'
        && $r['stream'] === true
        && $r['usage'] === ['include' => true]);
});

test('junta os argumentos de uma chamada de ferramenta que chegam em pedaços', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([
        chunkDeFerramenta(0, 'call_1', 'consultar_agenda', '{"inicio":'),
        chunkDeFerramenta(0, null, null, '"2026-10-18"}'),
    ]))]);

    [, $resposta] = rodar(ferramentas: [['type' => 'function', 'function' => ['name' => 'consultar_agenda', 'description' => 'x', 'parameters' => ['type' => 'object']]]]);

    expect($resposta->chamadas)->toHaveCount(1)
        ->and($resposta->chamadas[0]->id)->toBe('call_1')
        ->and($resposta->chamadas[0]->nome)->toBe('consultar_agenda')
        ->and($resposta->chamadas[0]->argumentosJson)->toBe('{"inicio":"2026-10-18"}');
});

test('não envia tools vazias', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('ok')]))]);

    rodar();

    Http::assertSent(fn (Request $r) => ! array_key_exists('tools', $r->data()));
});

test('linha de dados partida entre leituras não duplica nem perde texto', function () {
    $corpo = sseDoOpenRouter([chunkDeTexto('Olá, '), chunkDeTexto('mundo')]);
    Http::fake(['openrouter.ai/*' => Http::response($corpo)]);

    $cliente = new ClienteOpenRouter(tamanhoDaLeitura: 7);
    $gerador = $cliente->conversar([['role' => 'user', 'content' => 'Oi']], []);
    $texto = implode('', iterator_to_array($gerador, false));

    expect($texto)->toBe('Olá, mundo');
});

test('erros HTTP viram FalhaDoModelo com mensagem pt-BR', function (int $status, string $motivo) {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'x']], $status)]);

    expect(fn () => rodar())->toThrow(function (FalhaDoModelo $falha) use ($motivo) {
        expect($falha->motivo)->toBe($motivo)
            ->and($falha->getMessage())->toBe('Não consegui responder agora. Tente de novo.');
    });
})->with([[401, 'chave'], [402, 'credito'], [429, 'limite'], [500, 'servidor'], [503, 'servidor']]);

test('erro dentro do stream vira FalhaDoModelo', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([
        chunkDeTexto('Começo'), ['error' => ['message' => 'Provider disconnected']],
    ]))]);

    expect(fn () => rodar())->toThrow(FalhaDoModelo::class);
});

test('timeout vira FalhaDoModelo', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => rodar())->toThrow(fn (FalhaDoModelo $falha) => expect($falha->motivo)->toBe('timeout'));
});

test('queda do stream no meio da leitura vira FalhaDoModelo', function () {
    $primeiroChunk = 'data: '.json_encode(chunkDeTexto('Começo'))."\n\n";
    $leituras = 0;

    $corpo = new FnStream([
        'read' => function () use (&$leituras, $primeiroChunk) {
            $leituras++;

            if ($leituras > 1) {
                throw new RuntimeException('conexão caiu no meio');
            }

            return $primeiroChunk;
        },
        'eof' => fn () => false,
    ]);
    Http::fake(['openrouter.ai/*' => Http::response($corpo)]);

    expect(fn () => rodar())->toThrow(FalhaDoModelo::class);
});

test('lê os tokens do cache e o custo que o OpenRouter informa', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([
        chunkDeTexto('Ok'),
        ['choices' => [], 'usage' => ['prompt_tokens' => 5000, 'completion_tokens' => 20, 'prompt_tokens_details' => ['cached_tokens' => 4200], 'cost' => 0.000123]],
    ]))]);

    [, $resposta] = rodar();

    expect($resposta->tokensDoCache)->toBe(4200)
        ->and($resposta->custo)->toBe(0.000123);
});

test('marca o cache de prompt nas instruções e na última mensagem com conteúdo', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Ok')]))]);

    rodar([
        ['role' => 'system', 'content' => 'Instruções'],
        ['role' => 'user', 'content' => 'Oi'],
        ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'x', 'arguments' => '{}']]]],
        ['role' => 'tool', 'tool_call_id' => 'c1', 'content' => '{"dados":[]}'],
    ]);

    Http::assertSent(function (Request $r) {
        $mensagens = $r['messages'];

        return $mensagens[0]['content'] === [['type' => 'text', 'text' => 'Instruções', 'cache_control' => ['type' => 'ephemeral']]]
            && $mensagens[1]['content'] === 'Oi'
            && $mensagens[3]['content'] === [['type' => 'text', 'text' => '{"dados":[]}', 'cache_control' => ['type' => 'ephemeral']]];
    });
});

test('com anexos, a marca vai na última parte da mensagem', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Ok')]))]);

    rodar([
        ['role' => 'system', 'content' => 'Instruções'],
        ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Veja'], ['type' => 'text', 'text' => '<anexo>…</anexo>']]],
    ]);

    Http::assertSent(fn (Request $r) => ! isset($r['messages'][1]['content'][0]['cache_control'])
        && $r['messages'][1]['content'][1]['cache_control'] === ['type' => 'ephemeral']);
});

test('não marca cache em modelos que não são da Anthropic, nem quando desligado', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Ok')]))]);

    config(['assistente.modelo' => 'outro/modelo']);
    rodar([['role' => 'system', 'content' => 'Instruções'], ['role' => 'user', 'content' => 'Oi']]);

    config(['assistente.modelo' => 'anthropic/claude-haiku-5.5', 'assistente.cache_de_prompt' => false]);
    rodar([['role' => 'system', 'content' => 'Instruções'], ['role' => 'user', 'content' => 'Oi']]);

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $r) => is_array($r['messages'][0]['content']));
});
