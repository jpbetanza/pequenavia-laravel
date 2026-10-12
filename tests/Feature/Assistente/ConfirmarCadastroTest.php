<?php

use App\Enums\StatusDaAcao;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeEspaco;
use App\Services\Assistente\Ferramentas\ProporEquipamento;
use App\Services\Assistente\Ferramentas\ProporEspaco;
use App\Services\Assistente\Ferramentas\ProporGrupo;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));
});

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

test('confirmar alterar_espaco com só sábado mantém os outros dias e os bloqueios', function () {
    $usuario = User::factory()->create();
    $espaco = espacoComAgenda($usuario);
    $acao = propostaDeCadastro(ProporEspaco::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Salão', 'horarios' => [['dia' => 'sábado', 'abre' => '08:00', 'fecha' => '18:00']]]);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertOk()
        ->assertJsonPath('acao.resultado.cadastro_id', $espaco->id)
        ->assertJsonPath('acao.resultado.entidade', 'espaco');

    $espaco->refresh();

    expect($espaco->horarios->pluck('dia_semana')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and($espaco->horarios->last()->abre)->toBe('08:00:00')
        ->and($espaco->bloqueios)->toHaveCount(1)
        ->and($espaco->bloqueios->first()->motivo)->toBe('Feriado');
});

test('confirmar criar_espaco grava o espaço com os horários', function () {
    $usuario = User::factory()->create();
    $acao = propostaDeCadastro(ProporEspaco::class, $usuario, [
        'operacao' => 'criar', 'nome' => 'Sala 3', 'capacidade' => 25, 'cor' => 'azul',
        'horarios' => [['dia' => 'segunda', 'abre' => '08:00', 'fecha' => '22:00'], ['dia' => 'terca', 'abre' => '08:00', 'fecha' => '22:00']],
    ]);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))->assertOk();

    $espaco = Espaco::sole();

    expect($espaco)->toMatchArray(['nome' => 'Sala 3', 'capacidade' => 25, 'user_id' => $usuario->id])
        ->and($espaco->horarios->pluck('dia_semana')->all())->toBe([1, 2]);
});

test('confirmar alterar_espaco com horário que fecha antes de abrir responde 422 e não grava', function () {
    $usuario = User::factory()->create();
    $espaco = espacoComAgenda($usuario);
    $acao = propostaDeCadastro(ProporEspaco::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Salão', 'capacidade' => 25]);

    $dados = $acao->dados;
    $dados['final']['horarios'][0]['fecha'] = '07:00';
    $acao->update(['dados' => $dados]);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O fechamento precisa ser depois da abertura.');

    expect($espaco->fresh()->capacidade)->toBe(10)
        ->and($acao->fresh()->status)->toBe(StatusDaAcao::Proposta);
});

test('excluir espaço que ganhou reserva depois da proposta responde 422', function () {
    $usuario = User::factory()->create();
    $espaco = espacoComAgenda($usuario);
    $acao = propostaDeCadastro(ProporEspaco::class, $usuario, ['operacao' => 'excluir', 'alvo' => 'Salão']);

    Reserva::factory()->for($usuario)->create(['espaco_id' => $espaco->id, 'data' => '2026-10-20']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Há reservas futuras usando este espaço. Desative em vez de excluir.');

    expect(Espaco::count())->toBe(1);
});

/** Regrava o espaço pelo gravador da tela, com o estado atual mais as mudanças. */
function gravarEspacoComoTela(Espaco $espaco, array $mudancas): void
{
    $espaco->load(['horarios', 'bloqueios']);

    $dados = [
        'nome' => $espaco->nome, 'capacidade' => $espaco->capacidade, 'localizacao' => $espaco->localizacao,
        'cor' => $espaco->cor, 'recursos_fixos' => $espaco->recursos_fixos, 'aceita_solicitacoes' => $espaco->aceita_solicitacoes,
        'antecedencia_min_horas' => $espaco->antecedencia_min_horas, 'duracao_max_min' => $espaco->duracao_max_min,
        'intervalo_min' => $espaco->intervalo_min, 'ativo' => $espaco->ativo,
        'horarios' => $espaco->horarios->map(fn ($horario) => [
            'dia_semana' => $horario->dia_semana, 'abre' => substr($horario->abre, 0, 5), 'fecha' => substr($horario->fecha, 0, 5),
        ])->all(),
        'bloqueios' => $espaco->bloqueios->map(fn ($bloqueio) => [
            'inicio' => $bloqueio->inicio->toDateString(), 'fim' => $bloqueio->fim->toDateString(), 'motivo' => $bloqueio->motivo,
        ])->all(),
    ];

    app(GravadorDeEspaco::class)->atualizar($espaco, array_replace($dados, $mudancas));
}

test('confirmar alteração de espaço responde 422 quando só os bloqueios mudaram pela tela, e preserva a mudança da tela', function () {
    $usuario = User::factory()->create();
    $espaco = espacoComAgenda($usuario);
    $acao = propostaDeCadastro(ProporEspaco::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Salão', 'capacidade' => 25]);

    gravarEspacoComoTela($espaco, ['bloqueios' => [['inicio' => '2026-12-01', 'fim' => '2026-12-02', 'motivo' => 'Reforma pela tela']]]);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O cadastro mudou desde a proposta. Peça de novo.');

    expect($espaco->fresh()->bloqueios->pluck('motivo')->all())->toBe(['Reforma pela tela'])
        ->and($espaco->fresh()->capacidade)->toBe(10)
        ->and($acao->fresh()->status)->toBe(StatusDaAcao::Proposta);
});

test('confirmar alteração de espaço responde 422 quando só os horários mudaram pela tela, e preserva a mudança da tela', function () {
    $usuario = User::factory()->create();
    $espaco = espacoComAgenda($usuario);
    $acao = propostaDeCadastro(ProporEspaco::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Salão', 'capacidade' => 25]);

    gravarEspacoComoTela($espaco, ['horarios' => [['dia_semana' => 6, 'abre' => '09:00', 'fecha' => '13:00']]]);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O cadastro mudou desde a proposta. Peça de novo.');

    expect($espaco->fresh()->horarios->pluck('dia_semana')->all())->toBe([6])
        ->and($espaco->fresh()->capacidade)->toBe(10)
        ->and($acao->fresh()->status)->toBe(StatusDaAcao::Proposta);
});

test('confirmar alteração de grupo responde 422 quando o grupo muda de novo no mesmo segundo', function () {
    $usuario = User::factory()->create();
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Catequese', 'coordenacao' => 'Ana', 'contato' => '11 9999']);
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);

    $grupo->update(['contato' => 'outro']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O cadastro mudou desde a proposta. Peça de novo.');

    expect($grupo->fresh()->contato)->toBe('outro')
        ->and($acao->fresh()->status)->toBe(StatusDaAcao::Proposta);
});

test('proposta antiga sem impressão digital confere a data de atualização', function () {
    $usuario = User::factory()->create();
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Catequese', 'coordenacao' => 'Ana']);
    $acao = propostaDeCadastro(ProporGrupo::class, $usuario, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);

    $dados = $acao->dados;
    unset($dados['alvo_impressao']);
    $acao->update(['dados' => $dados]);

    $this->travel(1)->seconds();
    $grupo->update(['contato' => 'outro']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O cadastro mudou desde a proposta. Peça de novo.');
});
