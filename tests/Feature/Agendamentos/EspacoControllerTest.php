<?php

use App\Enums\CorDoEspaco;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\EspacoHorario;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // "Hoje" é sábado, 10/10/2026, no fuso da agenda. A semana vai de 05/10 a 11/10.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));
});

/**
 * @param  array<string, mixed>  $sobrescritos
 * @return array<string, mixed>
 */
function dadosDoEspaco(array $sobrescritos = []): array
{
    return array_merge([
        'nome' => 'Salão paroquial',
        'capacidade' => 120,
        'localizacao' => 'Térreo, ao lado da secretaria',
        'cor' => 'roxo',
        'recursos_fixos' => ['Telão', 'Cozinha'],
        'aceita_solicitacoes' => true,
        'antecedencia_min_horas' => 24,
        'duracao_max_min' => 240,
        'intervalo_min' => 30,
        'ativo' => true,
        'horarios' => [
            ['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '22:00'],
            ['dia_semana' => 6, 'abre' => '08:00', 'fecha' => '18:00'],
        ],
        'bloqueios' => [
            ['inicio' => '2026-12-20', 'fim' => '2027-01-06', 'motivo' => 'Festas de fim de ano'],
        ],
    ], $sobrescritos);
}

it('redireciona o visitante para o login', function (string $metodo, string $rota, bool $comEspaco) {
    $espaco = Espaco::factory()->create();
    $parametros = $comEspaco ? ['espaco' => $espaco] : [];

    $this->{$metodo}(route($rota, $parametros))->assertRedirect(route('login'));
})->with([
    ['get', 'agendamentos.espacos.index', false],
    ['get', 'agendamentos.espacos.create', false],
    ['post', 'agendamentos.espacos.store', false],
    ['get', 'agendamentos.espacos.edit', true],
    ['put', 'agendamentos.espacos.update', true],
    ['delete', 'agendamentos.espacos.destroy', true],
]);

it('cria o espaço com horários e bloqueios do dono logado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('agendamentos.espacos.store'), dadosDoEspaco())
        ->assertRedirect(route('agendamentos.espacos.index'));

    $espaco = Espaco::query()->where('user_id', $user->id)->sole();

    expect($espaco->nome)->toBe('Salão paroquial')
        ->and($espaco->capacidade)->toBe(120)
        ->and($espaco->cor)->toBe(CorDoEspaco::Roxo)
        ->and($espaco->recursos_fixos)->toBe(['Telão', 'Cozinha'])
        ->and($espaco->intervalo_min)->toBe(30)
        ->and($espaco->duracao_max_min)->toBe(240)
        ->and($espaco->horarios->map(fn (EspacoHorario $horario) => [$horario->dia_semana, $horario->abre, $horario->fecha])->all())
        ->toBe([[1, '08:00:00', '22:00:00'], [6, '08:00:00', '18:00:00']])
        ->and($espaco->bloqueios)->toHaveCount(1)
        ->and($espaco->bloqueios->first()->motivo)->toBe('Festas de fim de ano');
});

it('atualiza o espaço substituindo horários e bloqueios', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create(['nome' => 'Capela']);
    EspacoHorario::factory()->for($espaco, 'espaco')->create(['dia_semana' => 2, 'abre' => '07:00', 'fecha' => '09:00']);
    EspacoBloqueio::factory()->for($espaco, 'espaco')->create(['motivo' => 'Antigo']);

    $this->actingAs($user)
        ->put(route('agendamentos.espacos.update', $espaco), dadosDoEspaco([
            'nome' => 'Capela',
            'horarios' => [['dia_semana' => 0, 'abre' => '07:00', 'fecha' => '12:00']],
            'bloqueios' => [],
        ]))
        ->assertRedirect(route('agendamentos.espacos.index'));

    $espaco->refresh()->load(['horarios', 'bloqueios']);

    expect($espaco->horarios->map(fn (EspacoHorario $horario) => [$horario->dia_semana, $horario->abre])->all())
        ->toBe([[0, '07:00:00']])
        ->and($espaco->bloqueios)->toHaveCount(0);
});

it('aceita o nome que o próprio espaço já usa ao atualizar', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create(['nome' => 'Capela']);

    $this->actingAs($user)
        ->put(route('agendamentos.espacos.update', $espaco), dadosDoEspaco(['nome' => 'Capela', 'horarios' => [], 'bloqueios' => []]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('agendamentos.espacos.index'));
});

