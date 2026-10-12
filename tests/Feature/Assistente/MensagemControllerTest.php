<?php

use App\Models\Conversa;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['assistente.chave' => 'chave-de-teste']);
});

test('envia a mensagem e recebe o stream SSE', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Oi!')]))]);
    $conversa = Conversa::factory()->create();

    $resposta = $this->actingAs($conversa->user)
        ->post(route('assistente.mensagens.store', $conversa), ['texto' => 'Olá'])
        ->assertOk();

    expect($resposta->headers->get('Content-Type'))->toStartWith('text/event-stream')
        ->and($resposta->streamedContent())->toContain("event: texto\ndata: {\"pedaco\":\"Oi!\"}")
        ->toContain('event: fim')
        ->not->toContain('</stream>');
});

test('texto vazio ou acima de 2000 caracteres responde 422 sem chamar o modelo', function (string $texto) {
    Http::fake();
    $conversa = Conversa::factory()->create();

    $this->actingAs($conversa->user)->postJson(route('assistente.mensagens.store', $conversa), ['texto' => $texto])
        ->assertInvalid('texto');

    Http::assertNothingSent();
})->with(['', str_repeat('a', 2001)]);

test('mais de 20 mensagens por minuto responde 429', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('ok')]))]);
    $conversa = Conversa::factory()->create();
    $this->actingAs($conversa->user);

    foreach (range(1, 20) as $_) {
        $this->post(route('assistente.mensagens.store', $conversa), ['texto' => 'Oi'])->assertOk();
    }

    $this->postJson(route('assistente.mensagens.store', $conversa), ['texto' => 'Oi'])->assertTooManyRequests();
});

test('o stream segue até o fim mesmo se o cliente fechar a conexão, para gravar a resposta parcial', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Oi!')]))]);
    $conversa = Conversa::factory()->create();
    ignore_user_abort(false);

    $this->actingAs($conversa->user)
        ->post(route('assistente.mensagens.store', $conversa), ['texto' => 'Olá'])
        ->assertOk()
        ->streamedContent();

    expect(ignore_user_abort())->toBe(1);
});
