<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // "Hoje" é sábado, 10/10/2026, no fuso da agenda.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));
});

/**
 * @param  array<string, mixed>  $sobrescritos
 * @return array<string, mixed>
 */
function dadosDoEquipamento(array $sobrescritos = []): array
{
    return array_merge([
        'nome' => 'Projetor',
        'descricao' => 'com cabo HDMI e controle',
        'quantidade' => 2,
        'local_guarda' => 'Armário da secretaria',
        'manutencao_ate' => null,
    ], $sobrescritos);
}

it('redireciona o visitante para o login', function (string $metodo, string $rota, bool $comEquipamento) {
    $equipamento = Equipamento::factory()->create();
    $parametros = $comEquipamento ? ['equipamento' => $equipamento] : [];

    $this->{$metodo}(route($rota, $parametros))->assertRedirect(route('login'));
})->with([
    ['get', 'agendamentos.equipamentos.index', false],
    ['post', 'agendamentos.equipamentos.store', false],
    ['put', 'agendamentos.equipamentos.update', true],
    ['delete', 'agendamentos.equipamentos.destroy', true],
]);

it('cria o equipamento do dono logado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('agendamentos.equipamentos.store'), dadosDoEquipamento())
        ->assertRedirect(route('agendamentos.equipamentos.index'))
        ->assertSessionHasNoErrors();

    $equipamento = Equipamento::query()->where('user_id', $user->id)->sole();

    expect($equipamento->nome)->toBe('Projetor')
        ->and($equipamento->descricao)->toBe('com cabo HDMI e controle')
        ->and($equipamento->quantidade)->toBe(2)
        ->and($equipamento->local_guarda)->toBe('Armário da secretaria')
        ->and($equipamento->manutencao_ate)->toBeNull();
});

it('aceita campos opcionais vazios e manutenção com data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('agendamentos.equipamentos.store'), dadosDoEquipamento([
            'descricao' => '',
            'local_guarda' => '',
            'manutencao_ate' => '2026-10-31',
        ]))
        ->assertSessionHasNoErrors();

    $equipamento = Equipamento::query()->where('user_id', $user->id)->sole();

    expect($equipamento->descricao)->toBeNull()
        ->and($equipamento->local_guarda)->toBeNull()
        ->and($equipamento->manutencao_ate->toDateString())->toBe('2026-10-31');
});

it('valida quantidade, nome, descrição e data de manutenção', function (array $sobrescritos, string $campo) {
    $this->actingAs(User::factory()->create())
        ->post(route('agendamentos.equipamentos.store'), dadosDoEquipamento($sobrescritos))
        ->assertSessionHasErrors($campo);

    expect(Equipamento::query()->count())->toBe(0);
})->with([
    'quantidade zero' => [['quantidade' => 0], 'quantidade'],
    'quantidade negativa' => [['quantidade' => -1], 'quantidade'],
    'quantidade decimal' => [['quantidade' => 1.5], 'quantidade'],
    'nome vazio' => [['nome' => ''], 'nome'],
    'nome longo' => [['nome' => str_repeat('a', 121)], 'nome'],
    'descrição longa' => [['descricao' => str_repeat('a', 161)], 'descricao'],
    'local longo' => [['local_guarda' => str_repeat('a', 121)], 'local_guarda'],
    'manutenção inválida' => [['manutencao_ate' => '31/10/2026'], 'manutencao_ate'],
]);

