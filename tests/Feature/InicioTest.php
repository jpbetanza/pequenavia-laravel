<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\GeracaoCertificado;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // 17/10/2026 15h30 em São Paulo = 18h30 UTC
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));
});

it('exige login', function () {
    $this->get(route('inicio'))->assertRedirect(route('login'));
});

it('mostra o dia no fuso de São Paulo com período e agora', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inicio')
            ->where('hoje.data', '2026-10-17')
            ->where('hoje.rotulo', 'sábado, 17 de outubro')
            ->where('hoje.agora', '15:30')
            ->where('hoje.periodo', 'tarde'));
});

it('às 23h30 de São Paulo ainda é o mesmo dia e é noite', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 23:30', 'America/Sao_Paulo'));

    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hoje.data', '2026-10-17')
            ->where('hoje.periodo', 'noite'));
});

it('lista só as reservas de hoje do dono, em ordem, com situação', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create(['nome' => 'Salão', 'cor' => 'bordo']);
    $projetor = Equipamento::factory()->for($user)->create(['nome' => 'Projetor', 'quantidade' => 2]);

    Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Coral', 'data' => '2026-10-17', 'inicio' => '19:30', 'fim' => '21:00']);
    $agora = Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Crisma', 'data' => '2026-10-17', 'inicio' => '15:00', 'fim' => '17:00']);
    $agora->equipamentos()->attach($projetor->id, ['quantidade' => 1]);
    Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Criança', 'data' => '2026-10-17', 'inicio' => '14:00', 'fim' => '15:30']);
    Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Ontem', 'data' => '2026-10-16', 'inicio' => '15:00', 'fim' => '16:00']);

    $outro = User::factory()->create();
    Reserva::factory()->for($outro)->for(Espaco::factory()->for($outro))->create(['titulo' => 'Alheia', 'data' => '2026-10-17']);

    $this->actingAs($user)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservas', 3)
            ->where('reservas.0.titulo', 'Criança')
            ->where('reservas.0.situacao', 'encerrada')
            ->where('reservas.1.titulo', 'Crisma')
            ->where('reservas.1.situacao', 'em_andamento')
            ->where('reservas.1.inicio', '15:00')
            ->where('reservas.1.espaco.nome', 'Salão')
            ->where('reservas.1.espaco.cor', 'bordo')
            ->where('reservas.1.equipamentos.0.nome', 'Projetor')
            ->where('reservas.1.equipamentos.0.quantidade', 1)
            ->where('reservas.2.titulo', 'Coral')
            ->where('reservas.2.situacao', 'proxima'));
});

it('traz o total de pedidos pendentes e só os 3 primeiros cartões', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create(['nome' => 'Capela', 'cor' => 'verde']);

    foreach (['2026-10-20', '2026-10-18', '2026-10-22', '2026-10-19'] as $data) {
        Solicitacao::factory()->for($user)->create(['espaco_id' => $espaco->id, 'data' => $data, 'inicio' => '19:00', 'fim' => '20:00']);
    }
    Solicitacao::factory()->for($user)->create(['status' => 'recusada']);
    Solicitacao::factory()->for(User::factory())->create();

    $this->actingAs($user)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('solicitacoes.total', 4)
            ->has('solicitacoes.primeiras', 3)
            ->where('solicitacoes.primeiras.0.data', '2026-10-18')
            ->where('solicitacoes.primeiras.0.espaco_nome', 'Capela')
            ->where('solicitacoes.primeiras.0.espaco_cor', 'verde')
            ->has('solicitacoes.primeiras.0.estado'));
});

it('conta os certificados do mês no fuso e traz o último', function () {
    $user = User::factory()->create();

    // 30/09 22h em SP = 01/10 01h UTC: é setembro em São Paulo, não entra
    GeracaoCertificado::factory()->for($user)->create(['modelo_title' => 'Setembro', 'created_at' => CarbonImmutable::parse('2026-09-30 22:00', 'America/Sao_Paulo')]);
    GeracaoCertificado::factory()->for($user)->create(['modelo_title' => 'Batismo', 'created_at' => CarbonImmutable::parse('2026-10-16 10:00', 'America/Sao_Paulo')]);
    GeracaoCertificado::factory()->for($user)->create(['modelo_title' => 'Crisma', 'created_at' => CarbonImmutable::parse('2026-10-02 10:00', 'America/Sao_Paulo')]);
    GeracaoCertificado::factory()->for(User::factory())->create(['created_at' => CarbonImmutable::parse('2026-10-10 10:00', 'America/Sao_Paulo')]);

    $this->actingAs($user)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificados.emitidos_no_mes', 2)
            ->where('certificados.ultimo.modelo', 'Batismo'));
});

it('não traz no painel de equipamentos as reservas de hoje de outro dono', function () {
    $outro = User::factory()->create();
    $reserva = Reserva::factory()->for($outro)->for(Espaco::factory()->for($outro))->create(['data' => '2026-10-17']);
    $reserva->equipamentos()->attach(Equipamento::factory()->for($outro)->create()->id, ['quantidade' => 1]);

    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('equipamentos_hoje.movimentos', 0));
});

it('sem certificado o último é nulo, e o painel de equipamentos vem no formato da tela de equipamentos', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificados.ultimo', null)
            ->where('equipamentos_hoje.rotulo', 'sábado, 17 de outubro')
            ->has('equipamentos_hoje.movimentos', 0));
});
