<?php

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Espaco;
use App\Models\Mensagem;
use App\Models\User;
use App\Services\Assistente\Assistente;
use App\Services\Assistente\ClienteOpenRouter;
use App\Services\Assistente\EventoDoAssistente;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use RuntimeException;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['assistente.chave' => 'chave-de-teste']);
});

/** @return list<EventoDoAssistente> */
function eventos(User $usuario, Conversa $conversa, string $texto): array
{
    return iterator_to_array(app(Assistente::class)->responder($usuario, $conversa, $texto), false);
}

test('resposta direta: texto em pedaços, mensagem gravada e fim', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Olá'), chunkDeTexto('!'), chunkDeUso(50, 2)]))]);
    $conversa = Conversa::factory()->create(['titulo' => 'Nova conversa']);

    $eventos = eventos($conversa->user, $conversa, 'Oi, tudo bem? Preciso de ajuda');

    expect(array_map(fn ($e) => $e->tipo, $eventos))->toBe(['texto', 'texto', 'fim'])
        ->and($conversa->fresh()->titulo)->toBe('Oi, tudo bem?')
        ->and($conversa->mensagens()->pluck('papel')->all())->toBe([PapelDaMensagem::Usuario, PapelDaMensagem::Assistente])
        ->and($conversa->mensagens()->latest('created_at')->first()->only('conteudo', 'tokens_entrada'))->toBe(['conteudo' => 'Olá!', 'tokens_entrada' => 50])
        ->and(end($eventos)->dados['conversa'])->toBe(['id' => $conversa->id, 'titulo' => 'Oi, tudo bem?']);
});

test('ciclo com ferramenta: status, cartão, segunda rodada e cartões gravados na resposta final', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    Http::fake(['openrouter.ai/*' => Http::sequence()
        ->push(sseDoOpenRouter([chunkDeFerramenta(0, 'c1', 'verificar_disponibilidade', json_encode(['espaco' => $salao->id, 'data' => now()->addDays(3)->toDateString()]))]))
        ->push(sseDoOpenRouter([chunkDeTexto('Está livre à tarde.')]))]);
    $conversa = Conversa::factory()->for($usuario)->create();

    $eventos = eventos($usuario, $conversa, 'O salão está livre?');

    expect(array_map(fn ($e) => $e->tipo, $eventos))->toBe(['status', 'cartao', 'texto', 'fim'])
        ->and($eventos[0]->dados['texto'])->toBe('Verificando a disponibilidade…')
        ->and($eventos[1]->dados['tipo'])->toBe('disponibilidade');

    $final = $conversa->mensagens()->where('papel', PapelDaMensagem::Assistente)->whereNull('chamadas')->sole();
    expect($final->conteudo)->toBe('Está livre à tarde.')->and($final->cartoes[0]['tipo'])->toBe('disponibilidade');

    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => collect($r['messages'])->contains(fn ($m) => $m['role'] === 'tool' && $m['tool_call_id'] === 'c1'));
});

test('falha do modelo grava a pergunta e emite erro', function () {
    Http::fake(['openrouter.ai/*' => Http::response('', 429)]);
    $conversa = Conversa::factory()->create();

    $eventos = eventos($conversa->user, $conversa, 'Oi');

    expect(end($eventos)->tipo)->toBe('erro')
        ->and(end($eventos)->dados['mensagem'])->toBe('Não consegui responder agora. Tente de novo.')
        ->and($conversa->mensagens()->where('papel', PapelDaMensagem::Usuario)->count())->toBe(1)
        ->and($conversa->mensagens()->where('erro', true)->count())->toBe(1);
});

