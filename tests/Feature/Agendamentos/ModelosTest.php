<?php

use App\Enums\CorDoEspaco;
use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\EspacoHorario;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('liga o usuário às suas agendas e só às dele', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create();
    $equipamento = Equipamento::factory()->for($user)->create();
    $grupo = Grupo::factory()->for($user)->create();
    $reserva = Reserva::factory()->for($user)->for($espaco, 'espaco')->create();
    $solicitacao = Solicitacao::factory()->for($user)->create();
    Espaco::factory()->create();

    expect($user->espacos->pluck('id')->all())->toBe([$espaco->id])
        ->and($user->equipamentos->pluck('id')->all())->toBe([$equipamento->id])
        ->and($user->grupos->pluck('id')->all())->toBe([$grupo->id])
        ->and($user->reservas->pluck('id')->all())->toBe([$reserva->id])
        ->and($user->solicitacoes->pluck('id')->all())->toBe([$solicitacao->id]);
});

it('liga o espaço aos horários, aos bloqueios e às reservas', function () {
    $espaco = Espaco::factory()->comHorarioComercial()->create();
    $bloqueio = EspacoBloqueio::factory()->for($espaco, 'espaco')->create();
    $reserva = Reserva::factory()->for($espaco, 'espaco')->create();

    expect($espaco->horarios)->toHaveCount(6)
        ->and($espaco->horarios->first())->toBeInstanceOf(EspacoHorario::class)
        ->and($espaco->bloqueios->pluck('id')->all())->toBe([$bloqueio->id])
        ->and($espaco->reservas->pluck('id')->all())->toBe([$reserva->id])
        ->and($bloqueio->espaco->is($espaco))->toBeTrue();
});

it('guarda os equipamentos da reserva e da solicitação com quantidade no pivô', function () {
    $reserva = Reserva::factory()->create();
    $equipamento = Equipamento::factory()->create(['user_id' => $reserva->user_id]);
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 2, 'entregue_em' => now()]);

    $solicitacao = Solicitacao::factory()->create(['user_id' => $reserva->user_id]);
    $solicitacao->equipamentos()->attach($equipamento, ['quantidade' => 3]);

    $reservaEquipamento = $reserva->fresh()->equipamentos->first()->pivot;
    $solicitacaoEquipamento = $solicitacao->fresh()->equipamentos->first()->pivot;

    expect($reservaEquipamento->quantidade)->toBe(2)
        ->and($reservaEquipamento->entregue_em)->not->toBeNull()
        ->and($reservaEquipamento->recolhido_em)->toBeNull()
        ->and($solicitacaoEquipamento->quantidade)->toBe(3)
        ->and($equipamento->reservas)->toHaveCount(1)
        ->and($equipamento->solicitacoes)->toHaveCount(1);
});

it('liga a reserva ao grupo e a solicitação ao espaço, ao grupo e à reserva criada', function () {
    $user = User::factory()->create();
    $grupo = Grupo::factory()->for($user)->create();
    $espaco = Espaco::factory()->for($user)->create();
    $reserva = Reserva::factory()->for($user)->for($espaco, 'espaco')->for($grupo, 'grupo')->create();
    $solicitacao = Solicitacao::factory()->for($user)->for($grupo, 'grupo')->create([
        'espaco_id' => $espaco->id,
        'reserva_id' => $reserva->id,
    ]);
    $semEspaco = Solicitacao::factory()->for($user)->create(['espaco_id' => null]);

    expect($reserva->grupo->is($grupo))->toBeTrue()
        ->and($reserva->espaco->is($espaco))->toBeTrue()
        ->and($grupo->reservas->pluck('id')->all())->toBe([$reserva->id])
        ->and($solicitacao->espaco->is($espaco))->toBeTrue()
        ->and($solicitacao->grupo->is($grupo))->toBeTrue()
        ->and($solicitacao->reserva->is($reserva))->toBeTrue()
        ->and($semEspaco->espaco)->toBeNull();
});

it('converte cor, recursos, datas e status para os tipos certos', function () {
    $espaco = Espaco::factory()->create(['cor' => CorDoEspaco::Verde, 'recursos_fixos' => ['Piano', 'Projetor']]);
    $espacoLido = Espaco::query()->findOrFail($espaco->id);
    $solicitacao = Solicitacao::factory()->create(['data' => '2026-11-14', 'status' => StatusSolicitacao::Aprovada]);
    $solicitacaoLida = Solicitacao::query()->findOrFail($solicitacao->id);

    expect($espacoLido->cor)->toBe(CorDoEspaco::Verde)
        ->and($espacoLido->recursos_fixos)->toBe(['Piano', 'Projetor'])
        ->and($espacoLido->aceita_solicitacoes)->toBeTrue()
        ->and($espacoLido->ativo)->toBeTrue()
        ->and($solicitacaoLida->status)->toBe(StatusSolicitacao::Aprovada)
        ->and($solicitacaoLida->data)->toBeInstanceOf(CarbonInterface::class)
        ->and($solicitacaoLida->data->toDateString())->toBe('2026-11-14');
});

