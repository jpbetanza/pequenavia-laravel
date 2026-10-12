<?php

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\User;
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

    expect(array_column($mensagens, 'role'))->toBe(['system', 'user', 'assistant', 'tool', 'assistant']);
});

test('chamada sem resultado e resultado sem chamada não vão para o contexto', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Oi']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => null, 'chamadas' => [['id' => 'c1', 'nome' => 'listar_cadastros', 'argumentos' => '{}']]]);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => 'orfa']);

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect(array_column($mensagens, 'role'))->toBe(['system', 'user']);
});

test('o nome do usuário vai para o prompt como texto puro, sem escape HTML', function () {
    $usuario = User::factory()->create(['name' => "Ana D'Ávila <x>"]);
    $conversa = Conversa::factory()->for($usuario)->create();

    $mensagens = app(MontadorDeContexto::class)->montar($usuario, $conversa);

    expect($mensagens[0]['content'])->toContain("de Ana D'Ávila <x>.")
        ->and($mensagens[0]['content'])->not->toContain('&#039;')->not->toContain('&lt;');
});

test('o nome vai para o prompt sem espaços sobrando e com no máximo 80 caracteres', function () {
    $usuario = User::factory()->create(['name' => "  Maria\n  ".str_repeat('Conceição ', 12)]);
    $conversa = Conversa::factory()->for($usuario)->create();

    $instrucoes = app(MontadorDeContexto::class)->montar($usuario, $conversa)[0]['content'];

    expect($instrucoes)->toContain('de Maria '.str_repeat('Conceição ', 7).'Conc...');
});

test('chamada respondida pela metade sai do contexto junto com o resultado parcial', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Oi']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => null, 'chamadas' => [
        ['id' => 'c1', 'nome' => 'listar_cadastros', 'argumentos' => '{}'],
        ['id' => 'c2', 'nome' => 'consultar_agenda', 'argumentos' => '{}'],
    ]]);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => 'c1']);

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect(array_column($mensagens, 'role'))->toBe(['system', 'user']);
});

test('turno maior que o limite começa na última pergunta do usuário, com os pares de ferramenta íntegros', function () {
    config(['assistente.mensagens_no_contexto' => 3]);
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Pergunta longa']);
    foreach (['c1', 'c2', 'c3'] as $id) {
        Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => null, 'chamadas' => [['id' => $id, 'nome' => 'listar_cadastros', 'argumentos' => '{}']]]);
        Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => $id]);
    }

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect(array_column($mensagens, 'role'))->toBe(['system', 'user', 'assistant', 'tool', 'assistant', 'tool', 'assistant', 'tool'])
        ->and($mensagens[1]['content'])->toBe('Pergunta longa')
        ->and(array_column($mensagens, 'tool_call_id'))->toBe(['c1', 'c2', 'c3']);
});

test('turno maior que o limite não traz mensagens de um turno anterior criadas no mesmo segundo', function () {
    config(['assistente.mensagens_no_contexto' => 3]);
    $segundo = '2026-10-13 10:00:00';
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['id' => '00000000-0000-7000-8000-000000000001', 'papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Pergunta antiga', 'created_at' => $segundo]);
    Mensagem::factory()->for($conversa)->create(['id' => '00000000-0000-7000-8000-000000000002', 'papel' => PapelDaMensagem::Assistente, 'conteudo' => 'Resposta antiga', 'created_at' => $segundo]);
    Mensagem::factory()->for($conversa)->create(['id' => '00000000-0000-7000-8000-000000000003', 'papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Pergunta nova', 'created_at' => $segundo]);
    Mensagem::factory()->for($conversa)->create(['id' => '00000000-0000-7000-8000-000000000004', 'papel' => PapelDaMensagem::Assistente, 'conteudo' => null, 'chamadas' => [['id' => 'c1', 'nome' => 'listar_cadastros', 'argumentos' => '{}']], 'created_at' => '2026-10-13 10:00:01']);
    Mensagem::factory()->for($conversa)->create(['id' => '00000000-0000-7000-8000-000000000005', 'papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => 'c1', 'created_at' => '2026-10-13 10:00:02']);
    Mensagem::factory()->for($conversa)->create(['id' => '00000000-0000-7000-8000-000000000006', 'papel' => PapelDaMensagem::Assistente, 'conteudo' => null, 'chamadas' => [['id' => 'c2', 'nome' => 'listar_cadastros', 'argumentos' => '{}']], 'created_at' => '2026-10-13 10:00:03']);
    Mensagem::factory()->for($conversa)->create(['id' => '00000000-0000-7000-8000-000000000007', 'papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => 'c2', 'created_at' => '2026-10-13 10:00:04']);

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect(array_column($mensagens, 'role'))->toBe(['system', 'user', 'assistant', 'tool', 'assistant', 'tool'])
        ->and($mensagens[1]['content'])->toBe('Pergunta nova');
});
