<?php

use App\Enums\PapelDaMensagem;
use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\User;

beforeEach(fn () => config(['assistente.chave' => 'chave-de-teste']));

test('lista só as conversas do usuário, mais recentes primeiro, com busca sem acento', function () {
    $usuario = User::factory()->create();
    Conversa::factory()->for($usuario)->create(['titulo' => 'Certificados do mês', 'ultima_mensagem_em' => now()->subDay()]);
    Conversa::factory()->for($usuario)->create(['titulo' => 'Salão no sábado', 'ultima_mensagem_em' => now()]);
    Conversa::factory()->create(['titulo' => 'Salão de outro usuário']);

    $this->actingAs($usuario)->getJson(route('assistente.conversas.index'))
        ->assertOk()
        ->assertJsonPath('conversas.0.titulo', 'Salão no sábado')
        ->assertJsonPath('conversas.1.titulo', 'Certificados do mês')
        ->assertJsonCount(2, 'conversas');

    $this->getJson(route('assistente.conversas.index', ['busca' => 'salao']))
        ->assertJsonCount(1, 'conversas')
        ->assertJsonPath('conversas.0.titulo', 'Salão no sábado');
});

test('busca encontra conversa antiga, além das 200 mais recentes, e a lista continua limitada a 200', function () {
    $usuario = User::factory()->create();
    Conversa::factory()->for($usuario)->create(['titulo' => 'Salão', 'ultima_mensagem_em' => now()->subYear()]);
    Conversa::factory()->for($usuario)->count(204)->create(['titulo' => 'Catequese', 'ultima_mensagem_em' => now()]);

    $this->actingAs($usuario)->getJson(route('assistente.conversas.index', ['busca' => 'salao']))
        ->assertOk()
        ->assertJsonCount(1, 'conversas')
        ->assertJsonPath('conversas.0.titulo', 'Salão');

    $this->getJson(route('assistente.conversas.index'))->assertJsonCount(200, 'conversas');
});

test('a busca acompanha a troca de título', function () {
    $conversa = Conversa::factory()->for(User::factory()->create())->create(['titulo' => 'Nova conversa']);

    $conversa->update(['titulo' => 'Reunião do conselho']);

    $this->actingAs($conversa->user)->getJson(route('assistente.conversas.index', ['busca' => 'REUNIAO']))
        ->assertJsonCount(1, 'conversas')
        ->assertJsonPath('conversas.0.titulo', 'Reunião do conselho');
});

test('cria conversa vazia', function () {
    $this->actingAs(User::factory()->create())->postJson(route('assistente.conversas.store'))
        ->assertCreated()
        ->assertJsonPath('conversa.titulo', 'Nova conversa');

    expect(Conversa::count())->toBe(1);
});

test('mostra só as mensagens visíveis e atualiza o cartão de ação', function () {
    $conversa = Conversa::factory()->create();
    $acao = AcaoProposta::factory()->for($conversa)->create(['user_id' => $conversa->user_id, 'status' => StatusDaAcao::Confirmada]);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Reserve o salão']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => '', 'chamadas' => [['id' => 'c1', 'nome' => 'propor_reserva', 'argumentos' => '{}']]]);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Ferramenta, 'conteudo' => '{}', 'ferramenta_id' => 'c1']);
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Assistente, 'conteudo' => 'Confira a proposta.', 'cartoes' => [['tipo' => 'acao_proposta', 'dados' => ['id' => $acao->id, 'status' => 'proposta']]]]);

    $this->actingAs($conversa->user)->getJson(route('assistente.conversas.show', $conversa))
        ->assertOk()
        ->assertJsonCount(2, 'mensagens')
        ->assertJsonPath('mensagens.1.cartoes.0.dados.status', 'confirmada');
});

test('exclui a conversa', function () {
    $conversa = Conversa::factory()->create();

    $this->actingAs($conversa->user)->deleteJson(route('assistente.conversas.destroy', $conversa))->assertNoContent();

    expect(Conversa::count())->toBe(0);
});

test('conversa alheia responde 404 em show, destroy e envio', function () {
    $alheia = Conversa::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->getJson(route('assistente.conversas.show', $alheia))->assertNotFound();
    $this->deleteJson(route('assistente.conversas.destroy', $alheia))->assertNotFound();
    $this->postJson(route('assistente.mensagens.store', $alheia), ['texto' => 'Oi'])->assertNotFound();
});

test('sem chave configurada as rotas respondem 503', function () {
    config(['assistente.chave' => null]);

    $this->actingAs(User::factory()->create())->getJson(route('assistente.conversas.index'))->assertServiceUnavailable();
});
