<?php

use App\Enums\StatusSolicitacao;
use App\Jobs\RemoverImagemModelo;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('exclui a conta mesmo com espaço, equipamento, reserva e solicitação aprovada ligados entre si', function () {
    Queue::fake([RemoverImagemModelo::class]);

    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create();
    $equipamento = Equipamento::factory()->for($user)->create();
    $grupo = Grupo::factory()->for($user)->create();
    $reserva = Reserva::factory()->for($user)->for($espaco, 'espaco')->for($grupo, 'grupo')->create();
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 1]);
    Solicitacao::factory()->for($user)->create([
        'status' => StatusSolicitacao::Aprovada,
        'espaco_id' => $espaco->id,
        'reserva_id' => $reserva->id,
    ]);
    $solicitacao = Solicitacao::query()->where('reserva_id', $reserva->id)->sole();
    $solicitacao->equipamentos()->attach($equipamento, ['quantidade' => 1]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    // As FKs de reservas são DEFERRABLE: esta linha força a checagem agora, para não ficar nada pendurado.
    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

    $this->assertModelMissing($user);
    expect(Espaco::query()->count())->toBe(0)
        ->and(Equipamento::query()->count())->toBe(0)
        ->and(Grupo::query()->count())->toBe(0)
        ->and(Reserva::query()->count())->toBe(0)
        ->and(Solicitacao::query()->count())->toBe(0)
        ->and(DB::table('reserva_equipamento')->count())->toBe(0)
        ->and(DB::table('solicitacao_equipamento')->count())->toBe(0);
});

it('bloqueia excluir espaço ou equipamento direto enquanto houver reserva', function () {
    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    $reserva = Reserva::factory()->create();
    $equipamento = Equipamento::factory()->for($reserva->user)->create();
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 1]);

    expect(fn () => DB::transaction(fn () => $reserva->espaco->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $equipamento->delete()))->toThrow(QueryException::class);
    expect(Reserva::query()->count())->toBe(1);
});