it('valida horários, bloqueios, capacidade e nome repetido', function (array $sobrescritos, string $campo) {
    $user = User::factory()->create();
    Espaco::factory()->for($user)->create(['nome' => 'Salão paroquial']);

    $this->actingAs($user)
        ->post(route('agendamentos.espacos.store'), dadosDoEspaco($sobrescritos))
        ->assertSessionHasErrors([$campo]);

    expect(Espaco::query()->where('user_id', $user->id)->count())->toBe(1);
})->with([
    'fechamento igual à abertura' => [['horarios' => [['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '08:00']]], 'horarios.0.fecha'],
    'fechamento antes da abertura' => [['horarios' => [['dia_semana' => 1, 'abre' => '18:00', 'fecha' => '08:00']]], 'horarios.0.fecha'],
    'dia da semana repetido' => [['horarios' => [
        ['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '10:00'],
        ['dia_semana' => 1, 'abre' => '12:00', 'fecha' => '14:00'],
    ]], 'horarios.1.dia_semana'],
    'dia da semana fora de 0 a 6' => [['horarios' => [['dia_semana' => 7, 'abre' => '08:00', 'fecha' => '10:00']]], 'horarios.0.dia_semana'],
    'bloqueio que termina antes de começar' => [['bloqueios' => [['inicio' => '2026-12-20', 'fim' => '2026-12-19', 'motivo' => 'Reforma']]], 'bloqueios.0.fim'],
    'bloqueio sem motivo' => [['bloqueios' => [['inicio' => '2026-12-20', 'fim' => '2026-12-20', 'motivo' => '']]], 'bloqueios.0.motivo'],
    'capacidade zero' => [['capacidade' => 0], 'capacidade'],
    'capacidade acima de 2000' => [['capacidade' => 2001], 'capacidade'],
    'nome repetido pelo mesmo dono' => [['nome' => 'Salão paroquial'], 'nome'],
    'cor fora da paleta' => [['cor' => 'laranja'], 'cor'],
    'recursos fixos nulos' => [['recursos_fixos' => null], 'recursos_fixos'],
    'antecedência negativa' => [['antecedencia_min_horas' => -1], 'antecedencia_min_horas'],
]);

it('aceita o mesmo nome de espaço de outro dono', function () {
    Espaco::factory()->create(['nome' => 'Salão paroquial']);

    $this->actingAs(User::factory()->create())
        ->post(route('agendamentos.espacos.store'), dadosDoEspaco())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('agendamentos.espacos.index'));
});

it('não exclui espaço com reserva futura e pede para desativar', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create();
    Reserva::factory()->for($user)->for($espaco, 'espaco')->create(['data' => '2026-10-20']);

    $this->actingAs($user)
        ->delete(route('agendamentos.espacos.destroy', $espaco))
        ->assertSessionHasErrors(['espaco' => 'Há reservas futuras usando este espaço. Desative em vez de excluir.']);

    expect(Espaco::query()->whereKey($espaco->id)->exists())->toBeTrue();
});

it('não exclui espaço com reserva passada, porque a FK bloquearia a exclusão', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create();
    Reserva::factory()->for($user)->for($espaco, 'espaco')->create(['data' => '2026-10-03']);

    $this->actingAs($user)
        ->delete(route('agendamentos.espacos.destroy', $espaco))
        ->assertSessionHasErrors(['espaco' => 'Há reservas usando este espaço. Desative em vez de excluir.']);

    expect(Espaco::query()->whereKey($espaco->id)->exists())->toBeTrue();
});

it('exclui o espaço sem reservas e leva junto os horários e bloqueios', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->comHorarioComercial()->for($user)->create();
    EspacoBloqueio::factory()->for($espaco, 'espaco')->create();

    $this->actingAs($user)
        ->delete(route('agendamentos.espacos.destroy', $espaco))
        ->assertRedirect(route('agendamentos.espacos.index'))
        ->assertSessionHasNoErrors();

    expect(Espaco::query()->whereKey($espaco->id)->exists())->toBeFalse()
        ->and(EspacoHorario::query()->where('espaco_id', $espaco->id)->exists())->toBeFalse()
        ->and(EspacoBloqueio::query()->where('espaco_id', $espaco->id)->exists())->toBeFalse();
});

it('responde 404 para espaço de outro dono em edição, atualização e exclusão', function (string $metodo, string $rota) {
    $alheio = Espaco::factory()->create(['nome' => 'De outro usuário']);
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->{$metodo}(route($rota, ['espaco' => $alheio]), dadosDoEspaco(['nome' => 'Invadido']))
        ->assertNotFound();

    expect($alheio->refresh()->nome)->toBe('De outro usuário');
})->with([
    ['get', 'agendamentos.espacos.edit'],
    ['put', 'agendamentos.espacos.update'],
    ['delete', 'agendamentos.espacos.destroy'],
]);

it('lista os espaços do dono com selo, horários, recursos e reservas da semana', function () {
    $user = User::factory()->create();

    $ativo = Espaco::factory()->comHorarioComercial()->for($user)->create([
        'nome' => 'Vestíbulo',
        'capacidade' => 120,
        'localizacao' => 'Térreo',
        'recursos_fixos' => ['Telão'],
    ]);
    $bloqueado = Espaco::factory()->for($user)->create(['nome' => 'Sala Santa Rita']);
    EspacoBloqueio::factory()->for($bloqueado, 'espaco')->create([
        'inicio' => '2026-10-01',
        'fim' => '2026-11-30',
        'motivo' => 'Reforma',
    ]);
    $inativo = Espaco::factory()->inativo()->for($user)->create(['nome' => 'Capela']);
    EspacoBloqueio::factory()->for($inativo, 'espaco')->create([
        'inicio' => '2026-10-01',
        'fim' => '2026-11-30',
        'motivo' => 'Reforma',
    ]);
    Espaco::factory()->create(['nome' => 'De outro usuário']);

    // Semana de 05/10 a 11/10: conta 2. A de 04/10 e a de 12/10 ficam de fora.
    foreach (['2026-10-05', '2026-10-11', '2026-10-04', '2026-10-12'] as $data) {
        Reserva::factory()->for($user)->for($ativo, 'espaco')->create(['data' => $data]);
    }

    $this->actingAs($user)
        ->get(route('agendamentos.espacos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agendamentos/espacos/index')
            ->has('espacos', 3)
            ->has('espacos.0', fn (Assert $espaco) => $espaco
                ->where('nome', 'Capela')
                ->where('situacao', 'inativo')
                ->where('bloqueio_atual', null)
                ->etc())
            ->has('espacos.2', fn (Assert $espaco) => $espaco
                ->where('nome', 'Vestíbulo')
                ->where('capacidade', 120)
                ->where('localizacao', 'Térreo')
                ->where('recursos_fixos', ['Telão'])
                ->where('situacao', 'ativo')
                ->where('bloqueio_atual', null)
                ->where('reservas_na_semana', 2)
                ->where('horarios.0', ['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '22:00'])
                ->etc())
            ->has('espacos.1', fn (Assert $espaco) => $espaco
                ->where('nome', 'Sala Santa Rita')
                ->where('situacao', 'bloqueado')
                ->where('bloqueio_atual', ['motivo' => 'Reforma', 'ate' => '30/11'])
                ->where('reservas_na_semana', 0)
                ->etc()));
});

it('mostra o bloqueio só quando cobre hoje, no fuso da agenda', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create(['nome' => 'Auditório']);
    EspacoBloqueio::factory()->for($espaco, 'espaco')->create([
        'inicio' => '2026-10-11',
        'fim' => '2026-10-20',
        'motivo' => 'Festa',
    ]);

    $this->actingAs($user)
        ->get(route('agendamentos.espacos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('espacos.0', fn (Assert $espaco) => $espaco
                ->where('situacao', 'ativo')
                ->where('bloqueio_atual', null)
                ->etc()));
});

it('abre o formulário de criação sem espaço e com os recursos já usados', function () {
    $user = User::factory()->create();
    Espaco::factory()->for($user)->create(['recursos_fixos' => ['Telão', 'Piano']]);
    Espaco::factory()->create(['recursos_fixos' => ['Projetor de outro usuário']]);

    $this->actingAs($user)
        ->get(route('agendamentos.espacos.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agendamentos/espacos/form')
            ->where('espaco', null)
            ->whereContains('recursosSugeridos', 'Telão')
            ->whereContains('recursosSugeridos', 'Piano')
            ->missing('recursosSugeridos.2'));
});

it('abre a edição com horários e bloqueios do espaço', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->comHorarioComercial()->for($user)->create(['nome' => 'Salão paroquial', 'cor' => CorDoEspaco::Dourado]);
    EspacoBloqueio::factory()->for($espaco, 'espaco')->create([
        'inicio' => '2026-12-20',
        'fim' => '2027-01-06',
        'motivo' => 'Festas',
    ]);

    $this->actingAs($user)
        ->get(route('agendamentos.espacos.edit', $espaco))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agendamentos/espacos/form')
            ->has('espaco', fn (Assert $dados) => $dados
                ->where('id', $espaco->id)
                ->where('nome', 'Salão paroquial')
                ->where('cor', 'dourado')
                ->has('horarios', 6)
                ->where('horarios.0', ['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '22:00'])
                ->where('bloqueios.0', ['inicio' => '2026-12-20', 'fim' => '2027-01-06', 'motivo' => 'Festas'])
                ->etc()));
});
