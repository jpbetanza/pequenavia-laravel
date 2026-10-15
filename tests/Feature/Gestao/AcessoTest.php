<?php

use App\Models\User;

it('manda o visitante para o login em todas as telas de gestão', function () {
    $cliente = User::factory()->create(['admin' => false]);

    foreach ([
        route('gestao.index'),
        route('gestao.conversas.index'),
        route('gestao.clientes.index'),
        route('gestao.clientes.show', $cliente),
        route('gestao.temas.index'),
    ] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});

it('responde 403 a quem não é gestor em todas as telas de gestão', function () {
    $secretaria = User::factory()->create(['admin' => false]);
    $cliente = User::factory()->create(['admin' => false]);

    $this->actingAs($secretaria);

    foreach ([
        route('gestao.index'),
        route('gestao.conversas.index'),
        route('gestao.clientes.index'),
        route('gestao.clientes.show', $cliente),
        route('gestao.temas.index'),
    ] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('deixa o gestor abrir a visão geral, a lista de clientes e o detalhe de um cliente', function () {
    $gestor = User::factory()->create(['admin' => true]);
    $cliente = User::factory()->create(['admin' => false]);

    $this->actingAs($gestor);

    $this->get(route('gestao.index'))->assertOk();
    $this->get(route('gestao.clientes.index'))->assertOk();
    $this->get(route('gestao.clientes.show', $cliente))->assertOk();
});

it('não mostra o detalhe de um gestor como se fosse cliente', function () {
    $gestor = User::factory()->create(['admin' => true]);
    $outroGestor = User::factory()->create(['admin' => true]);

    $this->actingAs($gestor)
        ->get(route('gestao.clientes.show', $outroGestor))
        ->assertNotFound();
});
