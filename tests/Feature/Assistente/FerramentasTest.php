<?php

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\GeracaoCertificado;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use App\Services\Assistente\Ferramentas\ConsultarAgenda;
use App\Services\Assistente\Ferramentas\ListarCadastros;
use App\Services\Assistente\Ferramentas\ListarSolicitacoes;
use App\Services\Assistente\Ferramentas\ResolvedorDeCadastros;
use App\Services\Assistente\Ferramentas\ResumoDeCertificados;
use App\Services\Assistente\Ferramentas\VerificarDisponibilidade;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo')));

test('consultar_agenda lista só as reservas do usuário no período, com cartão', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->for($usuario)->create(['nome' => 'Salão paroquial', 'cor' => 'bordo']);
    Reserva::factory()->for($usuario)->create(['espaco_id' => $salao->id, 'titulo' => 'Encontro de noivos', 'data' => '2026-10-18', 'inicio' => '09:00', 'fim' => '12:00']);
    Reserva::factory()->for($usuario)->create(['espaco_id' => $salao->id, 'titulo' => 'Fora do período', 'data' => '2026-11-30', 'inicio' => '09:00', 'fim' => '10:00']);
    Reserva::factory()->create(['titulo' => 'De outro usuário', 'data' => '2026-10-18']);

    $resultado = executar(ConsultarAgenda::class, $usuario, ['inicio' => '2026-10-13', 'fim' => '2026-10-19']);

    expect(collect($resultado->paraOModelo['reservas'])->pluck('titulo')->all())->toBe(['Encontro de noivos'])
        ->and($resultado->cartao['tipo'])->toBe('reservas')
        ->and($resultado->cartao['dados']['reservas'][0])->toMatchArray(['titulo' => 'Encontro de noivos', 'espaco_nome' => 'Salão paroquial', 'espaco_cor' => 'bordo', 'inicio' => '09:00', 'fim' => '12:00']);
});

test('consultar_agenda recusa período maior que 31 dias e data inválida', function (array $argumentos) {
    expect(fn () => executar(ConsultarAgenda::class, User::factory()->create(), $argumentos))->toThrow(ArgumentoInvalido::class);
})->with([[['inicio' => '2026-10-01', 'fim' => '2026-12-01']], [['inicio' => 'amanhã', 'fim' => '2026-10-20']]]);

test('verificar_disponibilidade com horário devolve conflito e sugestões', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Sala 2', 'capacidade' => 30]);
    Reserva::factory()->for($usuario)->create(['espaco_id' => $salao->id, 'titulo' => 'Encontro de noivos', 'data' => '2026-10-16', 'inicio' => '09:00', 'fim' => '12:00']);

    $resultado = executar(VerificarDisponibilidade::class, $usuario, ['espaco' => 'salão PAROQUIAL', 'data' => '2026-10-16', 'inicio' => '10:00', 'fim' => '11:00']);

    expect($resultado->paraOModelo['livre'])->toBeFalse()
        ->and($resultado->cartao['tipo'])->toBe('disponibilidade')
        ->and($resultado->cartao['dados']['pedido']['conflitos'][0]['tipo'])->toBe('sala_ocupada')
        ->and($resultado->cartao['dados']['pedido']['sugestoes']['espacos'][0]['nome'])->toBe('Sala 2');
});

test('verificar_disponibilidade sem horário devolve a ocupação do dia', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    Reserva::factory()->for($usuario)->create(['espaco_id' => $salao->id, 'titulo' => 'Encontro de noivos', 'data' => '2026-10-16', 'inicio' => '09:00', 'fim' => '12:00']);

    $resultado = executar(VerificarDisponibilidade::class, $usuario, ['espaco' => $salao->id, 'data' => '2026-10-16']);

    expect($resultado->cartao['dados']['pedido'])->toBeNull()
        ->and($resultado->cartao['dados']['reservas'])->toBe([['inicio' => '09:00', 'fim' => '12:00', 'titulo' => 'Encontro de noivos']])
        ->and($resultado->cartao['dados']['horario_do_dia'])->not->toBeNull();
});

