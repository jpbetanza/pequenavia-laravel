<?php

use App\Enums\PapelDaMensagem;
use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

test('conversa pertence ao usuário e guarda mensagens e ações', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Oi']);
    AcaoProposta::factory()->for($conversa)->create(['user_id' => $conversa->user_id]);

    expect($conversa->user->conversas()->count())->toBe(1)
        ->and($conversa->mensagens()->first()->papel)->toBe(PapelDaMensagem::Usuario)
        ->and($conversa->acoes()->first()->status)->toBe(StatusDaAcao::Proposta)
        ->and($conversa->mensagens()->first()->cartoes)->toBe([]);
});

test('excluir a conversa apaga mensagens e ações', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create();
    AcaoProposta::factory()->for($conversa)->create(['user_id' => $conversa->user_id]);

    $conversa->delete();

    expect(Mensagem::count())->toBe(0)->and(AcaoProposta::count())->toBe(0);
});

test('policy: dono passa e estranho recebe 404', function () {
    $conversa = Conversa::factory()->create();
    $estranho = User::factory()->create();

    expect($conversa->user->can('view', $conversa))->toBeTrue()
        ->and(Gate::forUser($estranho)->inspect('view', $conversa)->status())->toBe(404)
        ->and(Gate::forUser($estranho)->inspect('confirmar', AcaoProposta::factory()->for($conversa)->create(['user_id' => $conversa->user_id]))->status())->toBe(404);
});

test('a prop assistente.disponivel segue a chave configurada', function (?string $chave, bool $disponivel) {
    config(['assistente.chave' => $chave]);

    $this->actingAs(User::factory()->create())
        ->get(route('agendamentos.grupos.index'))
        ->assertInertia(fn (Assert $page) => $page->where('assistente.disponivel', $disponivel));
})->with([[null, false], ['', false], ['chave-de-teste', true]]);