it('não aceita nome repetido entre os equipamentos do próprio dono', function () {
    $user = User::factory()->create();
    Equipamento::factory()->for($user)->create(['nome' => 'Projetor']);

    $this->actingAs($user)
        ->post(route('agendamentos.equipamentos.store'), dadosDoEquipamento())
        ->assertSessionHasErrors(['nome' => 'Já existe um equipamento com este nome.']);

    expect(Equipamento::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('aceita o mesmo nome de equipamento de outro dono', function () {
    Equipamento::factory()->create(['nome' => 'Projetor']);

    $this->actingAs(User::factory()->create())
        ->post(route('agendamentos.equipamentos.store'), dadosDoEquipamento())
        ->assertSessionHasNoErrors();
});

it('atualiza o equipamento mantendo o próprio nome e não aceita o nome de um irmão', function () {
    $user = User::factory()->create();
    $projetor = Equipamento::factory()->for($user)->create(['nome' => 'Projetor']);
    Equipamento::factory()->for($user)->create(['nome' => 'Microfone sem fio']);

    $this->actingAs($user)
        ->put(route('agendamentos.equipamentos.update', $projetor), dadosDoEquipamento(['quantidade' => 3]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('agendamentos.equipamentos.index'));

    expect($projetor->refresh()->quantidade)->toBe(3);

    $this->actingAs($user)
        ->put(route('agendamentos.equipamentos.update', $projetor), dadosDoEquipamento(['nome' => 'Microfone sem fio']))
        ->assertSessionHasErrors(['nome' => 'Já existe um equipamento com este nome.']);

    expect($projetor->refresh()->nome)->toBe('Projetor');
});

it('exclui o equipamento sem reservas', function () {
    $user = User::factory()->create();
    $equipamento = Equipamento::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('agendamentos.equipamentos.destroy', $equipamento))
        ->assertRedirect(route('agendamentos.equipamentos.index'))
        ->assertSessionHasNoErrors();

    expect(Equipamento::query()->whereKey($equipamento->id)->exists())->toBeFalse();
});

it('não exclui equipamento com reserva futura e pede para desativar', function () {
    $user = User::factory()->create();
    $equipamento = Equipamento::factory()->for($user)->create();
    $reserva = Reserva::factory()->for($user)->for(Espaco::factory()->for($user))->create(['data' => '2026-10-20']);
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 1]);

    $this->actingAs($user)
        ->delete(route('agendamentos.equipamentos.destroy', $equipamento))
        ->assertSessionHasErrors(['equipamento' => 'Há reservas futuras usando este equipamento. Desative em vez de excluir.']);

    expect(Equipamento::query()->whereKey($equipamento->id)->exists())->toBeTrue();
});

it('não exclui equipamento com reserva passada, porque a FK bloquearia a exclusão no commit', function () {
    $user = User::factory()->create();
    $equipamento = Equipamento::factory()->for($user)->create();
    $reserva = Reserva::factory()->for($user)->for(Espaco::factory()->for($user))->create(['data' => '2026-10-03']);
    $reserva->equipamentos()->attach($equipamento, ['quantidade' => 1]);

    $this->actingAs($user)
        ->delete(route('agendamentos.equipamentos.destroy', $equipamento))
        ->assertSessionHasErrors(['equipamento' => 'Há reservas usando este equipamento. Desative em vez de excluir.']);

    expect(Equipamento::query()->whereKey($equipamento->id)->exists())->toBeTrue();
});

it('responde 404 para equipamento de outro dono em atualização e exclusão', function (string $metodo, string $rota) {
    $alheio = Equipamento::factory()->create(['nome' => 'De outro usuário']);

    $this->actingAs(User::factory()->create())
        ->{$metodo}(route($rota, ['equipamento' => $alheio]), dadosDoEquipamento(['nome' => 'Invadido']))
        ->assertNotFound();

    expect($alheio->refresh()->nome)->toBe('De outro usuário');
})->with([
    ['put', 'agendamentos.equipamentos.update'],
    ['delete', 'agendamentos.equipamentos.destroy'],
]);

it('lista só os equipamentos do dono, por nome, com os dados do formulário', function () {
    $user = User::factory()->create();
    Equipamento::factory()->for($user)->create(['nome' => 'Projetor', 'quantidade' => 2]);
    Equipamento::factory()->for($user)->create(['nome' => 'Caixa de som portátil', 'quantidade' => 1]);
    Equipamento::factory()->create(['nome' => 'Alheio']);

    $this->actingAs($user)
        ->get(route('agendamentos.equipamentos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('agendamentos/equipamentos/index')
            ->has('equipamentos', 2)
            ->where('equipamentos.0.nome', 'Caixa de som portátil')
            ->where('equipamentos.1.nome', 'Projetor')
            ->where('equipamentos.1.quantidade', 2)
            ->where('equipamentos.1.local_guarda', 'Armário da sacristia')
            ->where('equipamentos.1.em_manutencao', false));
});
