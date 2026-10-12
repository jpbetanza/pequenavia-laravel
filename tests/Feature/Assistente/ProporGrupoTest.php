<?php

use App\Models\AcaoProposta;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use App\Services\Assistente\Ferramentas\ProporGrupo;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));
});

test('criar propõe o grupo sem gravar e devolve o cartão com as linhas', function () {
    [$usuario, $conversa] = usuarioComConversa();

    $resultado = app(ProporGrupo::class)->executar($usuario, $conversa, [
        'operacao' => 'criar', 'nome' => 'Pastoral da Juventude', 'coordenacao' => 'Ana', 'ativo' => true,
    ]);

    expect(Grupo::count())->toBe(0)
        ->and(AcaoProposta::sole()->tipo)->toBe('criar_grupo')
        ->and($resultado->cartao['tipo'])->toBe('acao_proposta')
        ->and($resultado->cartao['dados']['tipo'])->toBe('criar_grupo')
        ->and($resultado->cartao['dados']['status'])->toBe('proposta')
        ->and($resultado->cartao['dados']['cadastro']['titulo'])->toBe('Novo grupo: Pastoral da Juventude')
        ->and($resultado->cartao['dados']['cadastro']['linhas'])->toContain(['rotulo' => 'Nome', 'antes' => null, 'depois' => 'Pastoral da Juventude'])
        ->and($resultado->paraOModelo['acao_id'])->toBe(AcaoProposta::sole()->id);
});

test('criar com nome que já existe para o usuário vira ArgumentoInvalido', function () {
    [$usuario, $conversa] = usuarioComConversa();
    Grupo::factory()->for($usuario)->create(['nome' => 'Catequese']);

    expect(fn () => app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'criar', 'nome' => 'Catequese']))
        ->toThrow(ArgumentoInvalido::class, 'Já existe um grupo com este nome.');
    expect(AcaoProposta::count())->toBe(0);
});

test('alterar propõe só a mudança e mantém os demais campos', function () {
    [$usuario, $conversa] = usuarioComConversa();
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Catequese', 'coordenacao' => 'Ana', 'contato' => '11 9999', 'ativo' => true]);

    app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'catequese', 'coordenacao' => 'Bia']);

    $dados = AcaoProposta::sole()->dados;

    expect($dados['final'])->toMatchArray(['nome' => 'Catequese', 'coordenacao' => 'Bia', 'contato' => '11 9999', 'ativo' => true])
        ->and($dados['linhas'])->toEqual([['rotulo' => 'Coordenação', 'antes' => 'Ana', 'depois' => 'Bia']])
        ->and($dados['alvo_id'])->toBe($grupo->id)
        ->and($grupo->fresh()->coordenacao)->toBe('Ana');
});

test('alterar sem nenhum campo diferente vira ArgumentoInvalido', function () {
    [$usuario, $conversa] = usuarioComConversa();
    Grupo::factory()->for($usuario)->create(['nome' => 'Catequese', 'coordenacao' => 'Ana']);

    expect(fn () => app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Catequese', 'coordenacao' => 'Ana']))
        ->toThrow(ArgumentoInvalido::class, 'Nada para alterar: os valores informados já são os atuais.');
    expect(AcaoProposta::count())->toBe(0);
});

test('excluir grupo com reservas futuras avisa quantas ficam sem grupo', function () {
    [$usuario, $conversa] = usuarioComConversa();
    $grupo = Grupo::factory()->for($usuario)->create(['nome' => 'Coral']);
    foreach (['2026-10-20', '2026-10-27'] as $data) {
        Reserva::factory()->for($usuario)->create([
            'espaco_id' => Espaco::factory()->for($usuario)->create()->id, 'grupo_id' => $grupo->id, 'data' => $data,
        ]);
    }

    app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'excluir', 'alvo' => 'Coral']);

    expect(AcaoProposta::sole()->dados['avisos'])->toBe(['2 reservas futuras ficarão sem grupo.'])
        ->and(Grupo::count())->toBe(1);
});

test('alvo de outro usuário vira "Não encontrei o grupo"', function () {
    [$usuario, $conversa] = usuarioComConversa();
    Grupo::factory()->create(['nome' => 'Coral']);

    expect(fn () => app(ProporGrupo::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Coral', 'coordenacao' => 'Bia']))
        ->toThrow(ArgumentoInvalido::class, 'Não encontrei o grupo');
    expect(AcaoProposta::count())->toBe(0);
});