it('usa os padrões do banco em espaço quando nada é informado', function () {
    $dono = User::factory()->create();
    DB::table('espacos')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $dono->id,
        'nome' => 'Salão',
        'cor' => 'azul',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $espacoLido = Espaco::query()->sole();

    expect($espacoLido->recursos_fixos)->toBe([])
        ->and($espacoLido->aceita_solicitacoes)->toBeTrue()
        ->and($espacoLido->ativo)->toBeTrue()
        ->and($espacoLido->antecedencia_min_horas)->toBe(0)
        ->and($espacoLido->intervalo_min)->toBe(0)
        ->and($espacoLido->duracao_max_min)->toBeNull();
});

it('aceita o mesmo nome em donos diferentes', function (string $modelo) {
    $primeiro = User::factory()->create();
    $segundo = User::factory()->create();

    $modelo::factory()->for($primeiro)->create(['nome' => 'Salão Paroquial']);
    $outro = $modelo::factory()->for($segundo)->create(['nome' => 'Salão Paroquial']);

    expect($outro->exists)->toBeTrue();
})->with([Grupo::class, Espaco::class, Equipamento::class]);

it('recusa o mesmo nome no mesmo dono', function (string $modelo) {
    $dono = User::factory()->create();
    $modelo::factory()->for($dono)->create(['nome' => 'Salão Paroquial']);

    expect(fn () => $modelo::factory()->for($dono)->create(['nome' => 'Salão Paroquial']))
        ->toThrow(QueryException::class);
})->with([Grupo::class, Espaco::class, Equipamento::class]);

it('recusa dois horários no mesmo dia da semana para o mesmo espaço', function () {
    $espaco = Espaco::factory()->comHorarioComercial()->create();

    expect(fn () => EspacoHorario::factory()->for($espaco, 'espaco')->create(['dia_semana' => 1]))
        ->toThrow(QueryException::class);
});

it('apaga horários e bloqueios junto com o espaço', function () {
    $espaco = Espaco::factory()->comHorarioComercial()->create();
    EspacoBloqueio::factory()->for($espaco, 'espaco')->create();

    $espaco->delete();

    expect(EspacoHorario::query()->count())->toBe(0)
        ->and(EspacoBloqueio::query()->count())->toBe(0);
});

it('apaga as agendas do usuário junto com ele', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create();
    Equipamento::factory()->for($user)->create();
    Grupo::factory()->for($user)->create();
    Reserva::factory()->for($user)->for($espaco, 'espaco')->create();
    Solicitacao::factory()->for($user)->create();

    $user->delete();

    expect(Espaco::query()->count())->toBe(0)
        ->and(Equipamento::query()->count())->toBe(0)
        ->and(Grupo::query()->count())->toBe(0)
        ->and(Reserva::query()->count())->toBe(0)
        ->and(Solicitacao::query()->count())->toBe(0);
});

it('mantém a reserva quando o grupo é apagado, sem grupo', function () {
    $grupo = Grupo::factory()->create();
    $reserva = Reserva::factory()->for($grupo->user, 'user')->create(['grupo_id' => $grupo->id]);
    $solicitacao = Solicitacao::factory()->for($grupo->user, 'user')->create(['grupo_id' => $grupo->id]);

    $grupo->delete();

    expect($reserva->fresh())->not->toBeNull()
        ->and($reserva->fresh()->grupo_id)->toBeNull()
        ->and($solicitacao->fresh()->grupo_id)->toBeNull();
});

it('apaga os vínculos de equipamento quando a reserva ou a solicitação é apagada', function () {
    $reserva = Reserva::factory()->create();
    $solicitacao = Solicitacao::factory()->create(['user_id' => $reserva->user_id]);
    $equipamento = Equipamento::factory()->create(['user_id' => $reserva->user_id]);
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 1]);
    $solicitacao->equipamentos()->attach($equipamento, ['quantidade' => 1]);

    $reserva->delete();
    $solicitacao->delete();

    expect(DB::table('reserva_equipamento')->count())->toBe(0)
        ->and(DB::table('solicitacao_equipamento')->count())->toBe(0)
        ->and(Equipamento::query()->count())->toBe(1);
});