test('verificar_disponibilidade não enxerga espaço de outro usuário', function () {
    $alheio = Espaco::factory()->create(['nome' => 'Salão alheio']);

    expect(fn () => executar(VerificarDisponibilidade::class, User::factory()->create(), ['espaco' => $alheio->id, 'data' => '2026-10-16']))
        ->toThrow(ArgumentoInvalido::class, 'Não encontrei o espaço');
});

test('listar_solicitacoes devolve as pendentes do usuário com o estado de conflito', function () {
    $usuario = User::factory()->create();
    Solicitacao::factory()->pendente()->for($usuario)->create(['titulo' => 'Ensaio do coral', 'espaco_id' => null]);
    Solicitacao::factory()->pendente()->create(['titulo' => 'De outro usuário']);

    $resultado = executar(ListarSolicitacoes::class, $usuario, []);

    expect($resultado->cartao['dados']['solicitacoes'])->toHaveCount(1)
        ->and($resultado->cartao['dados']['solicitacoes'][0])->toMatchArray(['titulo' => 'Ensaio do coral', 'estado' => 'escolha_um_espaco']);
});

test('listar_cadastros traz espaços, equipamentos e grupos ativos do usuário', function () {
    $usuario = User::factory()->create();
    Espaco::factory()->for($usuario)->create(['nome' => 'Salão']);
    Espaco::factory()->inativo()->for($usuario)->create(['nome' => 'Antigo']);
    Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 2]);
    Grupo::factory()->for($usuario)->create(['nome' => 'Catequese', 'ativo' => true]);

    $resultado = executar(ListarCadastros::class, $usuario, []);

    expect(collect($resultado->paraOModelo['espacos'])->pluck('nome')->all())->toBe(['Salão'])
        ->and($resultado->paraOModelo['equipamentos'][0])->toMatchArray(['nome' => 'Projetor', 'quantidade' => 2])
        ->and($resultado->paraOModelo['grupos'][0]['nome'])->toBe('Catequese')
        ->and($resultado->cartao)->toBeNull();
});

test('resumo_de_certificados conta emissões do usuário por modelo no período', function () {
    $usuario = User::factory()->create();
    GeracaoCertificado::factory()->count(3)->for($usuario)->create(['modelo_title' => 'Batismo', 'created_at' => '2026-10-05 12:00']);
    GeracaoCertificado::factory()->for($usuario)->create(['modelo_title' => 'Crisma', 'created_at' => '2026-10-06 12:00']);
    GeracaoCertificado::factory()->for($usuario)->create(['modelo_title' => 'Batismo', 'created_at' => '2026-09-01 12:00']);
    GeracaoCertificado::factory()->create(['modelo_title' => 'Batismo', 'created_at' => '2026-10-05 12:00']);

    $resultado = executar(ResumoDeCertificados::class, $usuario, ['inicio' => '2026-10-01', 'fim' => '2026-10-31']);

    expect($resultado->paraOModelo['total'])->toBe(4)
        ->and($resultado->cartao['dados']['por_modelo'])->toBe([['modelo' => 'Batismo', 'total' => 3], ['modelo' => 'Crisma', 'total' => 1]]);
});

test('nome de espaço ignora maiúsculas e acentos e nome inexistente vira erro', function () {
    $usuario = User::factory()->create();
    $espaco = Espaco::factory()->for($usuario)->create(['nome' => 'Salão Paroquial']);

    $resolvedor = app(ResolvedorDeCadastros::class);

    expect($resolvedor->espaco($usuario, 'salao paroquial')->id)->toBe($espaco->id)
        ->and(fn () => $resolvedor->espaco($usuario, 'Capela'))->toThrow(ArgumentoInvalido::class, 'Não encontrei o espaço “Capela”.');
});

test('resumo_de_certificados recusa período maior que 366 dias', function () {
    expect(fn () => executar(ResumoDeCertificados::class, User::factory()->create(), ['inicio' => '2025-01-01', 'fim' => '2026-12-31']))
        ->toThrow(ArgumentoInvalido::class, 'no máximo 366 dias');
});

test('verificar_disponibilidade trata inicio e fim vazios como ausentes', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);

    $resultado = executar(VerificarDisponibilidade::class, $usuario, ['espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '', 'fim' => '']);

    expect($resultado->cartao['dados']['pedido'])->toBeNull();
});

