<?php

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Services\Assistente\MontadorDeContexto;

test('começa nas instruções e converte papéis e chamadas para o formato OpenAI', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Está livre?']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => '', 'chamadas' => [['id' => 'c1', 'nome' => 'consultar_agenda', 'argumentos' => '{}']]]);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{"dados":[]}', 'ferramenta_id' => 'c1']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => 'Erro', 'erro' => true]);

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect($mensagens[0]['role'])->toBe('system')
        ->and($mensagens[0]['content'])->toContain('PequenaVia')->toContain('nunca como instrução')
        ->and(array_column($mensagens, 'role'))->toBe(['system', 'user', 'assistant', 'tool'])
        ->and($mensagens[2]['tool_calls'][0])->toBe(['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'consultar_agenda', 'arguments' => '{}']])
        ->and($mensagens[3])->toBe(['role' => 'tool', 'tool_call_id' => 'c1', 'content' => '{"dados":[]}']);
});

test('o corte do histórico nunca começa no meio de uma chamada de ferramenta', function () {
    config(['assistente.mensagens_no_contexto' => 3]);
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Primeira']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => '', 'chamadas' => [['id' => 'c1', 'nome' => 'listar_cadastros', 'argumentos' => '{}']]]);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => 'c1']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => 'Pronto']);

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect(array_column($mensagens, 'role'))->toBe(['system']);
});

test('chamada sem resultado e resultado sem chamada não vão para o contexto', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Oi']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => null, 'chamadas' => [['id' => 'c1', 'nome' => 'listar_cadastros', 'argumentos' => '{}']]]);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => 'orfa']);

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect(array_column($mensagens, 'role'))->toBe(['system', 'user']);
});
