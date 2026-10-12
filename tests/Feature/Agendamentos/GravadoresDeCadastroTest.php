<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\EspacoHorario;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeEquipamento;
use App\Services\Agenda\Cadastros\GravadorDeEspaco;
use App\Services\Agenda\Cadastros\GravadorDeGrupo;
use App\Services\Agenda\Cadastros\RegrasDeEspaco;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // "Hoje" é sábado, 10/10/2026, no fuso da agenda.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));
});

/**
 * @return array<string, mixed>
 */
function dadosValidadosDoEspaco(): array
{
    return [
        'nome' => 'Salão paroquial',
        'capacidade' => 120,
        'localizacao' => 'Térreo',
        'cor' => 'roxo',
        'recursos_fixos' => ['Telão'],
        'aceita_solicitacoes' => true,
        'antecedencia_min_horas' => 24,
        'duracao_max_min' => null,
        'intervalo_min' => 0,
        'ativo' => true,
        'horarios' => [
            ['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '22:00'],
            ['dia_semana' => 6, 'abre' => '08:00', 'fecha' => '18:00'],
        ],
        'bloqueios' => [
            ['inicio' => '2026-12-20', 'fim' => '2027-01-06', 'motivo' => 'Festas de fim de ano'],
        ],
    ];
}

it('cria o espaço com seus horários e bloqueios', function () {
    $dono = User::factory()->create();

    $espaco = app(GravadorDeEspaco::class)->criar($dono, dadosValidadosDoEspaco());

    expect($espaco->user_id)->toBe($dono->id)
        ->and($espaco->nome)->toBe('Salão paroquial')
        ->and(EspacoHorario::where('espaco_id', $espaco->id)->count())->toBe(2)
        ->and(EspacoBloqueio::where('espaco_id', $espaco->id)->count())->toBe(1);
});

it('atualizar substitui os horários e bloqueios pelo estado final', function () {
    $dono = User::factory()->create();
    $espaco = Espaco::factory()->for($dono, 'user')->create();
    EspacoHorario::factory()->for($espaco, 'espaco')->create(['dia_semana' => 3]);
    EspacoBloqueio::factory()->for($espaco, 'espaco')->create(['motivo' => 'Antigo']);

    $dados = dadosValidadosDoEspaco();
    $dados['horarios'] = [['dia_semana' => 5, 'abre' => '09:00', 'fecha' => '10:00']];
    $dados['bloqueios'] = [];
    $dados['nome'] = 'Salão renomeado';

    app(GravadorDeEspaco::class)->atualizar($espaco, $dados);

    $espaco->refresh();
    expect($espaco->nome)->toBe('Salão renomeado')
        ->and($espaco->horarios->pluck('dia_semana')->all())->toBe([5])
        ->and($espaco->bloqueios)->toHaveCount(0);
});

it('motivoParaNaoExcluir aponta reservas futuras, reservas passadas e null sem reservas', function () {
    $dono = User::factory()->create();
    $gravador = app(GravadorDeEspaco::class);

    $semReservas = Espaco::factory()->for($dono, 'user')->create();
    expect($gravador->motivoParaNaoExcluir($semReservas))->toBeNull();

    $passada = Espaco::factory()->for($dono, 'user')->create();
    Reserva::factory()->create(['user_id' => $dono->id, 'espaco_id' => $passada->id, 'data' => '2026-10-01']);
    expect($gravador->motivoParaNaoExcluir($passada))
        ->toBe('Há reservas usando este espaço. Desative em vez de excluir.');

    $futura = Espaco::factory()->for($dono, 'user')->create();
    Reserva::factory()->create(['user_id' => $dono->id, 'espaco_id' => $futura->id, 'data' => '2026-10-20']);
    expect($gravador->motivoParaNaoExcluir($futura))
        ->toBe('Há reservas futuras usando este espaço. Desative em vez de excluir.');
});

it('excluir lança ValidationException com a mensagem de reserva futura e mantém o espaço', function () {
    $dono = User::factory()->create();
    $espaco = Espaco::factory()->for($dono, 'user')->create();
    Reserva::factory()->create(['user_id' => $dono->id, 'espaco_id' => $espaco->id, 'data' => '2026-10-20']);

    expect(fn () => app(GravadorDeEspaco::class)->excluir($espaco))
        ->toThrow(ValidationException::class);

    expect(Espaco::whereKey($espaco->id)->exists())->toBeTrue();
});

it('errosCruzados aponta fechamento que não é depois da abertura', function () {
    $erros = RegrasDeEspaco::errosCruzados([
        'horarios' => [
            ['dia_semana' => 1, 'abre' => '10:00', 'fecha' => '10:00'],
            ['dia_semana' => 2, 'abre' => '08:00', 'fecha' => '09:00'],
        ],
    ]);

    expect($erros)->toBe(['horarios.0.fecha' => 'O fechamento precisa ser depois da abertura.']);
});

it('errosCruzados aponta bloqueio que termina antes de começar', function () {
    $erros = RegrasDeEspaco::errosCruzados([
        'bloqueios' => [
            ['inicio' => '2026-12-20', 'fim' => '2026-12-19', 'motivo' => 'Reforma'],
            ['inicio' => '2026-12-20', 'fim' => '2026-12-20', 'motivo' => 'Ok'],
        ],
    ]);

    expect($erros)->toBe(['bloqueios.0.fim' => 'O fim do bloqueio não pode ser antes do início.']);
});

it('errosCruzados ignora valores fora do formato para não duplicar erro de formato', function () {
    $erros = RegrasDeEspaco::errosCruzados([
        'horarios' => [['dia_semana' => 1, 'abre' => '25:00', 'fecha' => '08:00']],
        'bloqueios' => [['inicio' => '2026-02-30', 'fim' => '2026-01-01', 'motivo' => 'Reforma']],
    ]);

    expect($erros)->toBe([]);
});

it('reservasAfetadas conta só as reservas futuras do grupo', function () {
    $dono = User::factory()->create();
    $grupo = Grupo::factory()->for($dono, 'user')->create();
    $outro = Grupo::factory()->for($dono, 'user')->create();
    $espaco = Espaco::factory()->for($dono, 'user')->create();

    Reserva::factory()->create(['user_id' => $dono->id, 'espaco_id' => $espaco->id, 'grupo_id' => $grupo->id, 'data' => '2026-10-10']);
    Reserva::factory()->create(['user_id' => $dono->id, 'espaco_id' => $espaco->id, 'grupo_id' => $grupo->id, 'data' => '2026-10-11']);
    Reserva::factory()->create(['user_id' => $dono->id, 'espaco_id' => $espaco->id, 'grupo_id' => $grupo->id, 'data' => '2026-10-02']);
    Reserva::factory()->create(['user_id' => $dono->id, 'espaco_id' => $espaco->id, 'grupo_id' => $outro->id, 'data' => '2026-10-20']);

    expect(app(GravadorDeGrupo::class)->reservasAfetadas($grupo))->toBe(2);
});

it('motivoParaNaoExcluir de equipamento aponta reserva futura', function () {
    $dono = User::factory()->create();
    $equipamento = Equipamento::factory()->for($dono, 'user')->create();
    $reserva = Reserva::factory()->create([
        'user_id' => $dono->id,
        'espaco_id' => Espaco::factory()->for($dono, 'user')->create()->id,
        'data' => '2026-10-20',
    ]);
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 1]);

    expect(app(GravadorDeEquipamento::class)->motivoParaNaoExcluir($equipamento))
        ->toBe('Há reservas futuras usando este equipamento. Desative em vez de excluir.');
});
