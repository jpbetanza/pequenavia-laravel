<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('grava o primeiro acesso de quem entra no app', function () {
    $this->travelTo('2026-10-27 12:00:00');
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->get(route('inicio'))->assertOk();

    expect($usuario->fresh()->ultimo_acesso_em->toDateTimeString())->toBe('2026-10-27 12:00:00');
});

it('não grava de novo dentro da mesma hora', function () {
    $this->travelTo('2026-10-27 12:00:00');
    $usuario = User::factory()->create();
    $this->actingAs($usuario)->get(route('inicio'));

    $this->travelTo('2026-10-27 12:59:00');
    $this->actingAs($usuario->fresh())->get(route('inicio'));

    expect($usuario->fresh()->ultimo_acesso_em->toDateTimeString())->toBe('2026-10-27 12:00:00');
});

it('atualiza depois de uma hora, sem mexer no updated_at', function () {
    $this->travelTo('2026-10-27 12:00:00');
    $usuario = User::factory()->create();
    $this->actingAs($usuario)->get(route('inicio'));
    $atualizadoEm = $usuario->fresh()->updated_at->toDateTimeString();

    $this->travelTo('2026-10-27 13:01:00');
    $this->actingAs($usuario->fresh())->get(route('inicio'));

    $usuario = $usuario->fresh();
    expect($usuario->ultimo_acesso_em->toDateTimeString())->toBe('2026-10-27 13:01:00')
        ->and($usuario->updated_at->toDateTimeString())->toBe($atualizadoEm);
});

it('não conta a consulta automática da versão do site', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->getJson(route('versao'))->assertOk();

    expect($usuario->fresh()->ultimo_acesso_em)->toBeNull();
});

it('visitante sem login não grava nada', function () {
    $this->get(route('login'))->assertOk();

    expect(DB::table('users')->whereNotNull('ultimo_acesso_em')->count())->toBe(0);
});
