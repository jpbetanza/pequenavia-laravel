<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-10 09:00', config('agendamentos.fuso')));
});

/**
 * Reserva de hoje do dono, com um equipamento vinculado.
 *
 * @return array{user: User, reserva: Reserva, equipamento: Equipamento}
 */
function reservaComEquipamento(): array
{
    $user = User::factory()->create();
    $reserva = Reserva::factory()->for($user)->for(Espaco::factory()->for($user))->create(['data' => '2026-10-10']);
    $equipamento = Equipamento::factory()->for($user)->create();
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 1]);

    return ['user' => $user, 'reserva' => $reserva, 'equipamento' => $equipamento];
}

function pivoDe(Reserva $reserva, Equipamento $equipamento): object
{
    return DB::table('reserva_equipamento')
        ->where('reserva_id', $reserva->id)
        ->where('equipamento_id', $equipamento->id)
        ->sole();
}

it('redireciona o visitante para o login', function () {
    $dados = reservaComEquipamento();

    $this->patch(route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $dados['equipamento']]), ['acao' => 'entregue'])
        ->assertRedirect(route('login'));
});

it('marca como entregue e grava o horário da entrega', function () {
    $dados = reservaComEquipamento();

    $this->actingAs($dados['user'])
        ->patch(route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $dados['equipamento']]), ['acao' => 'entregue'])
        ->assertRedirect(route('agendamentos.equipamentos.index'))
        ->assertSessionHasNoErrors();

    $pivo = pivoDe($dados['reserva'], $dados['equipamento']);

    expect($pivo->entregue_em)->not->toBeNull()
        ->and($pivo->recolhido_em)->toBeNull();
});

it('marca como recolhido e grava o horário da devolução', function () {
    $dados = reservaComEquipamento();

    $this->actingAs($dados['user'])
        ->patch(route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $dados['equipamento']]), ['acao' => 'recolhido'])
        ->assertRedirect(route('agendamentos.equipamentos.index'));

    $pivo = pivoDe($dados['reserva'], $dados['equipamento']);

    expect($pivo->recolhido_em)->not->toBeNull()
        ->and($pivo->entregue_em)->toBeNull();
});

it('não sobrescreve a marcação anterior: quem marca de novo mantém o primeiro horário', function () {
    $dados = reservaComEquipamento();
    $rota = route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $dados['equipamento']]);

    $this->actingAs($dados['user'])->patch($rota, ['acao' => 'entregue'])->assertSessionHasNoErrors();
    $primeiraMarcacao = pivoDe($dados['reserva'], $dados['equipamento'])->entregue_em;

    $this->travelTo(Carbon::parse('2026-10-10 09:30', config('agendamentos.fuso')));
    $this->actingAs($dados['user'])->patch($rota, ['acao' => 'entregue'])->assertSessionHasNoErrors();

    expect(pivoDe($dados['reserva'], $dados['equipamento'])->entregue_em)->toBe($primeiraMarcacao);
});

it('recusa ação fora de entregue e recolhido, com 422', function (mixed $acao) {
    $dados = reservaComEquipamento();

    $this->actingAs($dados['user'])
        ->patchJson(route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $dados['equipamento']]), ['acao' => $acao])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['acao']);

    $pivo = pivoDe($dados['reserva'], $dados['equipamento']);

    expect($pivo->entregue_em)->toBeNull()
        ->and($pivo->recolhido_em)->toBeNull();
})->with([
    'ausente' => [null],
    'desconhecida' => ['devolvido'],
    'lista' => [['entregue']],
]);

it('responde 404 para reserva de outro dono, mesmo com ação inválida', function () {
    $dados = reservaComEquipamento();

    $this->actingAs(User::factory()->create())
        ->patch(route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $dados['equipamento']]), ['acao' => 'devolvido'])
        ->assertNotFound();

    expect(pivoDe($dados['reserva'], $dados['equipamento'])->entregue_em)->toBeNull();
});

it('responde 404 quando o equipamento não está vinculado à reserva', function () {
    $dados = reservaComEquipamento();
    $outro = Equipamento::factory()->for($dados['user'])->create();

    $this->actingAs($dados['user'])
        ->patch(route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $outro]), ['acao' => 'entregue'])
        ->assertNotFound();

    expect(pivoDe($dados['reserva'], $dados['equipamento'])->entregue_em)->toBeNull();
});

it('responde 404 quando o equipamento é de outro dono, mesmo vinculado à reserva', function () {
    $dados = reservaComEquipamento();
    $alheio = Equipamento::factory()->create();
    $dados['reserva']->equipamentos()->attach($alheio, ['quantidade' => 1]);

    $this->actingAs($dados['user'])
        ->patch(route('agendamentos.reservas.equipamentos.update', [$dados['reserva'], $alheio]), ['acao' => 'entregue'])
        ->assertNotFound();

    expect(pivoDe($dados['reserva'], $alheio)->entregue_em)->toBeNull();
});