test('verificar_disponibilidade exige início e fim juntos', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);

    expect(fn () => executar(VerificarDisponibilidade::class, $usuario, ['espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '10:00', 'fim' => '']))
        ->toThrow(ArgumentoInvalido::class, 'Informe início e fim, ou nenhum dos dois.');
});

test('listar_cadastros dá o nome do dia da semana junto do número', function () {
    $usuario = User::factory()->create();
    $espaco = Espaco::factory()->for($usuario)->create(['nome' => 'Salão']);
    $espaco->horarios()->create(['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '12:00']);

    $resultado = executar(ListarCadastros::class, $usuario, []);

    expect($resultado->paraOModelo['espacos'][0]['horarios'][0])->toBe(['dia_semana' => 1, 'dia' => 'segunda', 'abre' => '08:00', 'fecha' => '12:00']);
});

test('listar_solicitacoes com status aprovada devolve só as aprovadas do usuário', function () {
    $usuario = User::factory()->create();
    Solicitacao::factory()->for($usuario)->create(['titulo' => 'Coral aprovado', 'status' => StatusSolicitacao::Aprovada, 'espaco_id' => null]);
    Solicitacao::factory()->pendente()->for($usuario)->create(['titulo' => 'Coral pendente', 'espaco_id' => null]);

    $resultado = executar(ListarSolicitacoes::class, $usuario, ['status' => 'aprovada']);

    expect($resultado->cartao['dados']['status'])->toBe('aprovada')
        ->and(collect($resultado->cartao['dados']['solicitacoes'])->pluck('titulo')->all())->toBe(['Coral aprovado']);
});

test('listar_solicitacoes recusa status fora da lista', function () {
    expect(fn () => executar(ListarSolicitacoes::class, User::factory()->create(), ['status' => 'cancelada']))->toThrow(ArgumentoInvalido::class);
});

test('verificar_disponibilidade acha cada equipamento pelo nome: o ocupado conflita e o livre não', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    $outroEspaco = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Sala 2']);
    Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $caixa = Equipamento::factory()->for($usuario)->create(['nome' => 'Caixa de som', 'quantidade' => 1]);
    Reserva::factory()->for($usuario)->create(['espaco_id' => $outroEspaco->id, 'data' => '2026-10-16', 'inicio' => '09:00', 'fim' => '12:00'])
        ->equipamentos()->attach($caixa->id, ['quantidade' => 1]);

    $ocupado = executar(VerificarDisponibilidade::class, $usuario, ['espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '10:00', 'fim' => '11:00', 'equipamentos' => [['equipamento' => 'caixa DE som', 'quantidade' => 1]]]);
    $livre = executar(VerificarDisponibilidade::class, $usuario, ['espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '10:00', 'fim' => '11:00', 'equipamentos' => [['equipamento' => 'projetor', 'quantidade' => 1]]]);

    expect($ocupado->cartao['dados']['pedido']['conflitos'][0]['tipo'])->toBe('equipamento_indisponivel')
        ->and($livre->paraOModelo['livre'])->toBeTrue()
        ->and($livre->cartao['dados']['pedido']['conflitos'])->toBe([]);
});

test('verificar_disponibilidade com equipamento esgotado aponta equipamento_indisponivel', function () {
    $usuario = User::factory()->create();
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    $outroEspaco = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Sala 2']);
    $projetor = Equipamento::factory()->for($usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
    $reservaComProjetor = Reserva::factory()->for($usuario)->create(['espaco_id' => $outroEspaco->id, 'data' => '2026-10-16', 'inicio' => '09:00', 'fim' => '12:00']);
    $reservaComProjetor->equipamentos()->attach($projetor->id, ['quantidade' => 1]);

    $resultado = executar(VerificarDisponibilidade::class, $usuario, ['espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => '10:00', 'fim' => '11:00', 'equipamentos' => [['equipamento' => 'Projetor', 'quantidade' => 1]]]);

    expect($resultado->paraOModelo['livre'])->toBeFalse()
        ->and($resultado->cartao['dados']['pedido']['conflitos'][0]['tipo'])->toBe('equipamento_indisponivel');
});
