<?php

use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // "Hoje" é sábado, 10/10/2026, no fuso da agenda.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));
});

it('redireciona o visitante para o login', function (string $metodo, string $rota) {
    $grupo = Grupo::factory()->create();

    $this->{$metodo}(route($rota, $metodo === 'post' ? [] : ['grupo' => $grupo]))
        ->assertRedirect(route('login'));
})->with([
    ['get', 'agendamentos.grupos.index'],
    ['post', 'agendamentos.grupos.store'],
    ['put', 'agendamentos.grupos.update'],
    ['delete', 'agendamentos.grupos.destroy'],
]);

it('lista os grupos do dono por nome, com coordenação, contato e observação', function () {
    $user = User::factory()->create();
    Grupo::factory()->for($user)->create([
        'nome' => 'Pastoral Familiar',
        'coordenacao' => 'Marcos e Helena',
        'contato' => 'familiar@paroquia.org.br',
        'observacao' => 'Encontro mensal',
    ]);
    Grupo::factory()->for($user)->create(['nome' => 'Catequese', 'ativo' => false]);
    Grupo::factory()->create(['nome' => 'De outro usuário']);

    $this->actingAs($user)
        ->get(route('agendamentos.grupos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agendamentos/grupos/index')
            ->has('grupos', 2)
            ->has('grupos.0', fn (Assert $grupo) => $grupo
                ->where('nome', 'Catequese')
                ->where('ativo', false)
                ->where('reunioes', [])
                ->etc())
            ->has('grupos.1', fn (Assert $grupo) => $grupo
                ->where('nome', 'Pastoral Familiar')
                ->where('coordenacao', 'Marcos e Helena')
                ->where('contato', 'familiar@paroquia.org.br')
                ->where('observacao', 'Encontro mensal')
                ->where('ativo', true)
                ->where('reunioes', [])
                ->etc()));
});

it('mostra até duas reuniões fixas por grupo, uma por série e só das futuras', function () {
    $user = User::factory()->create();
    $grupo = Grupo::factory()->for($user)->create(['nome' => 'Catequese']);
    $catequese = Espaco::factory()->for($user)->create(['nome' => 'Sala de catequese']);
    $salao = Espaco::factory()->for($user)->create(['nome' => 'Salão paroquial']);

    $serieSemanal = (string) Str::uuid();
    foreach (['2026-10-12', '2026-10-19', '2026-10-26'] as $data) {
        Reserva::factory()->for($user)->for($catequese, 'espaco')->create([
            'grupo_id' => $grupo->id, 'serie_id' => $serieSemanal, 'data' => $data, 'inicio' => '19:00', 'fim' => '20:30',
        ]);
    }

    // Série que só tem ocorrências passadas: não aparece.
    Reserva::factory()->for($user)->for($catequese, 'espaco')->create([
        'grupo_id' => $grupo->id, 'serie_id' => (string) Str::uuid(), 'data' => '2026-10-03', 'inicio' => '10:00', 'fim' => '11:00',
    ]);

    // Reserva avulsa, sem série: não aparece.
    Reserva::factory()->for($user)->for($catequese, 'espaco')->create([
        'grupo_id' => $grupo->id, 'serie_id' => null, 'data' => '2026-10-20', 'inicio' => '08:00', 'fim' => '09:00',
    ]);

    Reserva::factory()->for($user)->for($salao, 'espaco')->create([
        'grupo_id' => $grupo->id, 'serie_id' => (string) Str::uuid(), 'data' => '2026-10-14', 'inicio' => '08:00', 'fim' => '09:00',
    ]);

    Reserva::factory()->for($user)->for($catequese, 'espaco')->create([
        'grupo_id' => $grupo->id, 'serie_id' => (string) Str::uuid(), 'data' => '2026-10-17', 'inicio' => '15:00', 'fim' => '16:00',
    ]);

    $this->actingAs($user)
        ->get(route('agendamentos.grupos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('grupos.0', fn (Assert $grupo) => $grupo
                ->where('reunioes', [
                    'Seg 19:00 · Sala de catequese',
                    'Qua 08:00 · Salão paroquial',
                ])
                ->etc()));
});

it('carrega as reuniões fixas em uma consulta só, sem repetir a busca por grupo', function () {
    $user = User::factory()->create();
    $primeiro = Grupo::factory()->for($user)->create();
    $espaco = Espaco::factory()->for($user)->create();

    $contarConsultas = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('agendamentos.grupos.index'))->assertOk();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    Reserva::factory()->for($user)->for($espaco, 'espaco')->create([
        'grupo_id' => $primeiro->id, 'serie_id' => (string) Str::uuid(), 'data' => '2026-10-12',
    ]);
    $comUmGrupo = $contarConsultas();

    foreach (range(1, 3) as $indice) {
        $grupo = Grupo::factory()->for($user)->create();
        Reserva::factory()->for($user)->for($espaco, 'espaco')->create([
            'grupo_id' => $grupo->id, 'serie_id' => (string) Str::uuid(), 'data' => '2026-10-12',
        ]);
    }
    $comQuatroGrupos = $contarConsultas();

    expect($comQuatroGrupos)->toBe($comUmGrupo);
});

it('cria um grupo do dono com os dados informados', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('agendamentos.grupos.store'), [
            'nome' => 'Coral Santa Cecília',
            'coordenacao' => 'Rafael',
            'contato' => '(11) 9 0000-0000',
            'observacao' => 'Ensaios às quartas',
            'ativo' => true,
        ])
        ->assertRedirect(route('agendamentos.grupos.index'));

    $this->assertDatabaseHas('grupos', [
        'user_id' => $user->id,
        'nome' => 'Coral Santa Cecília',
        'coordenacao' => 'Rafael',
        'contato' => '(11) 9 0000-0000',
        'observacao' => 'Ensaios às quartas',
        'ativo' => true,
    ]);
});

