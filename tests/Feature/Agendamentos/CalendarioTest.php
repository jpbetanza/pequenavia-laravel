<?php

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Sábado, 10/10/2026, 10:00 no fuso da agenda. A semana vai de 05/10 (seg.) a 11/10 (dom.).
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));

    $this->dono = User::factory()->create();
    $this->salao = Espaco::factory()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Salão paroquial']);
});

it('redireciona o visitante para o login', function () {
    $this->get(route('agendamentos.calendario'))->assertRedirect(route('login'));
});

it('mostra só as reservas do dono dentro da semana, de segunda a domingo', function () {
    $terca = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-06', 'titulo' => 'Catequese']);
    $domingo = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-11', 'titulo' => 'Missa de família']);
    Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-04', 'titulo' => 'Semana anterior']);
    Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-12', 'titulo' => 'Semana seguinte']);
    Reserva::factory()->for(User::factory())->for(Espaco::factory(), 'espaco')->create(['data' => '2026-10-06']);

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('agendamentos/calendario')
            ->where('semana', ['inicio' => '2026-10-05', 'fim' => '2026-10-11'])
            ->has('reservas', 2)
            ->where('reservas.0.id', $terca->id)
            ->where('reservas.1.id', $domingo->id));
});

it('na visão dia mostra só as reservas da data escolhida, mantendo a semana', function () {
    $terca = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-06']);
    Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-07']);

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario', ['visao' => 'dia', 'data' => '2026-10-06']))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('visao', 'dia')
            ->where('data', '2026-10-06')
            ->where('semana', ['inicio' => '2026-10-05', 'fim' => '2026-10-11'])
            ->has('reservas', 1)
            ->where('reservas.0.id', $terca->id));
});

it('usa o dia de hoje e o agora no fuso da agenda, não no UTC', function () {
    // 05/10/2026 02:00 em UTC ainda é 04/10 (domingo) em São Paulo: a semana é a de 28/09 a 04/10.
    $this->travelTo(Carbon::parse('2026-10-05 02:00', 'UTC'));

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('data', '2026-10-04')
            ->where('visao', 'semana')
            ->where('semana', ['inicio' => '2026-09-28', 'fim' => '2026-10-04'])
            ->where('agora', '2026-10-04T23:00:00-03:00'));
});

it('lista os espaços ativos com horários e só os bloqueios que tocam a semana', function () {
    $bloqueio = EspacoBloqueio::factory()->for($this->salao, 'espaco')->create([
        'inicio' => '2026-10-08',
        'fim' => '2026-10-09',
        'motivo' => 'Reforma do piso',
    ]);
    EspacoBloqueio::factory()->for($this->salao, 'espaco')->create(['inicio' => '2026-12-01', 'fim' => '2026-12-05', 'motivo' => 'Natal']);
    Espaco::factory()->inativo()->for($this->dono)->create(['nome' => 'Sala desativada']);

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('espacos', 1)
            ->where('espacos.0.id', $this->salao->id)
            ->where('espacos.0.nome', 'Salão paroquial')
            ->where('espacos.0.cor', $this->salao->cor->value)
            ->where('espacos.0.horarios.0', ['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '22:00'])
            ->has('espacos.0.bloqueios', 1)
            ->where('espacos.0.bloqueios.0', [
                'id' => $bloqueio->id,
                'inicio' => '2026-10-08',
                'fim' => '2026-10-09',
                'motivo' => 'Reforma do piso',
            ]));
});

it('traz o espaço inativo que tem reserva no intervalo, marcado como inativo, e deixa de fora o inativo sem reservas', function () {
    $desativada = Espaco::factory()->inativo()->for($this->dono)->create(['nome' => 'Auditório antigo']);
    Espaco::factory()->inativo()->for($this->dono)->create(['nome' => 'Depósito sem reservas']);
    $reservaNaDesativada = Reserva::factory()->for($this->dono)->for($desativada, 'espaco')->create(['data' => '2026-10-07']);

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('espacos', 2)
            ->where('espacos.0.id', $desativada->id)
            ->where('espacos.0.ativo', false)
            ->where('espacos.1.id', $this->salao->id)
            ->where('espacos.1.ativo', true)
            ->where('reservas.0.id', $reservaNaDesativada->id)
            ->where('reservas.0.espaco_id', $desativada->id));
});

