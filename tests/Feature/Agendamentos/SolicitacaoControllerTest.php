<?php

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Hoje é sábado, 10/10/2026, 10:00 no fuso da agenda. 12/10 é segunda, 13/10 terça e 14/10 quarta.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));

    $this->secretaria = User::factory()->create();
    $this->salao = Espaco::factory()->comHorarioComercial()->for($this->secretaria)->create(['nome' => 'Salão paroquial', 'capacidade' => 80]);
    $this->sala = Espaco::factory()->comHorarioComercial()->for($this->secretaria)->create(['nome' => 'Sala 2', 'capacidade' => 15]);
    $this->projetor = Equipamento::factory()->for($this->secretaria)->create(['nome' => 'Projetor', 'quantidade' => 2]);
    $this->grupo = Grupo::factory()->for($this->secretaria)->create(['nome' => 'Catequese']);
});

/**
 * Pedido pendente do dono, no salão, na data e horário dados.
 */
function pedidoPendenteDoSalao(User $dono, Espaco $espaco, string $data, string $inicio, string $fim, array $extra = []): Solicitacao
{
    return Solicitacao::factory()->pendente()->for($dono)->for($espaco, 'espaco')->create(array_merge([
        'titulo' => 'Ensaio do coral',
        'data' => $data,
        'inicio' => $inicio,
        'fim' => $fim,
    ], $extra));
}

/**
 * Reserva da secretaria no salão, com os mesmos campos de uma reserva comum.
 */
function reservaDoSalao(User $dono, Espaco $espaco, string $data, string $inicio, string $fim): Reserva
{
    return Reserva::factory()->for($dono)->for($espaco, 'espaco')->create([
        'titulo' => 'Missa das crianças',
        'data' => $data,
        'inicio' => $inicio,
        'fim' => $fim,
    ]);
}

describe('listagem', function () {
    it('lista as pendentes por padrão, com as contagens de cada status, e filtra pelo status pedido', function () {
        pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');
        pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-13', '19:00', '21:00');
        Solicitacao::factory()->for($this->secretaria)->create(['status' => StatusSolicitacao::Aprovada]);
        Solicitacao::factory()->for($this->secretaria)->create(['status' => StatusSolicitacao::Recusada]);
        Solicitacao::factory()->pendente()->create();

        $this->actingAs($this->secretaria)
            ->get(route('agendamentos.solicitacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('agendamentos/solicitacoes')
                ->where('status', 'pendente')
                ->where('contagens', ['pendente' => 2, 'aprovada' => 1, 'recusada' => 1])
                ->has('solicitacoes', 2)
                ->where('link_do_formulario', route('reservar.create', $this->secretaria->agendaSlug()))
                ->has('espacos', 2)
                ->has('equipamentos', 1));

        $this->actingAs($this->secretaria)
            ->get(route('agendamentos.solicitacoes.index', ['status' => 'recusada']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'recusada')
                ->has('solicitacoes', 1)
                ->where('solicitacoes.0.status', 'recusada'));
    });

    it('calcula o estado de cada pendente no servidor: conflito com motivo e sugestão, escolha de espaço e sem conflito', function () {
        reservaDoSalao($this->secretaria, $this->salao, '2026-10-12', '20:00', '22:00');

        pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00', ['pessoas' => 10]);
        pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-13', '19:00', '21:00', ['espaco_id' => null]);
        pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-14', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->get(route('agendamentos.solicitacoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('solicitacoes.0.estado', 'conflito')
                ->where('solicitacoes.0.conflitos.0.tipo', 'sala_ocupada')
                ->where('solicitacoes.0.sugestoes.espacos.0.id', $this->sala->id)
                ->where('solicitacoes.1.estado', 'escolha_um_espaco')
                ->where('solicitacoes.1.espaco_id', null)
                ->where('solicitacoes.2.estado', 'sem_conflito')
                ->where('solicitacoes.2.conflitos', []));
    });

    it('mostra o cartão completo com grupo, equipamentos e data de recebimento', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-14', '19:00', '21:00', [
            'grupo_id' => $this->grupo->id,
            'nome' => 'Maria Silva',
            'telefone' => '11987654321',
            'pessoas' => 12,
            'observacoes' => 'Precisa de cadeiras extras.',
        ]);
        $solicitacao->equipamentos()->attach($this->projetor->id, ['quantidade' => 1]);

        $this->actingAs($this->secretaria)
            ->get(route('agendamentos.solicitacoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('solicitacoes.0.id', $solicitacao->id)
                ->where('solicitacoes.0.nome', 'Maria Silva')
                ->where('solicitacoes.0.telefone', '11987654321')
                ->where('solicitacoes.0.grupo_nome', 'Catequese')
                ->where('solicitacoes.0.data', '2026-10-14')
                ->where('solicitacoes.0.inicio', '19:00')
                ->where('solicitacoes.0.fim', '21:00')
                ->where('solicitacoes.0.pessoas', 12)
                ->where('solicitacoes.0.observacoes', 'Precisa de cadeiras extras.')
                ->where('solicitacoes.0.status', 'pendente')
                ->where('solicitacoes.0.reserva_id', null)
                ->where('solicitacoes.0.equipamentos.0.id', $this->projetor->id)
                ->where('solicitacoes.0.equipamentos.0.quantidade', 1)
                ->where('solicitacoes.0.recebida_em', '2026-10-10T10:00:00-03:00'));
    });
});

