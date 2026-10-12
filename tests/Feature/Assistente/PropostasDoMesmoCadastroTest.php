<?php

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ProporGrupo;
use App\Services\Assistente\Ferramentas\ProporReserva;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo')));

test('propostas de cadastros diferentes na mesma conversa ficam pendentes juntas', function () {
    $usuario = User::factory()->create();
    Grupo::factory()->for($usuario)->create(['nome' => 'Catequese']);
    Grupo::factory()->for($usuario)->create(['nome' => 'Coral']);
    $conversa = Conversa::factory()->for($usuario)->create();

    $resultado = app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);
    app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Coral', 'coordenacao' => 'Caio']);

    expect(AcaoProposta::where('status', StatusDaAcao::Proposta)->count())->toBe(2)
        ->and($resultado->paraOModelo)->not->toHaveKey('substituiu');
});

test('nova proposta do mesmo cadastro expira a anterior e informa o título que saiu', function () {
    $usuario = User::factory()->create();
    Grupo::factory()->for($usuario)->create(['nome' => 'Catequese']);
    $conversa = Conversa::factory()->for($usuario)->create();

    app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);
    $primeira = AcaoProposta::sole()->id;

    $resultado = app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'contato' => '11 9999']);

    expect(AcaoProposta::find($primeira)->status)->toBe(StatusDaAcao::Expirada)
        ->and(AcaoProposta::whereKeyNot($primeira)->sole()->status)->toBe(StatusDaAcao::Proposta)
        ->and($resultado->paraOModelo['substituiu'])->toBe(['Alterar grupo: Catequese']);
});

test('criar com o mesmo nome expira a proposta anterior, e nomes diferentes ficam juntos', function () {
    $usuario = User::factory()->create();
    $conversa = Conversa::factory()->for($usuario)->create();
    $proporGrupo = app(ProporGrupo::class);

    $proporGrupo->executar($usuario, $conversa, ['operacao' => 'criar', 'nome' => 'Coral', 'coordenacao' => 'Ana']);
    $primeira = AcaoProposta::sole()->id;
    $proporGrupo->executar($usuario, $conversa, ['operacao' => 'criar', 'nome' => 'Canto', 'coordenacao' => 'Bia']);
    $proporGrupo->executar($usuario, $conversa, ['operacao' => 'criar', 'nome' => ' coral ', 'coordenacao' => 'Caio']);

    expect(AcaoProposta::find($primeira)->status)->toBe(StatusDaAcao::Expirada)
        ->and(AcaoProposta::where('status', StatusDaAcao::Proposta)->count())->toBe(2);
});

test('reserva e cadastro na mesma conversa ficam pendentes juntos, nos dois sentidos', function () {
    $usuario = User::factory()->create();
    Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    Grupo::factory()->for($usuario)->create(['nome' => 'Catequese']);
    $conversa = Conversa::factory()->for($usuario)->create();

    app(ProporReserva::class)->executar($usuario, $conversa, ['titulo' => 'Festa', 'espaco' => 'Salão paroquial', 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '15:00']);
    app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);
    app(ProporReserva::class)->executar($usuario, $conversa, ['titulo' => 'Festa 2', 'espaco' => 'Salão paroquial', 'data' => '2026-10-17', 'inicio' => '14:00', 'fim' => '15:00']);

    expect(AcaoProposta::where('status', StatusDaAcao::Proposta)->pluck('tipo')->sort()->values()->all())
        ->toBe(['alterar_grupo', 'criar_reserva']);
});

test('o cartão traz a chave da proposta: reserva, cadastro novo e cadastro existente', function () {
    $usuario = User::factory()->create();
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Catequese']);
    Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $conversa = Conversa::factory()->for($usuario)->create();

    $criar = app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'criar', 'nome' => 'Coral Jovem']);
    $alterar = app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Bia']);
    $reserva = app(ProporReserva::class)->executar($usuario, $conversa, ['titulo' => 'Festa', 'espaco' => 'Salão paroquial', 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '15:00']);

    expect($criar->cartao['dados']['chave'])->toBe('grupo:novo:coral jovem')
        ->and($alterar->cartao['dados']['chave'])->toBe("grupo:{$grupo->id}")
        ->and($reserva->cartao['dados']['chave'])->toBe('reserva');
});
