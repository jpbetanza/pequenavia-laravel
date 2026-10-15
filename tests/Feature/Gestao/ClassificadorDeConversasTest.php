<?php

use App\Enums\PapelDaMensagem;
use App\Enums\SituacaoDoTema;
use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\TemaDePedido;
use App\Models\User;
use App\Services\Gestao\ClassificadorDeConversas;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config([
        'gestao.jev_chave' => 'chave-jev',
        'gestao.jev_url' => 'https://jev.test/v1/systemone',
        'gestao.jev_modelo' => '~typesafe/jev-latest',
        'assistente.chave' => 'chave-or',
        'assistente.url' => 'https://or.test/chat',
    ]);
});

/** Respostas do JEV para uma conversa resolvida e sem pedido; `$ajustes` troca só as perguntas indicadas. */
function respostasDoJevDaRodada(array $ajustes = []): array
{
    return array_replace([
        'use_case' => ['type' => 'choice', 'choice' => 'reserva', 'probabilities' => ['reserva' => 0.9, 'outro' => 0.1], 'confidence' => 0.9],
        'outcome' => ['type' => 'choice', 'choice' => 'resolvida', 'probabilities' => ['resolvida' => 0.9, 'parcial' => 0.1], 'confidence' => 0.9],
        'frustration' => ['type' => 'score', 'score' => 0.4, 'legend' => 'x', 'probabilities' => [], 'confidence' => 0.8],
        'confused' => ['type' => 'noul', 'noul' => 0.1],
        'unmet_request' => ['type' => 'noul', 'noul' => 0.1],
        'assistant_error' => ['type' => 'noul', 'noul' => 0.05],
        'ended_waiting' => ['type' => 'noul', 'noul' => 0.1],
        'unmet_theme' => ['type' => 'choice', 'choice' => 'nenhum', 'probabilities' => ['nenhum' => 0.9], 'confidence' => 0.9],
    ], $ajustes);
}

function jevDaRodada(array $ajustes = []): array
{
    return [
        'model' => '~typesafe/jev-latest',
        'answers' => respostasDoJevDaRodada($ajustes),
        'usage' => ['input_tokens' => 500, 'output_tokens' => 9],
    ];
}

function haikuDaRodada(?array $pedido = null, string $resumo = 'A secretaria pediu uma reserva.', float $custo = 0.0004): array
{
    return [
        'choices' => [['message' => ['content' => json_encode(['resumo' => $resumo, 'pedido' => $pedido])]]],
        'usage' => ['cost' => $custo],
    ];
}

function conversaDaRodada(mixed $ultimaEm, string $texto = 'Quero reservar o salão.', ?User $usuario = null): Conversa
{
    $usuario ??= User::factory()->create(['name' => 'Joana Souza']);

    $conversa = Conversa::factory()->for($usuario)->create(['ultima_mensagem_em' => $ultimaEm]);
    mensagemDaRodada($conversa, PapelDaMensagem::Usuario, $texto, ['created_at' => $ultimaEm]);

    return $conversa;
}

function mensagemDaRodada(Conversa $conversa, PapelDaMensagem $papel, string $conteudo, array $extras = []): Mensagem
{
    return Mensagem::factory()->for($conversa)->create(array_merge(['papel' => $papel, 'conteudo' => $conteudo], $extras));
}

/** Requisições feitas a uma URL (parte do host), na ordem em que saíram. */
function requisicoesDaRodada(string $parte): Collection
{
    return Http::recorded(fn (Request $request) => str_contains($request->url(), $parte))->map(fn (array $par) => $par[0]);
}

