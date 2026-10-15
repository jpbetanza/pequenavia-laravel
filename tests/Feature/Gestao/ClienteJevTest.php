<?php

use App\Services\Gestao\ClienteJev;
use App\Services\Gestao\FalhaDoJev;
use App\Services\Gestao\RespostaDoJev;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config([
        'gestao.jev_chave' => 'chave-teste',
        'gestao.jev_url' => 'https://jev.test/v1/systemone',
        'gestao.jev_modelo' => '~typesafe/jev-latest',
    ]);
});

function respostaDoJev(array $respostas = []): array
{
    return [
        'model' => '~typesafe/jev-latest',
        'answers' => $respostas,
        'usage' => ['input_tokens' => 512, 'output_tokens' => 9],
    ];
}

function perguntasDeTeste(): array
{
    return [
        'use_case' => ['type' => 'choice', 'instructions' => 'Escolha.', 'criteria' => ['reserva' => 'x']],
        'frustration' => ['type' => 'score', 'instructions' => 'Nota.', 'criteria' => []],
        'confused' => ['type' => 'noul', 'instructions' => 'Confusa?', 'criteria' => []],
    ];
}

test('monta o corpo e o cabeçalho do JEV', function () {
    Http::fake(['jev.test/*' => Http::response(respostaDoJev())]);

    app(ClienteJev::class)->perguntar(['conversation' => []], perguntasDeTeste());

    Http::assertSent(fn (Request $r) => $r->url() === 'https://jev.test/v1/systemone'
        && $r->hasHeader('Authorization', 'Bearer chave-teste')
        && $r['model'] === '~typesafe/jev-latest'
        && $r['state'] === ['conversation' => []]
        && $r['questions']['use_case']['type'] === 'choice'
        && $r['questions']['confused']['type'] === 'noul');
});

test('lê os três tipos de resposta', function () {
    Http::fake(['jev.test/*' => Http::response(respostaDoJev([
        'use_case' => ['type' => 'choice', 'choice' => 'reserva', 'probabilities' => ['reserva' => 0.9, 'outro' => 0.1], 'confidence' => 0.8],
        'frustration' => ['type' => 'score', 'score' => 2.5, 'legend' => 'x', 'probabilities' => [], 'confidence' => 0.7],
        'confused' => ['type' => 'noul', 'noul' => 0.25],
    ]))]);

    $resposta = app(ClienteJev::class)->perguntar('estado em texto', perguntasDeTeste());

    expect($resposta)->toBeInstanceOf(RespostaDoJev::class)
        ->and($resposta->modelo)->toBe('~typesafe/jev-latest')
        ->and($resposta->tokensEntrada)->toBe(512)
        ->and($resposta->escolha('use_case'))->toBe([
            'escolha' => 'reserva',
            'confianca' => 0.8,
            'probabilidades' => ['reserva' => 0.9, 'outro' => 0.1],
        ])
        ->and($resposta->nota('frustration'))->toBe(['nota' => 2.5, 'confianca' => 0.7])
        ->and($resposta->noul('confused'))->toBe(0.25);
});

test('pergunta sem resposta devolve valores zerados', function () {
    Http::fake(['jev.test/*' => Http::response(respostaDoJev())]);

    $resposta = app(ClienteJev::class)->perguntar('estado', perguntasDeTeste());

    expect($resposta->escolha('use_case'))->toBe(['escolha' => '', 'confianca' => 0.0, 'probabilidades' => []])
        ->and($resposta->nota('frustration'))->toBe(['nota' => 0.0, 'confianca' => 0.0])
        ->and($resposta->noul('confused'))->toBe(0.0);
});

test('tenta de novo no 429 e devolve a resposta quando a nova tentativa funciona', function () {
    Http::fake(['jev.test/*' => Http::sequence()
        ->push(['error' => 'limite'], 429)
        ->push(respostaDoJev(['confused' => ['type' => 'noul', 'noul' => 0.0]]), 200),
    ]);

    $resposta = app(ClienteJev::class)->perguntar('estado', perguntasDeTeste());

    Http::assertSentCount(2);
    expect($resposta->noul('confused'))->toBe(0.0);
});

test('repete até três vezes nos erros 5xx e depois falha com servidor', function () {
    Http::fake(['jev.test/*' => Http::response(['error' => 'x'], 503)]);

    expect(fn () => app(ClienteJev::class)->perguntar('estado', perguntasDeTeste()))
        ->toThrow(fn (FalhaDoJev $falha) => $falha->motivo === 'servidor');

    Http::assertSentCount(4);
});

test('erro de chave não é tentado de novo e vira falha de chave', function () {
    Http::fake(['jev.test/*' => Http::response(['error' => 'x'], 401)]);

    expect(fn () => app(ClienteJev::class)->perguntar('estado', perguntasDeTeste()))
        ->toThrow(fn (FalhaDoJev $falha) => $falha->motivo === 'chave');

    Http::assertSentCount(1);
});

test('erros do pedido viram falha de pedido, sem nova tentativa', function (int $status) {
    Http::fake(['jev.test/*' => Http::response(['error' => 'x'], $status)]);

    expect(fn () => app(ClienteJev::class)->perguntar('estado', perguntasDeTeste()))
        ->toThrow(fn (FalhaDoJev $falha) => $falha->motivo === 'pedido');

    Http::assertSentCount(1);
})->with([400, 413, 415, 422]);

test('falta de crédito vira falha de crédito', function () {
    Http::fake(['jev.test/*' => Http::response(['error' => 'x'], 402)]);

    expect(fn () => app(ClienteJev::class)->perguntar('estado', perguntasDeTeste()))
        ->toThrow(fn (FalhaDoJev $falha) => $falha->motivo === 'credito');
});

test('conexão que cai vira falha de timeout', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => app(ClienteJev::class)->perguntar('estado', perguntasDeTeste()))
        ->toThrow(fn (FalhaDoJev $falha) => $falha->motivo === 'timeout');
});

test('resposta sem o campo answers vira falha de resposta', function () {
    Http::fake(['jev.test/*' => Http::response(['model' => 'x'])]);

    expect(fn () => app(ClienteJev::class)->perguntar('estado', perguntasDeTeste()))
        ->toThrow(fn (FalhaDoJev $falha) => $falha->motivo === 'resposta');
});
