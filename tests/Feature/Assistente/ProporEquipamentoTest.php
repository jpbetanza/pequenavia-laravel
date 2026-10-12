<?php

use App\Models\AcaoProposta;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use App\Services\Assistente\Ferramentas\ProporEquipamento;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));
});

test('criar exige a quantidade', function () {
    $usuario = User::factory()->create();

    expect(fn () => app(ProporEquipamento::class)->executar($usuario, conversaDe($usuario), ['operacao' => 'criar', 'nome' => 'Cadeiras']))
        ->toThrow(ArgumentoInvalido::class, 'Informe quantas unidades existem.');
    expect(AcaoProposta::count())->toBe(0);
});

test('quantidade zero vira erro de validação', function () {
    $usuario = User::factory()->create();

    expect(fn () => app(ProporEquipamento::class)->executar($usuario, conversaDe($usuario), ['operacao' => 'criar', 'nome' => 'Cadeiras', 'quantidade' => 0]))
        ->toThrow(ArgumentoInvalido::class, 'Informe pelo menos uma unidade.');
});

test('criar propõe o equipamento sem gravar', function () {
    $usuario = User::factory()->create();

    $resultado = app(ProporEquipamento::class)->executar($usuario, conversaDe($usuario), [
        'operacao' => 'criar', 'nome' => 'Cadeiras', 'quantidade' => 40, 'local_guarda' => 'Salão',
    ]);

    expect(Equipamento::count())->toBe(0)
        ->and(AcaoProposta::sole()->tipo)->toBe('criar_equipamento')
        ->and($resultado->cartao['dados']['cadastro']['titulo'])->toBe('Novo equipamento: Cadeiras')
        ->and($resultado->cartao['dados']['cadastro']['linhas'])->toContain(['rotulo' => 'Quantidade', 'antes' => null, 'depois' => '40']);
});

test('alterar a manutenção mostra a data no formato dia/mês/ano', function () {
    $usuario = User::factory()->create();
    Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1, 'manutencao_ate' => null]);

    app(ProporEquipamento::class)->executar($usuario, conversaDe($usuario), [
        'operacao' => 'alterar', 'alvo' => 'Projetor', 'manutencao_ate' => '2026-10-20',
    ]);

    expect(AcaoProposta::sole()->dados['linhas'])->toEqual([['rotulo' => 'Manutenção até', 'antes' => '—', 'depois' => '20/10/2026']]);
});

test('excluir equipamento com reserva futura não cria ação', function () {
    $usuario = User::factory()->create();
    $projetor = Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $reserva = Reserva::factory()->for($usuario)->create([
        'espaco_id' => Espaco::factory()->for($usuario)->create()->id, 'data' => '2026-10-20',
    ]);
    $reserva->equipamentos()->attach($projetor->id, ['quantidade' => 1]);

    expect(fn () => app(ProporEquipamento::class)->executar($usuario, conversaDe($usuario), ['operacao' => 'excluir', 'alvo' => 'Projetor']))
        ->toThrow(ArgumentoInvalido::class, 'Há reservas futuras usando este equipamento. Desative em vez de excluir.');
    expect(AcaoProposta::count())->toBe(0)->and(Equipamento::count())->toBe(1);
});
