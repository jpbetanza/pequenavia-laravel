<?php

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ProporReserva;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));
});

function proposta(): array
{
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    $projetor = Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $conversa = Conversa::factory()->for($usuario)->create();
    app(ProporReserva::class)->executar($usuario, $conversa, ['titulo' => 'Catequese', 'espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '17:00', 'equipamentos' => [['equipamento' => $projetor->id, 'quantidade' => 1]]]);

    return [$usuario, AcaoProposta::firstOrFail(), $salao];
}

test('confirmar cria a reserva com equipamentos e marca a ação', function () {
    [$usuario, $acao] = proposta();

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))
        ->assertOk()
        ->assertJsonPath('acao.status', 'confirmada')
        ->assertJsonPath('acao.resultado.data', '2026-10-16');

    $reserva = Reserva::firstOrFail();
    expect($reserva->titulo)->toBe('Catequese')
        ->and($reserva->equipamentos()->count())->toBe(1)
        ->and($acao->fresh()->status)->toBe(StatusDaAcao::Confirmada)
        ->and($acao->fresh()->resultado_id)->toBe($reserva->id);
});

test('confirmar de novo responde 422 e não duplica', function () {
    [$usuario, $acao] = proposta();
    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))->assertOk();

    $this->postJson(route('assistente.acoes.confirmar', $acao))->assertUnprocessable()->assertJsonPath('message', 'Esta ação não está mais pendente.');
    expect(Reserva::count())->toBe(1);
});

test('ação que já não está pendente responde 422 com o cartão atual', function (string $rota) {
    [$usuario, $acao] = proposta();
    $acao->update(['status' => StatusDaAcao::Expirada]);

    $this->actingAs($usuario)->postJson(route($rota, $acao))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta ação não está mais pendente.')
        ->assertJsonPath('acao.id', $acao->id)
        ->assertJsonPath('acao.status', 'expirada');
})->with(['assistente.acoes.confirmar', 'assistente.acoes.descartar']);

test('conflito surgido depois da proposta responde 422 e nada é gravado', function () {
    [$usuario, $acao, $salao] = proposta();
    Reserva::factory()->for($usuario)->create(['espaco_id' => $salao->id, 'data' => '2026-10-16', 'inicio' => '15:00', 'fim' => '16:00']);

    $this->actingAs($usuario)->postJson(route('assistente.acoes.confirmar', $acao))->assertUnprocessable()->assertJsonStructure(['message', 'conflitos']);
    expect(Reserva::count())->toBe(1)->and($acao->fresh()->status)->toBe(StatusDaAcao::Proposta);
});

test('descartar marca a ação', function () {
    [$usuario, $acao] = proposta();

    $this->actingAs($usuario)->postJson(route('assistente.acoes.descartar', $acao))->assertOk()->assertJsonPath('acao.status', 'descartada');
});

test('ação de outro usuário responde 404', function (string $rota) {
    [, $acao] = proposta();

    $this->actingAs(User::factory()->create())->postJson(route($rota, $acao))->assertNotFound();
})->with(['assistente.acoes.confirmar', 'assistente.acoes.descartar']);
