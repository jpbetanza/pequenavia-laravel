<?php

use App\Services\Gestao\ClienteDeResumo;
use App\Services\Gestao\FalhaDoResumo;
use App\Services\Gestao\ResumoDaConversa;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'assistente.chave' => 'chave-teste',
        'assistente.url' => 'https://openrouter.ai/api/v1/chat/completions',
        'assistente.timeout_segundos' => 60,
        'gestao.haiku_modelo' => 'anthropic/claude-haiku-5.5',
    ]);
});

function conversaDeTeste(): array
{
    return [
        ['de' => 'secretaria', 'texto' => 'Quero mandar o certificado por WhatsApp.'],
        ['de' => 'assistente', 'texto' => 'Isso eu não faço. Posso gerar o PDF.'],
    ];
}

function respostaDoHaiku(?string $conteudo, array $uso = ['cost' => 0.00021]): array
{
    return [
        'choices' => [['message' => ['role' => 'assistant', 'content' => $conteudo]]],
        'usage' => $uso,
    ];
}

test('lê o JSON do Haiku e soma o custo informado pelo OpenRouter', function () {
    Http::fake(['openrouter.ai/*' => Http::response(respostaDoHaiku(json_encode([
        'resumo' => 'A secretaria queria enviar certificados por WhatsApp. O assistente só gera o PDF.',
        'pedido' => ['nome' => 'Envio de certificado por WhatsApp', 'descricao' => 'Enviar o certificado direto ao fiel.'],
    ])))]);

    $resumo = app(ClienteDeResumo::class)->resumir(conversaDeTeste(), ['Reservas']);

    expect($resumo)->toBeInstanceOf(ResumoDaConversa::class)
        ->and($resumo->resumo)->toContain('certificados')
        ->and($resumo->pedidoNome)->toBe('Envio de certificado por WhatsApp')
        ->and($resumo->pedidoDescricao)->toBe('Enviar o certificado direto ao fiel.')
        ->and($resumo->custo)->toBe(0.00021);
});

test('manda o prompt, a temperatura zero, o JSON forçado e o uso com custo', function () {
    Http::fake(['openrouter.ai/*' => Http::response(respostaDoHaiku(json_encode(['resumo' => 'Ok.', 'pedido' => null])))]);

    app(ClienteDeResumo::class)->resumir(conversaDeTeste(), ['Reservas', 'Certificados']);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://openrouter.ai/api/v1/chat/completions'
        && $r->hasHeader('Authorization', 'Bearer chave-teste')
        && $r['model'] === 'anthropic/claude-haiku-5.5'
        && $r['temperature'] === 0
        && $r['response_format'] === ['type' => 'json_object']
        && $r['usage'] === ['include' => true]
        && ! array_key_exists('stream', $r->data())
        && $r['messages'][0]['role'] === 'system'
        && str_contains($r['messages'][1]['content'], "- Reservas\n- Certificados")
        && str_contains($r['messages'][1]['content'], 'Secretaria: Quero mandar o certificado por WhatsApp.')
        && str_contains($r['messages'][1]['content'], 'Assistente: Isso eu não faço. Posso gerar o PDF.'));
});

test('pedido nulo deixa o nome e a descrição vazios e o custo zero sem usage', function () {
    Http::fake(['openrouter.ai/*' => Http::response(respostaDoHaiku(json_encode([
        'resumo' => 'Tudo resolvido.',
        'pedido' => null,
    ]), uso: []))]);

    $resumo = app(ClienteDeResumo::class)->resumir(conversaDeTeste(), []);

    expect($resumo->resumo)->toBe('Tudo resolvido.')
        ->and($resumo->pedidoNome)->toBeNull()
        ->and($resumo->pedidoDescricao)->toBeNull()
        ->and($resumo->custo)->toBe(0.0);
});

test('JSON inválido vira falha de json', function () {
    Http::fake(['openrouter.ai/*' => Http::response(respostaDoHaiku('isto não é json'))]);

    expect(fn () => app(ClienteDeResumo::class)->resumir(conversaDeTeste(), []))
        ->toThrow(fn (FalhaDoResumo $falha) => $falha->motivo === 'json');
});

test('JSON sem resumo vira falha de json', function () {
    Http::fake(['openrouter.ai/*' => Http::response(respostaDoHaiku(json_encode(['pedido' => null])))]);

    expect(fn () => app(ClienteDeResumo::class)->resumir(conversaDeTeste(), []))
        ->toThrow(fn (FalhaDoResumo $falha) => $falha->motivo === 'json');
});

test('erro HTTP vira falha de http', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => 'x'], 500)]);

    expect(fn () => app(ClienteDeResumo::class)->resumir(conversaDeTeste(), []))
        ->toThrow(fn (FalhaDoResumo $falha) => $falha->motivo === 'http');
});
