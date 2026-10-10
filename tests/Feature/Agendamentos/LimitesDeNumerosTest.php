<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\Carbon;

/*
 * Colunas de quantidade e de minutos são inteiros de 32 bits no banco. Um número acima do limite da regra
 * precisa responder 422 com a mensagem da validação, e não estourar em 500 no insert.
 */

beforeEach(function () {
    // Hoje é sábado, 10/10/2026, 10:00 no fuso da agenda. A segunda de referência é 12/10/2026.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));

    $this->dono = User::factory()->create(['name' => 'Paróquia São José']);
    $this->grupo = Grupo::factory()->for($this->dono)->create(['nome' => 'Catequese']);
    $this->salao = Espaco::factory()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Salão paroquial', 'capacidade' => 80]);
    $this->projetor = Equipamento::factory()->for($this->dono)->create(['nome' => 'Projetor', 'quantidade' => 2]);
});

/**
 * @param  array<string, mixed>  $sobrescritos
 * @return array<string, mixed>
 */
function corpoDeReservaParaLimite(Espaco $espaco, array $sobrescritos = []): array
{
    return array_merge([
        'titulo' => 'Ensaio do coral',
        'grupo_id' => null,
        'pessoas' => 20,
        'espaco_id' => $espaco->id,
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
        'observacoes' => null,
        'equipamentos' => [],
        'repetir' => 'nao',
    ], $sobrescritos);
}

/**
 * @param  array<string, mixed>  $sobrescritos
 * @return array<string, mixed>
 */
