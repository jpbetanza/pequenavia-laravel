<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

dataset('recursos da agenda', [
    Grupo::class,
    Espaco::class,
    Equipamento::class,
    Reserva::class,
    Solicitacao::class,
]);

it('deixa o dono ver, editar e excluir o próprio registro', function (string $recurso) {
    $registro = $recurso::factory()->create();

    expect(Gate::forUser($registro->user)->allows('view', $registro))->toBeTrue()
        ->and(Gate::forUser($registro->user)->allows('update', $registro))->toBeTrue()
        ->and(Gate::forUser($registro->user)->allows('delete', $registro))->toBeTrue();
})->with('recursos da agenda');

it('responde 404 a quem não é dono, para não revelar que o registro existe', function (string $recurso) {
    $registro = $recurso::factory()->create();
    $estranho = User::factory()->create();

    $statuses = collect(['view', 'update', 'delete'])
        ->map(fn (string $acao) => Gate::forUser($estranho)->inspect($acao, $registro)->status())
        ->unique()
        ->values()
        ->all();

    expect($statuses)->toBe([404]);
})->with('recursos da agenda');