it('guarda o grupo como inativo quando o switch está desligado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('agendamentos.grupos.store'), [
        'nome' => 'Conselho pastoral',
        'ativo' => false,
    ]);

    $this->assertDatabaseHas('grupos', ['user_id' => $user->id, 'nome' => 'Conselho pastoral', 'ativo' => false]);
});

it('valida o nome e o tamanho dos campos com mensagens em português', function (array $dados, string $campo, string $mensagem) {
    $this->actingAs(User::factory()->create())
        ->post(route('agendamentos.grupos.store'), $dados)
        ->assertSessionHasErrors([$campo => $mensagem]);

    $this->assertDatabaseCount('grupos', 0);
})->with([
    'nome vazio' => [['nome' => ''], 'nome', 'Informe o nome do grupo.'],
    'nome longo' => [['nome' => str_repeat('a', 121)], 'nome', 'O nome pode ter no máximo 120 caracteres.'],
    'coordenação longa' => [['nome' => 'Grupo', 'coordenacao' => str_repeat('a', 121)], 'coordenacao', 'A coordenação pode ter no máximo 120 caracteres.'],
    'contato longo' => [['nome' => 'Grupo', 'contato' => str_repeat('a', 121)], 'contato', 'O contato pode ter no máximo 120 caracteres.'],
    'ativo inválido' => [['nome' => 'Grupo', 'ativo' => 'talvez'], 'ativo', 'Informe se o grupo está ativo.'],
]);

it('não aceita dois grupos com o mesmo nome para o mesmo dono', function () {
    $user = User::factory()->create();
    Grupo::factory()->for($user)->create(['nome' => 'Catequese']);

    $this->actingAs($user)
        ->post(route('agendamentos.grupos.store'), ['nome' => 'Catequese'])
        ->assertSessionHasErrors(['nome' => 'Já existe um grupo com este nome.']);

    expect(Grupo::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('aceita o mesmo nome para donos diferentes', function () {
    Grupo::factory()->create(['nome' => 'Catequese']);

    $this->actingAs(User::factory()->create())
        ->post(route('agendamentos.grupos.store'), ['nome' => 'Catequese'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('agendamentos.grupos.index'));
});

it('atualiza o próprio grupo e aceita manter o mesmo nome dele', function () {
    $user = User::factory()->create();
    $grupo = Grupo::factory()->for($user)->create(['nome' => 'Catequese', 'contato' => null]);

    $this->actingAs($user)
        ->put(route('agendamentos.grupos.update', ['grupo' => $grupo]), [
            'nome' => 'Catequese',
            'coordenacao' => 'Irmã Lúcia',
            'contato' => '(11) 9 0000-0000',
            'observacao' => null,
            'ativo' => false,
        ])
        ->assertRedirect(route('agendamentos.grupos.index'))
        ->assertSessionHasNoErrors();

    expect($grupo->fresh())
        ->coordenacao->toBe('Irmã Lúcia')
        ->contato->toBe('(11) 9 0000-0000')
        ->ativo->toBeFalse();
});

it('não deixa renomear o grupo para o nome de outro grupo do mesmo dono', function () {
    $user = User::factory()->create();
    Grupo::factory()->for($user)->create(['nome' => 'Catequese']);
    $jovens = Grupo::factory()->for($user)->create(['nome' => 'Grupo Jovem']);

    $this->actingAs($user)
        ->put(route('agendamentos.grupos.update', ['grupo' => $jovens]), ['nome' => 'Catequese', 'ativo' => true])
        ->assertSessionHasErrors(['nome' => 'Já existe um grupo com este nome.']);

    expect($jovens->fresh()->nome)->toBe('Grupo Jovem');
});

it('responde 404 ao editar grupo de outro usuário, mesmo com dados inválidos', function (array $dados) {
    $alheio = Grupo::factory()->create(['nome' => 'Catequese']);

    $this->actingAs(User::factory()->create())
        ->put(route('agendamentos.grupos.update', ['grupo' => $alheio]), $dados)
        ->assertNotFound();

    expect($alheio->fresh()->nome)->toBe('Catequese');
})->with([
    'dados válidos' => [['nome' => 'Outro nome', 'ativo' => true]],
    'dados inválidos' => [['nome' => '']],
]);

it('exclui o próprio grupo e deixa as reservas dele sem grupo', function () {
    $user = User::factory()->create();
    $grupo = Grupo::factory()->for($user)->create();
    $espaco = Espaco::factory()->for($user)->create();
    $reserva = Reserva::factory()->for($user)->for($espaco, 'espaco')->create(['grupo_id' => $grupo->id]);

    $this->actingAs($user)
        ->delete(route('agendamentos.grupos.destroy', ['grupo' => $grupo]))
        ->assertRedirect(route('agendamentos.grupos.index'));

    $this->assertDatabaseMissing('grupos', ['id' => $grupo->id]);
    expect($reserva->fresh())
        ->not->toBeNull()
        ->grupo_id->toBeNull();
});

it('responde 404 ao excluir grupo de outro usuário', function () {
    $alheio = Grupo::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('agendamentos.grupos.destroy', ['grupo' => $alheio]))
        ->assertNotFound();

    $this->assertDatabaseHas('grupos', ['id' => $alheio->id]);
});
