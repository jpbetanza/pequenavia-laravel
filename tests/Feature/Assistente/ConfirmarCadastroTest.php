<?php

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ProporEquipamento;
use App\Services\Assistente\Ferramentas\ProporGrupo;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));
});

function propostaDeCadastro(string $ferramenta, User $usuario, array $argumentos): AcaoProposta
{
    app($ferramenta)->executar($usuario, Conversa::factory()->for($usuario)->create(), $argumentos);

    return AcaoProposta::latest('created_at')->firstOrFail();
}

test('confirmar criar_grupo cria o grupo e devolve o cartão confirmado', function () {
    $usuario = User::factory()->create();
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'criar', 'nome' => 'Coral', 'coordenacao' => 'Ana']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertOk()
        ->assertJsonPath('acao.status', 'confirmada')
        ->assertJsonPath('acao.tipo', 'criar_grupo')
        ->assertJsonPath('acao.resultado.cadastro_id', Grupo::sole()->id)
        ->assertJsonPath('acao.resultado.entidade', 'grupo');

    expect(Grupo::sole())->toMatchArray(['nome' => 'Coral', 'coordenacao' => 'Ana', 'user_id' => $usuario->id]);
});

test('confirmar alterar_grupo aplica só a mudança', function () {
    $usuario = User::factory()->create();
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Catequese', 'coordenacao' => 'Ana', 'contato' => '11 9999']);
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))->assertOk();

    expect($grupo->fresh())->toMatchArray(['nome' => 'Catequese', 'coordenacao' => 'Bia', 'contato' => '11 9999']);
});

test('alterar depois de o cadastro mudar responde 422 e não grava', function () {
    $usuario = User::factory()->create();
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Catequese', 'coordenacao' => 'Ana', 'contato' => '11 9999']);
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);

    $this->travel(1)->seconds();
    $grupo->update(['contato' => 'outro']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O cadastro mudou desde a proposta. Peça de novo.');

    expect($grupo->fresh()->contato)->toBe('outro')
        ->and($acao->fresh()->status)->toBe(StatusDaAcao::Proposta);
});

test('criar grupo cujo nome passou a existir responde 422 e não cria nada', function () {
    $usuario = User::factory()->create();
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'criar', 'nome' => 'Coral']);

    Grupo::factory()->for($usuario)->create(['nome' => 'Coral']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Já existe um grupo com este nome.');

    expect(Grupo::count())->toBe(1);
});

test('confirmar excluir_equipamento sem reservas exclui o equipamento', function () {
    $usuario = User::factory()->create();
    Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $acao = propostaDeCadastro(ProporEquipamento::class, $usuario, ['operacao' => 'excluir', 'alvo' => 'Projetor']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertOk()
        ->assertJsonPath('acao.status', 'confirmada');

    expect(Equipamento::count())->toBe(0);
});

test('excluir equipamento com reserva criada depois da proposta responde 422', function () {
    $usuario = User::factory()->create();
    $projetor = Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $acao = propostaDeCadastro(ProporEquipamento::class, $usuario, ['operacao' => 'excluir', 'alvo' => 'Projetor']);

    $reserva = Reserva::factory()->for($usuario)->create([
        'espaco_id' => Espaco::factory()->for($usuario)->create()->id, 'data' => '2026-10-20',
    ]);
    $reserva->equipamentos()->attach($projetor->id, ['quantidade' => 1]);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Há reservas futuras usando este equipamento. Desative em vez de excluir.');

    expect(Equipamento::count())->toBe(1);
});

test('confirmar duas vezes um cadastro responde 422 e não repete a gravação', function () {
    $usuario = User::factory()->create();
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'criar', 'nome' => 'Coral']);
    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))->assertOk();

    $this->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta ação não está mais pendente.');

    expect(Grupo::count())->toBe(1);
});

test('ação de cadastro de outro usuário responde 404', function () {
    $usuario = User::factory()->create();
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'criar', 'nome' => 'Coral']);

    $this->actingAs(User::factory()->create())->postJson(route('assistente.acoes.confirmar', $acao))->assertNotFound();
});
