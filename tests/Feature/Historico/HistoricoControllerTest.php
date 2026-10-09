<?php

use App\Models\CampoCertificado;
use App\Models\GeracaoCertificado;
use App\Models\ModeloCertificado;
use App\Models\User;

it('redireciona o visitante para o login', function () {
    $this->get(route('historico.index'))->assertRedirect(route('login'));
});

it('mostra as 50 gerações mais recentes do próprio usuário, da mais nova para a mais antiga', function () {
    $user = User::factory()->create();
    $geracoes = GeracaoCertificado::factory()
        ->for($user)
        ->count(55)
        ->sequence(fn ($sequence) => ['created_at' => now()->subMinutes(55 - $sequence->index)])
        ->create();
    GeracaoCertificado::factory()->create();

    $this->actingAs($user)->get(route('historico.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('historico/index')
            ->has('geracoes', 50)
            ->where('geracoes.0.id', $geracoes->last()->id)
            ->where('geracoes.49.id', $geracoes[5]->id));
});

it('entrega de cada geração o modelo, os valores e a data', function () {
    $user = User::factory()->create();
    $modelo = ModeloCertificado::factory()->create(['title' => 'Crisma 2024']);
    CampoCertificado::factory()->for($modelo, 'modelo')->count(2)->sequence(
        ['nome' => 'Nome', 'created_at' => now()->subMinute()],
        ['nome' => 'Data', 'created_at' => now()],
    )->create();
    $geracao = GeracaoCertificado::factory()->for($user)->for($modelo, 'modelo')->create([
        'modelo_title' => 'Crisma 2024',
        'campos_valores' => ['Nome' => 'Maria'],
    ]);

    $this->actingAs($user)->get(route('historico.index'))
        ->assertInertia(fn ($page) => $page
            ->has('geracoes.0', fn ($item) => $item
                ->where('id', $geracao->id)
                ->where('modelo_id', $modelo->id)
                ->where('modelo_title', 'Crisma 2024')
                ->where('campos_valores', ['Nome' => 'Maria'])
                ->where('ordem_campos', ['Nome', 'Data'])
                ->has('created_at')));
});

it('devolve a ordem dos campos vazia quando o modelo da geração foi excluído', function () {
    $user = User::factory()->create();
    $modelo = ModeloCertificado::factory()->create();
    GeracaoCertificado::factory()->for($user)->for($modelo, 'modelo')->create(['campos_valores' => ['Nome' => 'Maria']]);
    $modelo->delete();

    $this->actingAs($user)->get(route('historico.index'))
        ->assertInertia(fn ($page) => $page
            ->where('geracoes.0.modelo_id', null)
            ->where('geracoes.0.ordem_campos', []));
});