test('classifica a conversa ociosa e ignora as recentes', function () {
    $antiga = conversaDaRodada(now()->subHours(2));
    $recente = conversaDaRodada(now()->subMinutes(10));

    Http::fake(['jev.test/*' => Http::response(jevDaRodada()), 'or.test/*' => Http::response(haikuDaRodada())]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();

    expect($rodada->conversas_lidas)->toBe(1)
        ->and(ClassificacaoDeConversa::where('conversa_id', $antiga->id)->exists())->toBeTrue()
        ->and(ClassificacaoDeConversa::where('conversa_id', $recente->id)->exists())->toBeFalse();
});

test('reclassifica quando chegam mensagens novas e mantém a correção', function () {
    $conversa = conversaDaRodada(now()->subHours(3));

    Http::fake(['jev.test/*' => Http::response(jevDaRodada()), 'or.test/*' => Http::response(haikuDaRodada())]);

    app(ClassificadorDeConversas::class)->rodar();
    ClassificacaoDeConversa::where('conversa_id', $conversa->id)->update(['correcao' => ['caso_de_uso' => 'cadastro']]);

    $sem = app(ClassificadorDeConversas::class)->rodar();
    expect($sem->conversas_lidas)->toBe(0);

    mensagemDaRodada($conversa, PapelDaMensagem::Usuario, 'Mais uma coisa.', ['created_at' => now()->subMinutes(90)]);
    $conversa->update(['ultima_mensagem_em' => now()->subMinutes(90)]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();
    $classificacao = $conversa->classificacao()->first();

    expect($rodada->conversas_lidas)->toBe(1)
        ->and($classificacao->correcao)->toBe(['caso_de_uso' => 'cadastro'])
        ->and($classificacao->mensagens_ate->getTimestamp())->toBe($conversa->ultima_mensagem_em->getTimestamp());
});

test('o Haiku é chamado só quando a conversa vale a leitura', function () {
    $resolvida = conversaDaRodada(now()->subHours(4), 'Oi, quero ver a agenda.');
    $naoResolvida = conversaDaRodada(now()->subHours(3), 'Não consegui reservar.');

    Http::fake([
        'jev.test/*' => Http::sequence()
            ->push(jevDaRodada())
            ->push(jevDaRodada(['outcome' => ['type' => 'choice', 'choice' => 'nao_resolvida', 'probabilities' => ['nao_resolvida' => 0.9], 'confidence' => 0.9]]))
            ->whenEmpty(Http::response(jevDaRodada())),
        'or.test/*' => Http::sequence()
            ->push(haikuDaRodada(resumo: 'Ficou sem a reserva.'))
            ->whenEmpty(Http::response(haikuDaRodada())),
    ]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();

    expect(requisicoesDaRodada('or.test')->count())->toBe(1)
        ->and($rodada->resumidas)->toBe(1)
        ->and($resolvida->classificacao()->first()->resumo)->toBeNull()
        ->and($naoResolvida->classificacao()->first()->resumo)->toBe('Ficou sem a reserva.');
});

test('o tema escolhido pelo JEV com confiança alta vale, mesmo com o Haiku nomeando outro pedido', function () {
    $coral = TemaDePedido::factory()->create(['nome' => 'Coral', 'descricao' => 'Ensaios do coral']);
    $conversa = conversaDaRodada(now()->subHours(2), 'Preciso de uma agenda para o coral.');

    Http::fake([
        'jev.test/*' => Http::response(jevDaRodada([
            'unmet_request' => ['type' => 'noul', 'noul' => 0.9],
            'unmet_theme' => ['type' => 'choice', 'choice' => 'tema_'.$coral->id, 'probabilities' => [], 'confidence' => 0.8],
        ])),
        'or.test/*' => Http::response(haikuDaRodada(['nome' => 'Agenda de coral', 'descricao' => 'Agenda do coral.'])),
    ]);

    app(ClassificadorDeConversas::class)->rodar();

    $classificacao = $conversa->classificacao()->first();

    expect($classificacao->tema_id)->toBe($coral->id)
        ->and($classificacao->tema_origem)->toBe('jev')
        ->and($classificacao->tema_confianca)->toBe(0.8)
        ->and(TemaDePedido::count())->toBe(1);
});

test('o tema do JEV com confiança baixa fica para o Haiku', function () {
    $coral = TemaDePedido::factory()->create(['nome' => 'Coral']);
    $conversa = conversaDaRodada(now()->subHours(2), 'Preciso de uma agenda para o coral.');

    Http::fake([
        'jev.test/*' => Http::response(jevDaRodada([
            'unmet_request' => ['type' => 'noul', 'noul' => 0.9],
            'unmet_theme' => ['type' => 'choice', 'choice' => 'tema_'.$coral->id, 'probabilities' => [], 'confidence' => 0.4],
        ])),
        'or.test/*' => Http::response(haikuDaRodada(['nome' => 'Agenda de coral', 'descricao' => 'Agenda do coral.'])),
    ]);

    app(ClassificadorDeConversas::class)->rodar();

    $classificacao = $conversa->classificacao()->first();
    $novo = TemaDePedido::where('nome', 'Agenda de coral')->first();

    expect($classificacao->tema_id)->toBe($novo->id)
        ->and($classificacao->tema_origem)->toBe('haiku')
        ->and($classificacao->tema_confianca)->toBeNull()
        ->and($novo->criado_por)->toBe('haiku');
});

test('o tema novo do Haiku é criado uma vez só, com o nome normalizado igual', function () {
    $antiga = conversaDaRodada(now()->subHours(4), 'Quero mudar o horário do coral.');
    $nova = conversaDaRodada(now()->subHours(3), 'Mudar o horário de novo.');

    Http::fake([
        'jev.test/*' => Http::response(jevDaRodada(['unmet_request' => ['type' => 'noul', 'noul' => 0.9]])),
        'or.test/*' => Http::sequence()
            ->push(haikuDaRodada(['nome' => 'Mudança de Horário', 'descricao' => 'Trocar o horário.']))
            ->push(haikuDaRodada(['nome' => 'mudanca de horario', 'descricao' => 'Trocar o horário.']))
            ->whenEmpty(Http::response(haikuDaRodada())),
    ]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();

    expect(TemaDePedido::where('criado_por', 'haiku')->count())->toBe(1)
        ->and($rodada->temas_novos)->toBe(1)
        ->and($antiga->classificacao()->first()->tema_id)->toBe(TemaDePedido::first()->id)
        ->and($nova->classificacao()->first()->tema_id)->toBe(TemaDePedido::first()->id);
});

test('o tema do Haiku que bate com um tema juntado leva ao tema de destino, mesmo numa cadeia', function () {
    $final = TemaDePedido::factory()->create(['nome' => 'Horários', 'situacao' => SituacaoDoTema::Acompanhando]);
    $meio = TemaDePedido::factory()->create(['nome' => 'Horário do coral', 'situacao' => SituacaoDoTema::Descartado, 'juntado_em_id' => $final->id]);
    TemaDePedido::factory()->create(['nome' => 'Mudança de Horário', 'situacao' => SituacaoDoTema::Descartado, 'juntado_em_id' => $meio->id]);
    $conversa = conversaDaRodada(now()->subHours(3), 'Quero mudar o horário do coral.');

    Http::fake([
        'jev.test/*' => Http::response(jevDaRodada(['unmet_request' => ['type' => 'noul', 'noul' => 0.9]])),
        'or.test/*' => Http::response(haikuDaRodada(['nome' => 'mudanca de horario', 'descricao' => 'Trocar o horário.'])),
    ]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();

    $classificacao = $conversa->classificacao()->first();

    expect($classificacao->tema_id)->toBe($final->id)
        ->and($classificacao->tema_origem)->toBe('haiku')
        ->and($rodada->temas_novos)->toBe(0)
        ->and(TemaDePedido::count())->toBe(3);
});

test('a falha de uma conversa não derruba a rodada', function () {
    $falha = conversaDaRodada(now()->subHours(4), 'FALHA: pedido de teste.');
    $ok = conversaDaRodada(now()->subHours(3));

    Http::fake([
        'jev.test/*' => function (Request $request) {
            return str_contains($request->body(), 'FALHA')
                ? Http::response(['erro' => 'chave'], 401)
                : Http::response(jevDaRodada());
        },
        'or.test/*' => Http::response(haikuDaRodada()),
    ]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();

    expect($rodada->falhas)->toBe(1)
        ->and($rodada->conversas_lidas)->toBe(2)
        ->and($rodada->erro)->toBeNull()
        ->and(ClassificacaoDeConversa::where('conversa_id', $falha->id)->exists())->toBeFalse()
        ->and(ClassificacaoDeConversa::where('conversa_id', $ok->id)->exists())->toBeTrue();
});

test('para a rodada depois de três falhas seguidas do JEV e a fecha com erro', function () {
    foreach ([5, 4, 3, 2] as $horas) {
        conversaDaRodada(now()->subHours($horas));
    }

    Http::fake(['jev.test/*' => Http::response(['erro' => 'fora'], 503)]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();

    expect($rodada->falhas)->toBe(3)
        ->and($rodada->conversas_lidas)->toBe(3)
        ->and($rodada->erro)->not->toBeNull()
        ->and($rodada->terminada_em)->not->toBeNull()
        // Cada conversa tenta uma vez e mais três novas tentativas (503 é reenviado); a quarta conversa nem sai.
        ->and(requisicoesDaRodada('jev.test')->count())->toBe(3 * 4);
});

test('uma escolha fora das opções e uma confiança baixa mandam a conversa para a revisão', function () {
    $fora = conversaDaRodada(now()->subHours(4));
    $baixa = conversaDaRodada(now()->subHours(3));

    Http::fake([
        'jev.test/*' => Http::sequence()
            ->push(jevDaRodada(['use_case' => ['type' => 'choice', 'choice' => 'inventado', 'probabilities' => [], 'confidence' => 0.9]]))
            ->push(jevDaRodada(['use_case' => ['type' => 'choice', 'choice' => 'reserva', 'probabilities' => ['reserva' => 0.5, 'outro' => 0.5], 'confidence' => 0.4]]))
            ->whenEmpty(Http::response(jevDaRodada())),
        'or.test/*' => Http::response(haikuDaRodada()),
    ]);

    app(ClassificadorDeConversas::class)->rodar();

    expect($fora->classificacao()->first())->a_revisar->toBeTrue()
        ->and($fora->classificacao()->first()->caso_de_uso)->toBe('outro')
        ->and($baixa->classificacao()->first())->a_revisar->toBeTrue()
        ->and($baixa->classificacao()->first()->caso_confianca)->toBe(0.4);
});

test('a máscara chega ao JEV: o corpo enviado não tem e-mail, telefone nem o nome do dono', function () {
    conversaDaRodada(now()->subHours(2), 'Joana, mande para maria@exemplo.com ou (11) 98888-7777.');

    Http::fake(['jev.test/*' => Http::response(jevDaRodada()), 'or.test/*' => Http::response(haikuDaRodada())]);

    app(ClassificadorDeConversas::class)->rodar();

    $corpo = requisicoesDaRodada('jev.test')->first()->body();

    expect($corpo)->not->toContain('maria@exemplo.com')
        ->and($corpo)->not->toContain('98888-7777')
        ->and($corpo)->not->toContain('Joana')
        ->and($corpo)->toContain('[e-mail]')
        ->and($corpo)->toContain('[telefone]')
        ->and($corpo)->toContain('[nome]');
});

test('os temas descartados e os juntados a outro não viram opção do JEV', function () {
    $ativo = TemaDePedido::factory()->create(['nome' => 'Coral']);
    $descartado = TemaDePedido::factory()->create(['nome' => 'Velho', 'situacao' => SituacaoDoTema::Descartado]);
    $juntado = TemaDePedido::factory()->create(['nome' => 'Juntado', 'juntado_em_id' => $ativo->id]);
    conversaDaRodada(now()->subHours(2));

    Http::fake(['jev.test/*' => Http::response(jevDaRodada()), 'or.test/*' => Http::response(haikuDaRodada())]);

    app(ClassificadorDeConversas::class)->rodar();

    $opcoes = array_keys(requisicoesDaRodada('jev.test')->first()['questions']['unmet_theme']['criteria']);

    expect($opcoes)->toContain('tema_'.$ativo->id)
        ->and($opcoes)->not->toContain('tema_'.$descartado->id)
        ->and($opcoes)->not->toContain('tema_'.$juntado->id);
});

test('monta o estado sem as mensagens de ferramenta e registra ferramentas, ações e tokens', function () {
    $conversa = conversaDaRodada(now()->subHours(2), 'Tem sala livre sábado?');
    mensagemDaRodada($conversa, PapelDaMensagem::Assistente, 'Vou consultar a agenda.', [
        'chamadas' => [['id' => 'c1', 'nome' => 'consultar_agenda', 'argumentos' => []]],
        'tokens_entrada' => 1200,
        'tokens_saida' => 300,
    ]);
    mensagemDaRodada($conversa, PapelDaMensagem::Ferramenta, '{"livre": true}', ['ferramenta_id' => 'c1']);
    mensagemDaRodada($conversa, PapelDaMensagem::Assistente, 'O salão está livre.', ['tokens_entrada' => 800, 'tokens_saida' => 40]);
    AcaoProposta::create([
        'conversa_id' => $conversa->id,
        'user_id' => $conversa->user_id,
        'tipo' => 'reserva',
        'dados' => [],
        'status' => StatusDaAcao::Confirmada,
    ]);

    Http::fake(['jev.test/*' => Http::response(jevDaRodada()), 'or.test/*' => Http::response(haikuDaRodada())]);

    app(ClassificadorDeConversas::class)->rodar();

    $classificacao = $conversa->classificacao()->first();
    $conversaEnviada = requisicoesDaRodada('jev.test')->first()['state']['conversation'];

    expect($conversaEnviada)->toHaveCount(3)
        ->and($conversaEnviada[1]['tools_used'])->toBe(['consultar_agenda'])
        ->and($classificacao->ferramentas)->toBe(['consultar_agenda'])
        ->and($classificacao->acoes_propostas)->toBe(1)
        ->and($classificacao->acoes_confirmadas)->toBe(1)
        ->and($classificacao->mensagens)->toBe(4)
        ->and($classificacao->tokens_assistente_entrada)->toBe(2000)
        ->and($classificacao->tokens_assistente_saida)->toBe(340);
});

test('sem resumo quando o Haiku falha, a conversa ainda é classificada', function () {
    $conversa = conversaDaRodada(now()->subHours(2));

    Http::fake([
        'jev.test/*' => Http::response(jevDaRodada(['outcome' => ['type' => 'choice', 'choice' => 'nao_resolvida', 'probabilities' => ['nao_resolvida' => 0.9], 'confidence' => 0.9]])),
        'or.test/*' => Http::response(['erro' => 'indisponível'], 500),
    ]);

    $rodada = app(ClassificadorDeConversas::class)->rodar();

    expect($conversa->classificacao()->first())->not->toBeNull()
        ->and($conversa->classificacao()->first()->resumo)->toBeNull()
        ->and($rodada->resumidas)->toBe(0)
        ->and($rodada->erro)->toBeNull();
});

test('fecha a rodada com os totais e os custos de JEV e Haiku', function () {
    conversaDaRodada(now()->subHours(2));

    Http::fake([
        'jev.test/*' => Http::response(jevDaRodada(['outcome' => ['type' => 'choice', 'choice' => 'nao_resolvida', 'probabilities' => ['nao_resolvida' => 0.9], 'confidence' => 0.9]])),
        'or.test/*' => Http::response(haikuDaRodada(custo: 0.0004)),
    ]);

    $rodada = app(ClassificadorDeConversas::class)->rodar()->fresh();

    expect($rodada->terminada_em)->not->toBeNull()
        ->and($rodada->conversas_lidas)->toBe(1)
        ->and($rodada->resumidas)->toBe(1)
        ->and($rodada->tokens_jev)->toBe(500)
        ->and((float) $rodada->custo_jev)->toBe(0.000021)
        ->and((float) $rodada->custo_haiku)->toBe(0.0004)
        ->and($rodada->erro)->toBeNull();
});

test('o comando gestao:classificar roda a rodada e informa o resumo', function () {
    conversaDaRodada(now()->subHours(2));

    Http::fake(['jev.test/*' => Http::response(jevDaRodada()), 'or.test/*' => Http::response(haikuDaRodada())]);

    $this->artisan('gestao:classificar')
        ->expectsOutputToContain('1 conversas lidas')
        ->assertSuccessful();
});

test('agenda a classificação às 05:00 e às 17:00, em Brasília, sem sobreposição', function () {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn (Event $evento) => str_contains((string) $evento->command, 'gestao:classificar'))
        ->values();

    expect($eventos)->toHaveCount(2)
        ->and($eventos->map(fn (Event $evento) => $evento->getExpression())->sort()->values()->all())->toBe(['0 17 * * *', '0 5 * * *'])
        ->and($eventos->every(fn (Event $evento) => $evento->timezone === 'America/Sao_Paulo' && $evento->withoutOverlapping))->toBeTrue();
});