describe('edição pela secretaria', function () {
    it('recalcula o estado a cada edição e devolve o cartão em JSON', function () {
        reservaDoSalao($this->secretaria, $this->salao, '2026-10-13', '09:00', '10:00');
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
                'espaco_id' => $this->salao->id,
                'data' => '2026-10-13',
                'inicio' => '09:30',
                'fim' => '10:30',
                'pessoas' => 8,
                'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'conflito')
            ->assertJsonPath('conflitos.0.tipo', 'sala_ocupada')
            ->assertJsonPath('sugestoes.espacos.0.id', $this->sala->id)
            ->assertJsonPath('data', '2026-10-13')
            ->assertJsonPath('pessoas', 8);

        $this->actingAs($this->secretaria)
            ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
                'espaco_id' => $this->salao->id,
                'data' => '2026-10-13',
                'inicio' => '11:00',
                'fim' => '12:00',
                'pessoas' => 8,
                'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'sem_conflito')
            ->assertJsonPath('conflitos', [])
            ->assertJsonPath('equipamentos.0.quantidade', 2);

        $solicitacao->refresh();

        expect($solicitacao->inicio)->toBe('11:00:00')
            ->and($solicitacao->equipamentos->first()->pivot->quantidade)->toBe(2);
    });

    it('aceita espaço nulo na edição e marca como escolha de espaço', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
                'espaco_id' => null,
                'data' => '2026-10-12',
                'inicio' => '19:00',
                'fim' => '21:00',
                'equipamentos' => [],
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'escolha_um_espaco');
    });

    it('sem JSON, volta para a página anterior', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->from(route('agendamentos.solicitacoes.index'))
            ->put(route('agendamentos.solicitacoes.update', $solicitacao), [
                'espaco_id' => $this->sala->id,
                'data' => '2026-10-12',
                'inicio' => '19:00',
                'fim' => '21:00',
                'equipamentos' => [],
            ])
            ->assertRedirect(route('agendamentos.solicitacoes.index'));

        expect($solicitacao->refresh()->espaco_id)->toBe($this->sala->id);
    });

    it('não edita uma solicitação que já foi respondida', function () {
        $solicitacao = Solicitacao::factory()->for($this->secretaria)->create(['status' => StatusSolicitacao::Recusada]);

        $this->actingAs($this->secretaria)
            ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
                'espaco_id' => null,
                'data' => '2026-10-12',
                'inicio' => '19:00',
                'fim' => '21:00',
                'equipamentos' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Só é possível editar uma solicitação pendente.');
    });

    it('recusa fim que não é depois do início e espaço de outro dono', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
                'espaco_id' => $this->salao->id,
                'data' => '2026-10-12',
                'inicio' => '21:00',
                'fim' => '19:00',
                'equipamentos' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fim']);

        $this->actingAs($this->secretaria)
            ->putJson(route('agendamentos.solicitacoes.update', $solicitacao), [
                'espaco_id' => Espaco::factory()->for(User::factory())->create()->id,
                'data' => '2026-10-12',
                'inicio' => '19:00',
                'fim' => '21:00',
                'equipamentos' => [],
            ])
            ->assertNotFound();
    });

    it('responde 404 para a solicitação de outro dono', function () {
        $alheia = pedidoPendenteDoSalao(User::factory()->create(), $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->putJson(route('agendamentos.solicitacoes.update', $alheia), [
                'espaco_id' => null,
                'data' => '2026-10-12',
                'inicio' => '19:00',
                'fim' => '21:00',
                'equipamentos' => [],
            ])
            ->assertNotFound();
    });
});

