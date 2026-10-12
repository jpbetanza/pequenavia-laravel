<?php

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use App\Services\Assistente\Ferramentas\ProporReserva;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo')));

function cenario(): array
{
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    $projetor = Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Catequese infantil']);

    return [$usuario, Conversa::factory()->for($usuario)->create(), $salao, $projetor, $grupo];
}

test('propõe a reserva sem gravar e devolve o cartão de confirmação', function () {
    [$usuario, $conversa] = cenario();

    $resultado = app(ProporReserva::class)->executar($usuario, $conversa, [
        'titulo' => 'Catequese', 'espaco' => 'Salão paroquial', 'grupo' => 'catequese infantil',
        'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '17:00',
        'equipamentos' => [['equipamento' => 'Projetor', 'quantidade' => 1]],
    ]);

    expect(Reserva::count())->toBe(0)
        ->and(AcaoProposta::count())->toBe(1)
        ->and($resultado->cartao['tipo'])->toBe('acao_proposta')
        ->and($resultado->cartao['dados']['status'])->toBe('proposta')
        ->and($resultado->cartao['dados']['resumo'])->toMatchArray(['titulo' => 'Catequese', 'espaco_nome' => 'Salão paroquial', 'grupo_nome' => 'Catequese infantil', 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '17:00'])
        ->and($resultado->paraOModelo['acao_id'])->toBe(AcaoProposta::first()->id);
});

test('com conflito não cria ação e devolve os conflitos ao modelo', function () {
    [$usuario, $conversa, $salao] = cenario();
    Reserva::factory()->for($usuario)->create(['espaco_id' => $salao->id, 'data' => '2026-10-16', 'inicio' => '13:00', 'fim' => '15:00']);

    $resultado = app(ProporReserva::class)->executar($usuario, $conversa, ['titulo' => 'X', 'espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '17:00']);

    expect(AcaoProposta::count())->toBe(0)
        ->and($resultado->paraOModelo['conflitos'][0]['tipo'])->toBe('sala_ocupada')
        ->and($resultado->cartao)->toBeNull();
});

test('nova proposta expira a anterior ainda pendente na mesma conversa', function () {
    [$usuario, $conversa, $salao] = cenario();
    $argumentos = ['titulo' => 'X', 'espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '15:00'];

    app(ProporReserva::class)->executar($usuario, $conversa, $argumentos);
    $primeira = AcaoProposta::sole()->id;

    app(ProporReserva::class)->executar($usuario, $conversa, [...$argumentos, 'inicio' => '16:00', 'fim' => '17:00']);

    expect(AcaoProposta::find($primeira)->status)->toBe(StatusDaAcao::Expirada)
        ->and(AcaoProposta::whereKeyNot($primeira)->sole()->status)->toBe(StatusDaAcao::Proposta);
});

test('argumentos ruins viram ArgumentoInvalido', function (array $argumentos) {
    [$usuario, $conversa] = cenario();

    expect(fn () => app(ProporReserva::class)->executar($usuario, $conversa, $argumentos))->toThrow(ArgumentoInvalido::class);
})->with([
    [['titulo' => 'X', 'espaco' => 'Não existe', 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '15:00']],
    [['titulo' => 'X', 'espaco' => 'Salão paroquial', 'data' => 'sexta', 'inicio' => '14:00', 'fim' => '15:00']],
    [['titulo' => 'X', 'espaco' => 'Salão paroquial', 'data' => '2026-10-16', 'inicio' => '15:00', 'fim' => '14:00']],
    [['titulo' => 'X', 'espaco' => 'Salão paroquial', 'data' => '2026-10-01', 'inicio' => '14:00', 'fim' => '15:00']],
]);
