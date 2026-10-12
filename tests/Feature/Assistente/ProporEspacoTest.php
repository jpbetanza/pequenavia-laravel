<?php

use App\Models\AcaoProposta;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use App\Services\Assistente\Ferramentas\ProporEspaco;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));
});

test('criar propõe o espaço com horários sem gravar e devolve o cartão com as linhas', function () {
    [$usuario, $conversa] = usuarioComConversa();

    $resultado = app(ProporEspaco::class)->executar($usuario, $conversa, [
        'operacao' => 'criar', 'nome' => 'Sala 3', 'capacidade' => 25, 'cor' => 'azul',
        'horarios' => [
            ['dia' => 'segunda', 'abre' => '08:00', 'fecha' => '22:00'],
            ['dia' => 'terca', 'abre' => '08:00', 'fecha' => '22:00'],
            ['dia' => 'quarta', 'abre' => '08:00', 'fecha' => '22:00'],
            ['dia' => 'quinta', 'abre' => '08:00', 'fecha' => '22:00'],
            ['dia' => 'sexta', 'abre' => '08:00', 'fecha' => '22:00'],
        ],
    ]);

    $cadastro = AcaoProposta::sole()->dados;

    expect(Espaco::count())->toBe(0)
        ->and(AcaoProposta::sole()->tipo)->toBe('criar_espaco')
        ->and($cadastro['final']['horarios'])->toHaveCount(5)
        ->and($cadastro['final']['bloqueios'])->toBe([])
        ->and($resultado->cartao['dados']['tipo'])->toBe('criar_espaco')
        ->and($cadastro['linhas'])->toContainEqual(['rotulo' => 'Nome', 'antes' => null, 'depois' => 'Sala 3'])
        ->and($cadastro['linhas'])->toContainEqual(['rotulo' => 'Capacidade', 'antes' => null, 'depois' => '25'])
        ->and($cadastro['linhas'])->toContainEqual(['rotulo' => 'Cor', 'antes' => null, 'depois' => 'azul'])
        ->and($cadastro['linhas'])->toContainEqual(['rotulo' => 'Horários', 'antes' => null, 'depois' => 'Segunda 08:00–22:00, Terça 08:00–22:00, Quarta 08:00–22:00, Quinta 08:00–22:00, Sexta 08:00–22:00']);
});

test('criar sem cor usa bordo como padrão', function () {
    [$usuario, $conversa] = usuarioComConversa();

    app(ProporEspaco::class)->executar($usuario, $conversa, ['operacao' => 'criar', 'nome' => 'Sala 4']);

    expect(AcaoProposta::sole()->dados['final'])->toMatchArray(['nome' => 'Sala 4', 'cor' => 'bordo', 'ativo' => true, 'aceita_solicitacoes' => true]);
});