it('traz as reservas com grupo, pessoas, observações e equipamentos com a entrega', function () {
    $grupo = Grupo::factory()->for($this->dono)->create(['nome' => 'Pastoral da Catequese']);
    $projetor = Equipamento::factory()->for($this->dono)->create(['nome' => 'Projetor', 'quantidade' => 2]);
    $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
        'titulo' => 'Catequese',
        'data' => '2026-10-06',
        'inicio' => '09:00',
        'fim' => '10:30',
        'grupo_id' => $grupo->id,
        'pessoas' => 30,
        'observacoes' => 'Trazer mesas',
    ]);
    $reserva->equipamentos()->attach($projetor, ['quantidade' => 1]);

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('reservas.0', fn (Assert $reservaNaPagina) => $reservaNaPagina
                ->where('id', $reserva->id)
                ->where('titulo', 'Catequese')
                ->where('data', '2026-10-06')
                ->where('inicio', '09:00')
                ->where('fim', '10:30')
                ->where('pessoas', 30)
                ->where('observacoes', 'Trazer mesas')
                ->where('serie_id', null)
                ->where('espaco_id', $this->salao->id)
                ->where('grupo_id', $grupo->id)
                ->where('grupo_nome', 'Pastoral da Catequese')
                ->where('equipamentos.0', [
                    'id' => $projetor->id,
                    'nome' => 'Projetor',
                    'quantidade' => 1,
                    'entregue_em' => null,
                    'recolhido_em' => null,
                ])));
});

it('lista equipamentos e grupos ativos do dono', function () {
    $projetor = Equipamento::factory()->for($this->dono)->create(['nome' => 'Projetor', 'quantidade' => 2]);
    Equipamento::factory()->for($this->dono)->create(['nome' => 'Microfone', 'quantidade' => 4, 'manutencao_ate' => '2026-10-31']);
    Equipamento::factory()->for(User::factory())->create(['nome' => 'Alheio']);
    $grupo = Grupo::factory()->for($this->dono)->create(['nome' => 'Coral', 'ativo' => true]);
    Grupo::factory()->for($this->dono)->create(['nome' => 'Grupo arquivado', 'ativo' => false]);

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('equipamentos', 2)
            ->where('equipamentos.0.nome', 'Microfone')
            ->where('equipamentos.0.manutencao_ate', '2026-10-31')
            ->where('equipamentos.1.id', $projetor->id)
            ->where('equipamentos.1.quantidade', 2)
            ->where('equipamentos.1.manutencao_ate', null)
            ->has('grupos', 1)
            ->where('grupos.0', ['id' => $grupo->id, 'nome' => 'Coral']));
});

it('lista as solicitações pendentes do intervalo, sem as já decididas nem as de outro dono', function () {
    $pendente = Solicitacao::factory()->for($this->dono)->create([
        'titulo' => 'Ensaio do coral',
        'data' => '2026-10-07',
        'inicio' => '19:00',
        'fim' => '21:00',
        'espaco_id' => $this->salao->id,
        'status' => StatusSolicitacao::Pendente,
    ]);
    Solicitacao::factory()->for($this->dono)->create(['data' => '2026-10-07', 'status' => StatusSolicitacao::Aprovada]);
    Solicitacao::factory()->for($this->dono)->create(['data' => '2026-10-07', 'status' => StatusSolicitacao::Recusada]);
    Solicitacao::factory()->for($this->dono)->create(['data' => '2026-10-20', 'status' => StatusSolicitacao::Pendente]);
    Solicitacao::factory()->for(User::factory())->create(['data' => '2026-10-07', 'status' => StatusSolicitacao::Pendente]);

    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('solicitacoes', 1)
            ->where('solicitacoes.0.id', $pendente->id)
            ->where('solicitacoes.0.titulo', 'Ensaio do coral')
            ->where('solicitacoes.0.data', '2026-10-07')
            ->where('solicitacoes.0.inicio', '19:00')
            ->where('solicitacoes.0.fim', '21:00')
            ->where('solicitacoes.0.espaco_id', $this->salao->id));
});

it('não faz uma consulta a mais por reserva', function () {
    $grupo = Grupo::factory()->for($this->dono)->create();
    $projetor = Equipamento::factory()->for($this->dono)->create();
    $primeira = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-06', 'grupo_id' => $grupo->id]);
    $primeira->equipamentos()->attach($projetor, ['quantidade' => 1]);
    $this->actingAs($this->dono)->get(route('agendamentos.calendario'))->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($this->dono)->get(route('agendamentos.calendario'))->assertOk();
    $comUmaReserva = count(DB::getQueryLog());

    for ($dia = 7; $dia <= 9; $dia++) {
        $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
            'data' => "2026-10-0{$dia}",
            'grupo_id' => $grupo->id,
        ]);
        $reserva->equipamentos()->attach($projetor, ['quantidade' => 1]);
    }

    DB::flushQueryLog();
    $this->actingAs($this->dono)->get(route('agendamentos.calendario'))->assertOk();
    $comQuatroReservas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($comQuatroReservas)->toBe($comUmaReserva);
});

it('recusa data ou visão inválidas', function () {
    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario', ['data' => '10/10/2026', 'visao' => 'mes']))
        ->assertSessionHasErrors(['data', 'visao']);
});

it('aceita e ignora os parâmetros de nova reserva vindos do painel do assistente', function () {
    $this->actingAs($this->dono)
        ->get(route('agendamentos.calendario', ['data' => '2026-10-06', 'visao' => 'dia', 'nova_reserva' => 1, 'espaco' => $this->salao->id, 'inicio' => '14:00']))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('agendamentos/calendario')
            ->where('data', '2026-10-06')
            ->where('visao', 'dia'));
});