describe('aprovação', function () {
    it('sem conflito, cria a reserva com os equipamentos, vincula e muda o status', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00', [
            'grupo_id' => $this->grupo->id,
            'pessoas' => 20,
            'observacoes' => 'Precisa de projetor.',
        ]);
        $solicitacao->equipamentos()->attach($this->projetor->id, ['quantidade' => 1]);

        $this->actingAs($this->secretaria)
            ->post(route('agendamentos.solicitacoes.aprovar', $solicitacao))
            ->assertRedirect(route('agendamentos.solicitacoes.index'))
            ->assertSessionHasNoErrors();

        $reserva = Reserva::query()->sole();
        $solicitacao->refresh();

        expect($reserva->user_id)->toBe($this->secretaria->id)
            ->and($reserva->espaco_id)->toBe($this->salao->id)
            ->and($reserva->grupo_id)->toBe($this->grupo->id)
            ->and($reserva->titulo)->toBe('Ensaio do coral')
            ->and($reserva->data->toDateString())->toBe('2026-10-12')
            ->and($reserva->pessoas)->toBe(20)
            ->and($reserva->observacoes)->toBe('Precisa de projetor.')
            ->and($reserva->solicitacao_id)->toBe($solicitacao->id)
            ->and($reserva->equipamentos->first()->pivot->quantidade)->toBe(1)
            ->and($solicitacao->status)->toBe(StatusSolicitacao::Aprovada)
            ->and($solicitacao->reserva_id)->toBe($reserva->id);
    });

    it('com conflito, responde 422 com os motivos e não cria nada', function () {
        reservaDoSalao($this->secretaria, $this->salao, '2026-10-12', '20:00', '22:00');
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->postJson(route('agendamentos.solicitacoes.aprovar', $solicitacao))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Há conflito de horário, espaço ou equipamento. Resolva antes de aprovar.')
            ->assertJsonPath('conflitos.0.tipo', 'sala_ocupada');

        expect(Reserva::query()->count())->toBe(1)
            ->and($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Pendente)
            ->and($solicitacao->reserva_id)->toBeNull();
    });

    it('sem espaço definido, responde 422 e não cria nada', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00', ['espaco_id' => null]);

        $this->actingAs($this->secretaria)
            ->postJson(route('agendamentos.solicitacoes.aprovar', $solicitacao))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Escolha um espaço antes de aprovar.');

        expect(Reserva::query()->count())->toBe(0);
    });

    it('não aprova uma solicitação que já foi aprovada', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)->post(route('agendamentos.solicitacoes.aprovar', $solicitacao))->assertRedirect();

        $this->actingAs($this->secretaria)
            ->postJson(route('agendamentos.solicitacoes.aprovar', $solicitacao))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta solicitação já foi respondida e não pode ser aprovada.');

        expect(Reserva::query()->count())->toBe(1);
    });

    it('responde 404 para a solicitação de outro dono', function () {
        $alheia = pedidoPendenteDoSalao(User::factory()->create(), $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->post(route('agendamentos.solicitacoes.aprovar', $alheia))
            ->assertNotFound();

        expect(Reserva::query()->count())->toBe(0);
    });
});

describe('recusa', function () {
    it('grava o status e o motivo opcional', function () {
        $comMotivo = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');
        $semMotivo = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-13', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->post(route('agendamentos.solicitacoes.recusar', $comMotivo), ['motivo_recusa' => 'Salão ocupado pela paróquia.'])
            ->assertRedirect(route('agendamentos.solicitacoes.index'));

        $this->actingAs($this->secretaria)
            ->post(route('agendamentos.solicitacoes.recusar', $semMotivo))
            ->assertRedirect();

        expect($comMotivo->refresh()->status)->toBe(StatusSolicitacao::Recusada)
            ->and($comMotivo->motivo_recusa)->toBe('Salão ocupado pela paróquia.')
            ->and($semMotivo->refresh()->status)->toBe(StatusSolicitacao::Recusada)
            ->and($semMotivo->motivo_recusa)->toBeNull();
    });

    it('recusa motivo com mais de 200 caracteres e uma solicitação já respondida', function () {
        $solicitacao = pedidoPendenteDoSalao($this->secretaria, $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->postJson(route('agendamentos.solicitacoes.recusar', $solicitacao), ['motivo_recusa' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_recusa']);

        $this->actingAs($this->secretaria)->post(route('agendamentos.solicitacoes.recusar', $solicitacao))->assertRedirect();

        $this->actingAs($this->secretaria)
            ->postJson(route('agendamentos.solicitacoes.recusar', $solicitacao))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta solicitação já foi respondida e não pode ser recusada.');
    });

    it('responde 404 para a solicitação de outro dono', function () {
        $alheia = pedidoPendenteDoSalao(User::factory()->create(), $this->salao, '2026-10-12', '19:00', '21:00');

        $this->actingAs($this->secretaria)
            ->post(route('agendamentos.solicitacoes.recusar', $alheia))
            ->assertNotFound();

        expect($alheia->refresh()->status)->toBe(StatusSolicitacao::Pendente);
    });
});