test('alterar só a capacidade mantém horários, bloqueios, recursos e regras', function () {
    [$usuario, $conversa] = usuarioComConversa();
    espacoComAgenda($usuario);

    app(ProporEspaco::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Salão', 'capacidade' => 25]);

    $dados = AcaoProposta::sole()->dados;

    expect($dados['final']['horarios'])->toHaveCount(5)
        ->and($dados['final']['bloqueios'])->toHaveCount(1)
        ->and($dados['final']['recursos_fixos'])->toBe(['Piano'])
        ->and($dados['final'])->toMatchArray(['nome' => 'Salão', 'cor' => 'verde', 'ativo' => true, 'capacidade' => 25])
        ->and($dados['linhas'])->toEqual([['rotulo' => 'Capacidade', 'antes' => '10', 'depois' => '25']]);
});

test('alterar com horário de sábado soma o dia e mantém os demais', function () {
    [$usuario, $conversa] = usuarioComConversa();
    espacoComAgenda($usuario);

    app(ProporEspaco::class)->executar($usuario, $conversa, [
        'operacao' => 'alterar', 'alvo' => 'Salão', 'horarios' => [['dia' => 'sábado', 'abre' => '08:00', 'fecha' => '18:00']],
    ]);

    $dados = AcaoProposta::sole()->dados;

    expect($dados['final']['horarios'])->toHaveCount(6)
        ->and($dados['final']['horarios'][5])->toEqual(['dia_semana' => 6, 'abre' => '08:00', 'fecha' => '18:00'])
        ->and($dados['linhas'])->toEqual([['rotulo' => 'Sábado', 'antes' => 'Fechado', 'depois' => '08:00–18:00']]);
});

test('horário com fechamento antes da abertura vira ArgumentoInvalido', function () {
    [$usuario, $conversa] = usuarioComConversa();
    espacoComAgenda($usuario);

    expect(fn () => app(ProporEspaco::class)->executar($usuario, $conversa, [
        'operacao' => 'alterar', 'alvo' => 'Salão', 'horarios' => [['dia' => 'domingo', 'abre' => '10:00', 'fecha' => '09:00']],
    ]))->toThrow(ArgumentoInvalido::class, 'O fechamento precisa ser depois da abertura.');
    expect(AcaoProposta::count())->toBe(0);
});

test('adicionar bloqueio mantém o atual e acrescenta o novo', function () {
    [$usuario, $conversa] = usuarioComConversa();
    espacoComAgenda($usuario);

    app(ProporEspaco::class)->executar($usuario, $conversa, [
        'operacao' => 'alterar', 'alvo' => 'Salão',
        'adicionar_bloqueios' => [['inicio' => '2026-12-20', 'fim' => '2026-12-22', 'motivo' => 'Reforma']],
    ]);

    $dados = AcaoProposta::sole()->dados;

    expect($dados['final']['bloqueios'])->toHaveCount(2)
        ->and($dados['final']['bloqueios'][1])->toMatchArray(['inicio' => '2026-12-20', 'fim' => '2026-12-22', 'motivo' => 'Reforma'])
        ->and($dados['linhas'])->toContainEqual(['rotulo' => 'Bloqueios', 'antes' => null, 'depois' => '+ 20/12/2026 a 22/12/2026 (Reforma)']);
});

test('remover bloqueio pelo motivo sem caixa ou acento remove só o correspondente', function () {
    [$usuario, $conversa] = usuarioComConversa();
    $espaco = espacoComAgenda($usuario);
    $espaco->bloqueios()->create(['inicio' => '2026-12-20', 'fim' => '2026-12-22', 'motivo' => 'Reforma']);

    app(ProporEspaco::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Salão', 'remover_bloqueios' => [['motivo' => 'reforma']]]);

    $dados = AcaoProposta::sole()->dados;

    expect($dados['final']['bloqueios'])->toEqual([['inicio' => '2026-11-02', 'fim' => '2026-11-02', 'motivo' => 'Feriado']])
        ->and($dados['linhas'])->toContainEqual(['rotulo' => 'Bloqueios', 'antes' => null, 'depois' => '− 20/12/2026 a 22/12/2026 (Reforma)']);
});

test('remover bloqueio que não existe vira ArgumentoInvalido', function () {
    [$usuario, $conversa] = usuarioComConversa();
    espacoComAgenda($usuario);

    expect(fn () => app(ProporEspaco::class)->executar($usuario, $conversa, [
        'operacao' => 'alterar', 'alvo' => 'Salão', 'remover_bloqueios' => [['motivo' => 'Obra']],
    ]))->toThrow(ArgumentoInvalido::class, 'Não encontrei esse bloqueio no espaço.');
});

test('renomear para o nome de outro espaço do usuário vira ArgumentoInvalido', function () {
    [$usuario, $conversa] = usuarioComConversa();
    espacoComAgenda($usuario, 'Salão');
    Espaco::factory()->for($usuario)->create(['nome' => 'Sala 3']);

    expect(fn () => app(ProporEspaco::class)->executar($usuario, $conversa, ['operacao' => 'alterar', 'alvo' => 'Salão', 'nome' => 'Sala 3']))
        ->toThrow(ArgumentoInvalido::class, 'Já existe um espaço com este nome.');
});

test('excluir espaço com reservas passadas vira ArgumentoInvalido', function () {
    [$usuario, $conversa] = usuarioComConversa();
    $espaco = espacoComAgenda($usuario);
    Reserva::factory()->for($usuario)->create(['espaco_id' => $espaco->id, 'data' => '2026-10-01']);

    expect(fn () => app(ProporEspaco::class)->executar($usuario, $conversa, ['operacao' => 'excluir', 'alvo' => 'Salão']))
        ->toThrow(ArgumentoInvalido::class, 'Há reservas usando este espaço. Desative em vez de excluir.');
    expect(AcaoProposta::count())->toBe(0);
});

test('excluir espaço sem reservas cria a proposta de exclusão', function () {
    [$usuario, $conversa] = usuarioComConversa();
    espacoComAgenda($usuario);

    app(ProporEspaco::class)->executar($usuario, $conversa, ['operacao' => 'excluir', 'alvo' => 'Salão']);

    expect(AcaoProposta::sole()->tipo)->toBe('excluir_espaco')
        ->and(Espaco::count())->toBe(1);
});