function corpoDoEspacoParaLimite(array $sobrescritos = []): array
{
    return array_merge([
        'nome' => 'Sala nova',
        'capacidade' => 120,
        'localizacao' => 'Térreo, ao lado da secretaria',
        'cor' => 'roxo',
        'recursos_fixos' => ['Telão'],
        'aceita_solicitacoes' => true,
        'antecedencia_min_horas' => 24,
        'duracao_max_min' => 240,
        'intervalo_min' => 30,
        'ativo' => true,
        'horarios' => [['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '22:00']],
        'bloqueios' => [],
    ], $sobrescritos);
}

/**
 * @param  array<string, mixed>  $sobrescritos
 * @return array<string, mixed>
 */
function corpoDoEquipamentoParaLimite(array $sobrescritos = []): array
{
    return array_merge([
        'nome' => 'Caixa de som',
        'descricao' => null,
        'quantidade' => 2,
        'local_guarda' => null,
        'manutencao_ate' => null,
    ], $sobrescritos);
}

it('StoreReservaRequest recusa pessoas acima de 2000 com 422', function (int $pessoas) {
    $this->actingAs($this->dono)
        ->postJson(route('agendamentos.reservas.store'), corpoDeReservaParaLimite($this->salao, ['pessoas' => $pessoas]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pessoas' => 'O número de pessoas pode ser no máximo 2000.']);

    expect(Reserva::query()->count())->toBe(0);
})->with([2001, 99999999999]);

it('StoreReservaRequest recusa quantidade de equipamento acima de 1000 com 422', function (int $quantidade) {
    $this->actingAs($this->dono)
        ->postJson(route('agendamentos.reservas.store'), corpoDeReservaParaLimite($this->salao, [
            'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => $quantidade]],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['equipamentos.0.quantidade' => 'A quantidade pode ser no máximo 1000.']);

    expect(Reserva::query()->count())->toBe(0);
})->with([1001, 99999999999]);

it('UpdateReservaRequest recusa pessoas acima de 2000 com 422 e não altera a reserva', function (int $pessoas) {
    $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
        'titulo' => 'Ensaio do coral',
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
    ]);

    $this->actingAs($this->dono)
        ->putJson(route('agendamentos.reservas.update', $reserva), corpoDeReservaParaLimite($this->salao, [
            'titulo' => 'Ensaio alterado',
            'pessoas' => $pessoas,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pessoas' => 'O número de pessoas pode ser no máximo 2000.']);

    expect($reserva->fresh()->titulo)->toBe('Ensaio do coral');
})->with([2001, 99999999999]);

it('UpdateReservaRequest recusa quantidade de equipamento acima de 1000 com 422', function (int $quantidade) {
    $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
    ]);

    $this->actingAs($this->dono)
        ->putJson(route('agendamentos.reservas.update', $reserva), corpoDeReservaParaLimite($this->salao, [
            'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => $quantidade]],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['equipamentos.0.quantidade' => 'A quantidade pode ser no máximo 1000.']);
})->with([1001, 99999999999]);

it('UpdateSolicitacaoRequest recusa pessoas acima de 2000 com 422', function (int $pessoas) {
    $solicitacao = Solicitacao::factory()->pendente()->for($this->dono)->for($this->salao, 'espaco')->create([
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
    ]);

    $this->actingAs($this->dono)
        ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
            'espaco_id' => $this->salao->id,
            'data' => '2026-10-12',
            'inicio' => '19:00',
            'fim' => '21:00',
            'pessoas' => $pessoas,
            'equipamentos' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pessoas' => 'O número de pessoas pode ser no máximo 2000.']);
})->with([2001, 99999999999]);

it('UpdateSolicitacaoRequest recusa quantidade de equipamento acima de 1000 com 422', function (int $quantidade) {
    $solicitacao = Solicitacao::factory()->pendente()->for($this->dono)->for($this->salao, 'espaco')->create([
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
    ]);

    $this->actingAs($this->dono)
        ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
            'espaco_id' => $this->salao->id,
            'data' => '2026-10-12',
            'inicio' => '19:00',
            'fim' => '21:00',
            'pessoas' => 10,
            'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => $quantidade]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['equipamentos.0.quantidade' => 'A quantidade pode ser no máximo 1000.']);
})->with([1001, 99999999999]);

it('StoreSolicitacaoPublicaRequest responde 422, e não 500, para pessoas acima de 2000 no endpoint público', function (int $pessoas) {
    $this->postJson(route('reservar.store', $this->dono->agendaSlug()), [
        'grupo_id' => $this->grupo->id,
        'nome' => 'Maria Silva',
        'telefone' => '(11) 98765-4321',
        'titulo' => 'Ensaio do coral',
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
        'espaco_id' => $this->salao->id,
        'pessoas' => $pessoas,
        'equipamentos' => [],
        'observacoes' => null,
        'site' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pessoas' => 'O número de pessoas pode ser no máximo 2000.']);

    expect(Solicitacao::query()->count())->toBe(0);
})->with([2001, 99999999999]);

it('StoreEquipamentoRequest recusa quantidade acima de 1000 com 422', function (int $quantidade) {
    $this->actingAs($this->dono)
        ->postJson(route('agendamentos.equipamentos.store'), corpoDoEquipamentoParaLimite(['quantidade' => $quantidade]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantidade' => 'A quantidade pode ser no máximo 1000.']);

    expect(Equipamento::query()->where('nome', 'Caixa de som')->exists())->toBeFalse();
})->with([1001, 99999999999]);

it('UpdateEquipamentoRequest recusa quantidade acima de 1000 com 422 e não altera o equipamento', function (int $quantidade) {
    $this->actingAs($this->dono)
        ->putJson(route('agendamentos.equipamentos.update', $this->projetor), corpoDoEquipamentoParaLimite([
            'nome' => 'Projetor',
            'quantidade' => $quantidade,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantidade' => 'A quantidade pode ser no máximo 1000.']);

    expect($this->projetor->fresh()->quantidade)->toBe(2);
})->with([1001, 99999999999]);

it('StoreEspacoRequest recusa minutos e horas acima de 10080 com 422', function (string $campo, int $valor, string $mensagem) {
    $this->actingAs($this->dono)
        ->postJson(route('agendamentos.espacos.store'), corpoDoEspacoParaLimite([$campo => $valor]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo => $mensagem]);

    expect(Espaco::query()->where('user_id', $this->dono->id)->where('nome', 'Sala nova')->exists())->toBeFalse();
})->with([
    'antecedência' => ['antecedencia_min_horas', 10081, 'A antecedência pode ser no máximo 10080 horas.'],
    'duração máxima' => ['duracao_max_min', 10081, 'A duração máxima pode ser no máximo 10080 minutos.'],
    'intervalo' => ['intervalo_min', 10081, 'O intervalo pode ser no máximo 10080 minutos.'],
    'antecedência enorme' => ['antecedencia_min_horas', 99999999999, 'A antecedência pode ser no máximo 10080 horas.'],
    'duração enorme' => ['duracao_max_min', 99999999999, 'A duração máxima pode ser no máximo 10080 minutos.'],
    'intervalo enorme' => ['intervalo_min', 99999999999, 'O intervalo pode ser no máximo 10080 minutos.'],
]);

it('UpdateEspacoRequest recusa minutos e horas acima de 10080 com 422', function (string $campo, int $valor, string $mensagem) {
    $this->actingAs($this->dono)
        ->putJson(route('agendamentos.espacos.update', $this->salao), corpoDoEspacoParaLimite([$campo => $valor]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo => $mensagem]);

    expect($this->salao->fresh()->{$campo})->not->toBe($valor);
})->with([
    'antecedência' => ['antecedencia_min_horas', 10081, 'A antecedência pode ser no máximo 10080 horas.'],
    'duração máxima' => ['duracao_max_min', 10081, 'A duração máxima pode ser no máximo 10080 minutos.'],
    'intervalo' => ['intervalo_min', 10081, 'O intervalo pode ser no máximo 10080 minutos.'],
    'antecedência enorme' => ['antecedencia_min_horas', 99999999999, 'A antecedência pode ser no máximo 10080 horas.'],
    'duração enorme' => ['duracao_max_min', 99999999999, 'A duração máxima pode ser no máximo 10080 minutos.'],
    'intervalo enorme' => ['intervalo_min', 99999999999, 'O intervalo pode ser no máximo 10080 minutos.'],
]);
