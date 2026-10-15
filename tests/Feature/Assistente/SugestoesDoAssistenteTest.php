<?php

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-15 14:00', 'America/Sao_Paulo'));
    $this->usuario = User::factory()->create([
        'name' => 'Ana Souza',
        'primeiros_passos' => ['dispensado_em' => now()->toIso8601String()],
    ]);
});

it('no primeiro acesso, as sugestões levam à configuração', function () {
    $novo = User::factory()->create(['name' => 'Ana Souza']);

    $this->actingAs($novo)->getJson(route('assistente.sugestoes'))
        ->assertOk()
        ->assertJsonPath('situacao', 'primeiro')
        ->assertJsonPath('saudacao', 'Boas-vindas, Ana.')
        ->assertJsonPath('rotulo', 'Para configurar')
        ->assertJsonPath('sugestoes.0.texto', 'Continuar a configuração')
        ->assertJsonPath('sugestoes.0.destaque', true)
        ->assertJsonCount(4, 'sugestoes');
});

it('com pedidos e equipamento fora, as pendências vêm primeiro, com os números reais', function () {
    Solicitacao::factory()->for($this->usuario)->count(2)->create();
    Solicitacao::factory()->for($this->usuario)->create(['status' => StatusSolicitacao::Aprovada]);
    $projetor = Equipamento::factory()->for($this->usuario)->create(['nome' => 'Projetor']);
    $ontem = Reserva::factory()->for($this->usuario)->create(['data' => '2026-10-14']);
    $ontem->equipamentos()->attach($projetor->id, ['quantidade' => 1, 'entregue_em' => now()->subDay()]);

    $this->actingAs($this->usuario)->getJson(route('assistente.sugestoes'))
        ->assertOk()
        ->assertJsonPath('situacao', 'pendencias')
        ->assertJsonPath('saudacao', 'Boa tarde, Ana.')
        ->assertJsonPath('abertura', '2 pedidos esperam resposta e o equipamento Projetor ainda não voltou. Posso cuidar disso com você.')
        ->assertJsonPath('sugestoes.0.texto', 'Responder os 2 pedidos')
        ->assertJsonPath('sugestoes.0.destaque', true)
        ->assertJsonPath('sugestoes.1.texto', 'Projetor a devolver')
        ->assertJsonPath('sugestoes.1.destaque', false)
        ->assertJsonPath('sugestoes.2.texto', 'O que tem amanhã?')
        ->assertJsonCount(4, 'sugestoes');
});

it('num dia tranquilo, a agenda e uma ação; o que já voltou não conta', function () {
    $projetor = Equipamento::factory()->for($this->usuario)->create();
    $hoje = Reserva::factory()->for($this->usuario)->create(['data' => '2026-10-15', 'inicio' => '09:00', 'fim' => '18:00']);
    $hoje->equipamentos()->attach($projetor->id, ['quantidade' => 1, 'entregue_em' => now(), 'recolhido_em' => now()]);
    Solicitacao::factory()->create();

    $this->actingAs($this->usuario)->getJson(route('assistente.sugestoes'))
        ->assertOk()
        ->assertJsonPath('situacao', 'tranquilo')
        ->assertJsonPath('rotulo', 'Para começar')
        ->assertJsonPath('sugestoes.0.texto', 'O que tem hoje?')
        ->assertJsonPath('sugestoes.0.destaque', false)
        ->assertJsonPath('sugestoes.1.texto', 'Reservar um espaço')
        ->assertJsonCount(4, 'sugestoes');
});

it('visitante não vê sugestões', function () {
    $this->getJson(route('assistente.sugestoes'))->assertUnauthorized();
});