test('falha numa rodada posterior grava o texto já transmitido antes da mensagem de erro', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    Http::fake(['openrouter.ai/*' => Http::sequence()
        ->push(sseDoOpenRouter([chunkDeTexto('Vou consultar. '), chunkDeFerramenta(0, 'c1', 'verificar_disponibilidade', json_encode(['espaco' => $salao->id, 'data' => now()->addDays(3)->toDateString()]))]))
        ->push('', 500)]);
    $conversa = Conversa::factory()->for($usuario)->create();

    $eventos = eventos($usuario, $conversa, 'O salão está livre?');

    $respostas = $conversa->mensagens()->where('papel', PapelDaMensagem::Assistente)->whereNull('chamadas')->orderBy('created_at')->get();
    expect(end($eventos)->tipo)->toBe('erro')
        ->and($respostas->pluck('conteudo')->all())->toBe(['Vou consultar. ', 'Não consegui responder agora. Tente de novo.'])
        ->and($respostas->pluck('erro')->all())->toBe([false, true]);
});

test('limite de rodadas de ferramenta encerra com mensagem fixa', function () {
    config(['assistente.max_rodadas_de_ferramenta' => 2]);
    $chamada = sseDoOpenRouter([chunkDeFerramenta(0, 'c1', 'listar_cadastros', '{}')]);
    Http::fake(['openrouter.ai/*' => Http::sequence()->push($chamada)->push($chamada)->push($chamada)]);
    $conversa = Conversa::factory()->create();

    $eventos = eventos($conversa->user, $conversa, 'Oi');

    Http::assertSentCount(2);
    expect(end($eventos)->tipo)->toBe('fim')
        ->and(Mensagem::where('papel', PapelDaMensagem::Assistente)->whereNull('chamadas')->sole()->conteudo)->toBe('Não consegui concluir essa consulta. Pode reformular?');
});

test('texto de uma rodada antes da ferramenta fica na resposta final', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    Http::fake(['openrouter.ai/*' => Http::sequence()
        ->push(sseDoOpenRouter([chunkDeTexto('Vou consultar. '), chunkDeFerramenta(0, 'c1', 'verificar_disponibilidade', json_encode(['espaco' => $salao->id, 'data' => now()->addDays(3)->toDateString()]))]))
        ->push(sseDoOpenRouter([chunkDeTexto('Está livre.')]))]);
    $conversa = Conversa::factory()->for($usuario)->create();

    eventos($usuario, $conversa, 'O salão está livre?');

    $final = $conversa->mensagens()->where('papel', PapelDaMensagem::Assistente)->whereNull('chamadas')->sole();
    $chamada = $conversa->mensagens()->where('papel', PapelDaMensagem::Assistente)->whereNotNull('chamadas')->sole();
    expect($final->conteudo)->toBe('Vou consultar. Está livre.')
        ->and($chamada->conteudo)->toBeNull();
});

test('interrupção no meio do texto grava o trecho já recebido', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Olá'), chunkDeTexto(', tudo bem?')]))]);
    $conversa = Conversa::factory()->create();

    $gerador = app(Assistente::class)->responder($conversa->user, $conversa, 'Oi');
    foreach ($gerador as $evento) {
        break;
    }
    unset($gerador);
    gc_collect_cycles();

    $parcial = $conversa->mensagens()->where('papel', PapelDaMensagem::Assistente)->sole();
    expect($parcial->conteudo)->toBe('Olá')->and($parcial->erro)->toBeFalse();
});

test('erro inesperado grava a mensagem de erro e emite o evento erro, sem deixar o stream mudo', function () {
    Exceptions::fake();
    $this->mock(ClienteOpenRouter::class)
        ->shouldReceive('conversar')
        ->andThrow(new RuntimeException('falha inesperada'));
    $conversa = Conversa::factory()->create();

    $eventos = eventos($conversa->user, $conversa, 'Oi');

    expect(array_map(fn ($e) => $e->tipo, $eventos))->toBe(['erro'])
        ->and(end($eventos)->dados['mensagem'])->toBe('Não consegui responder agora. Tente de novo.')
        ->and($conversa->mensagens()->where('papel', PapelDaMensagem::Assistente)->where('erro', true)->count())->toBe(1);

    Exceptions::assertReported(RuntimeException::class);
});
