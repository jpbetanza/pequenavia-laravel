<?php

use App\Enums\SituacaoDoTema;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\RodadaDeClassificacao;
use App\Models\TemaDePedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // 17/10/2026 15h30 em São Paulo.
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));
});

function gestorDosTemas(): User
{
    $gestor = User::factory()->create();
    $gestor->forceFill(['admin' => true])->save();

    return $gestor;
}

/** Classificação de uma conversa do cliente, com tema (JEV) e outros campos opcionais. */
function classificacaoComTema(User $cliente, array $atributos = [], ?string $resumo = null): ClassificacaoDeConversa
{
    $conversa = Conversa::factory()->for($cliente)->create();

    return ClassificacaoDeConversa::factory()->for($conversa)->create(array_merge([
        'user_id' => $cliente->id,
        'classificada_em' => now(),
        'mensagens_ate' => now(),
        'resumo' => $resumo,
    ], $atributos));
}

it('lista os temas ativos com contagens do período e da semana, e deixa de fora os juntados e descartados', function () {
    $ana = User::factory()->create(['admin' => false]);
    $bruno = User::factory()->create(['admin' => false]);

    $salaDeVideo = TemaDePedido::factory()->create(['nome' => 'Sala de vídeo']);
    $outro = TemaDePedido::factory()->create(['nome' => 'Outro tema']);
    TemaDePedido::factory()->create(['nome' => 'Juntado', 'juntado_em_id' => $outro->id, 'situacao' => SituacaoDoTema::Descartado]);
    TemaDePedido::factory()->create(['nome' => 'Descartado', 'situacao' => SituacaoDoTema::Descartado]);

    // Duas conversas desta semana, de duas paróquias, uma com frustração alta.
    classificacaoComTema($ana, ['tema_id' => $salaDeVideo->id, 'frustracao' => 2.0]);
    classificacaoComTema($bruno, ['tema_id' => $salaDeVideo->id, 'frustracao' => 0.5]);

    // Uma de 10 dias atrás: conta no período e na semana anterior, não na semana.
    classificacaoComTema($ana, ['tema_id' => $salaDeVideo->id, 'classificada_em' => now()->subDays(10)]);

    $this->actingAs(gestorDosTemas())
        ->get(route('gestao.temas.index', ['periodo' => 30]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gestao/temas')
            ->where('periodo', 30)
            ->has('temas', 2)
            ->where('temas.0.nome', 'Sala de vídeo')
            ->where('temas.0.conversas', 3)
            ->where('temas.0.paroquias', 2)
            ->where('temas.0.frustradas', 1)
            ->where('temas.0.semana', 2)
            ->where('temas.0.semana_anterior', 1)
            ->where('temas.0.situacao', 'novo')
            ->where('temas.0.situacao_rotulo', 'Novo')
            ->where('temas.0.criado_por', 'haiku')
            ->where('temas.1.nome', 'Outro tema')
            ->where('temas.1.conversas', 0));
});

it('conta o tema efetivo: a correção do gestor muda o tema da conversa nas contagens', function () {
    $ana = User::factory()->create(['admin' => false]);
    $origem = TemaDePedido::factory()->create(['nome' => 'Tema do JEV']);
    $destino = TemaDePedido::factory()->create(['nome' => 'Tema corrigido']);

    classificacaoComTema($ana, [
        'tema_id' => $origem->id,
        'tema_origem' => 'jev',
        'correcao' => ['tema_id' => $destino->id],
    ]);

    // Correção para "nenhum tema" tira a conversa de todos os temas.
    classificacaoComTema($ana, [
        'tema_id' => $origem->id,
        'tema_origem' => 'jev',
        'correcao' => ['tema_id' => null],
    ]);

    $this->actingAs(gestorDosTemas())
        ->get(route('gestao.temas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('temas.0.nome', 'Tema corrigido')
            ->where('temas.0.conversas', 1)
            ->where('temas.1.nome', 'Tema do JEV')
            ->where('temas.1.conversas', 0));
});

it('mostra a última rodada com os totais e os custos', function () {
    $gestor = gestorDosTemas();

    $this->actingAs($gestor)
        ->get(route('gestao.temas.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rodada', null));

    RodadaDeClassificacao::query()->create([
        'iniciada_em' => now()->subHours(3),
        'terminada_em' => now()->subHours(3)->addMinutes(4),
        'conversas_lidas' => 10,
        'resumidas' => 4,
        'temas_novos' => 1,
        'falhas' => 0,
        'tokens_jev' => 5000,
        'custo_jev' => 0.00021,
        'custo_haiku' => 0.0123,
    ]);
    RodadaDeClassificacao::query()->create([
        'iniciada_em' => now()->subDay(),
        'conversas_lidas' => 1,
    ]);

    $this->actingAs($gestor)
        ->get(route('gestao.temas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rodada.conversas_lidas', 10)
            ->where('rodada.resumidas', 4)
            ->where('rodada.temas_novos', 1)
            ->where('rodada.tokens_jev', 5000)
            ->where('rodada.custo_jev', 0.00021)
            ->where('rodada.custo_haiku', 0.0123)
            ->where('rodada.erro', null));
});

it('mostra o tema pedido com os três resumos mais recentes e a paróquia de cada um', function () {
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana']);
    $bruno = User::factory()->create(['admin' => false, 'name' => 'Bruno']);
    $tema = TemaDePedido::factory()->create(['nome' => 'Lista de presença']);
    $outro = TemaDePedido::factory()->create(['nome' => 'Outro']);

    classificacaoComTema($ana, ['tema_id' => $tema->id, 'classificada_em' => now()->subDays(4)], 'Resumo antigo');
    classificacaoComTema($bruno, ['tema_id' => $tema->id, 'classificada_em' => now()->subDays(2)], 'Resumo do meio');
    classificacaoComTema($ana, ['tema_id' => $tema->id, 'classificada_em' => now()->subDay()], 'Resumo novo');
    classificacaoComTema($bruno, ['tema_id' => $tema->id, 'classificada_em' => now()->subHours(2)], 'Resumo mais novo');
    classificacaoComTema($bruno, ['tema_id' => $tema->id, 'classificada_em' => now()], null);
    classificacaoComTema($bruno, ['tema_id' => $outro->id], 'Outro tema');

    $this->actingAs(gestorDosTemas())
        ->get(route('gestao.temas.index', ['tema' => $tema->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selecionado.tema.id', $tema->id)
            ->where('selecionado.tema.nome', 'Lista de presença')
            ->has('selecionado.exemplos', 3)
            ->where('selecionado.exemplos.0.resumo', 'Resumo mais novo')
            ->where('selecionado.exemplos.0.paroquia', 'Bruno')
            ->where('selecionado.exemplos.1.resumo', 'Resumo novo')
            ->where('selecionado.exemplos.1.paroquia', 'Ana')
            ->where('selecionado.exemplos.2.resumo', 'Resumo do meio'));

    // Sem ?tema=, cai no primeiro da lista (o de mais conversas no período).
    $this->actingAs(gestorDosTemas())
        ->get(route('gestao.temas.index'))
        ->assertInertia(fn (Assert $page) => $page->where('selecionado.tema.id', $tema->id));
});

it('atualiza nome, descrição e situação, e aceita o próprio nome de volta', function () {
    $tema = TemaDePedido::factory()->create(['nome' => 'Antigo', 'descricao' => 'Descrição antiga']);
    $gestor = gestorDosTemas();

    $this->actingAs($gestor)
        ->patch(route('gestao.temas.update', $tema), ['nome' => 'Novo nome', 'descricao' => 'Descrição nova', 'situacao' => 'planejado'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tema->refresh())
        ->nome->toBe('Novo nome')
        ->descricao->toBe('Descrição nova')
        ->situacao->toBe(SituacaoDoTema::Planejado);

    // Só a situação: os outros campos ficam como estavam.
    $this->actingAs($gestor)
        ->patch(route('gestao.temas.update', $tema), ['situacao' => 'acompanhando'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tema->refresh())
        ->nome->toBe('Novo nome')
        ->descricao->toBe('Descrição nova')
        ->situacao->toBe(SituacaoDoTema::Acompanhando);

    // Mandar o próprio nome de volta não conta como repetido.
    $this->actingAs($gestor)
        ->patch(route('gestao.temas.update', $tema), ['nome' => 'Novo nome'])
        ->assertSessionHasNoErrors();
});

it('valida o tema na edição: nome único, tamanhos e situação', function () {
    $tema = TemaDePedido::factory()->create(['nome' => 'Primeiro']);
    TemaDePedido::factory()->create(['nome' => 'Já existe']);
    $gestor = gestorDosTemas();
    $url = route('gestao.temas.update', $tema);

    $this->actingAs($gestor)->patch($url, ['nome' => 'Já existe'])->assertSessionHasErrors('nome');
    $this->actingAs($gestor)->patch($url, ['nome' => str_repeat('a', 81)])->assertSessionHasErrors('nome');
    $this->actingAs($gestor)->patch($url, ['nome' => ''])->assertSessionHasErrors('nome');
    $this->actingAs($gestor)->patch($url, ['descricao' => str_repeat('a', 301)])->assertSessionHasErrors('descricao');
    $this->actingAs($gestor)->patch($url, ['situacao' => 'quase'])->assertSessionHasErrors('situacao');

    expect($tema->refresh()->nome)->toBe('Primeiro');
});

it('junta um tema em outro: move as classificações e as correções, e descarta a origem', function () {
    $ana = User::factory()->create(['admin' => false]);
    $origem = TemaDePedido::factory()->create(['nome' => 'Origem', 'situacao' => SituacaoDoTema::Planejado]);
    $destino = TemaDePedido::factory()->create(['nome' => 'Destino']);
    $outro = TemaDePedido::factory()->create(['nome' => 'Não mexer']);

    $peloJev = classificacaoComTema($ana, ['tema_id' => $origem->id, 'tema_origem' => 'haiku']);
    $corrigida = classificacaoComTema($ana, ['tema_id' => $outro->id, 'correcao' => ['tema_id' => $origem->id, 'resultado' => 'parcial']]);
    $deOutroTema = classificacaoComTema($ana, ['tema_id' => $outro->id]);

    $this->actingAs(gestorDosTemas())
        ->post(route('gestao.temas.juntar', $origem), ['destino_id' => $destino->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($peloJev->refresh()->tema_id)->toBe($destino->id)
        ->and($peloJev->tema_origem)->toBe('haiku')
        ->and($corrigida->refresh()->correcao)->toEqualCanonicalizing(['tema_id' => $destino->id, 'resultado' => 'parcial'])
        ->and($corrigida->tema_id)->toBe($outro->id)
        ->and($deOutroTema->refresh()->tema_id)->toBe($outro->id)
        ->and($origem->refresh()->juntado_em_id)->toBe($destino->id)
        ->and($origem->situacao)->toBe(SituacaoDoTema::Descartado);

    // A origem some da lista e o destino ganha as contagens.
    $this->actingAs(gestorDosTemas())
        ->get(route('gestao.temas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('temas', fn ($temas) => collect($temas)->pluck('nome')->doesntContain('Origem'))
            ->where('temas', fn ($temas) => collect($temas)->firstWhere('nome', 'Destino')['conversas'] === 2));
});

it('não junta um tema nele mesmo, nem em tema descartado ou já juntado', function () {
    $origem = TemaDePedido::factory()->create();
    $descartado = TemaDePedido::factory()->create(['situacao' => SituacaoDoTema::Descartado]);
    $juntado = TemaDePedido::factory()->create(['juntado_em_id' => $descartado->id]);
    $gestor = gestorDosTemas();

    $this->actingAs($gestor)
        ->post(route('gestao.temas.juntar', $origem), ['destino_id' => $origem->id])
        ->assertSessionHasErrors('destino_id');

    $this->actingAs($gestor)
        ->post(route('gestao.temas.juntar', $origem), ['destino_id' => $descartado->id])
        ->assertSessionHasErrors('destino_id');

    $this->actingAs($gestor)
        ->post(route('gestao.temas.juntar', $juntado), ['destino_id' => $origem->id])
        ->assertSessionHasErrors('destino_id');

    $this->actingAs($gestor)
        ->post(route('gestao.temas.juntar', $origem), [])
        ->assertSessionHasErrors('destino_id');

    expect($origem->refresh()->juntado_em_id)->toBeNull();
});

it('responde 403 a quem não é gestor nos temas', function () {
    $tema = TemaDePedido::factory()->create();
    $destino = TemaDePedido::factory()->create();
    $secretaria = User::factory()->create(['admin' => false]);

    $this->actingAs($secretaria)->get(route('gestao.temas.index'))->assertForbidden();
    $this->actingAs($secretaria)->patch(route('gestao.temas.update', $tema), ['nome' => 'Invasão'])->assertForbidden();
    $this->actingAs($secretaria)->post(route('gestao.temas.juntar', $tema), ['destino_id' => $destino->id])->assertForbidden();

    expect($tema->refresh()->nome)->not->toBe('Invasão')
        ->and($tema->juntado_em_id)->toBeNull();
});
