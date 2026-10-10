<?php

use App\Enums\StatusSolicitacao;
use App\Models\Solicitacao;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('agendamentos redireciona para o calendário', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('agendamentos'))
        ->assertRedirect(route('agendamentos.calendario'));
});

test('as páginas do módulo respondem para quem está logado', function (string $rota, string $componente) {
    $this->actingAs(User::factory()->create())
        ->get(route($rota))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($componente));
})->with([
    ['agendamentos.calendario', 'agendamentos/calendario'],
    ['agendamentos.solicitacoes.index', 'agendamentos/solicitacoes'],
    ['agendamentos.espacos.index', 'agendamentos/espacos/index'],
    ['agendamentos.equipamentos.index', 'agendamentos/equipamentos/index'],
    ['agendamentos.grupos.index', 'agendamentos/grupos/index'],
]);

test('as páginas do módulo mandam visitantes para o login', function (string $rota) {
    $this->get(route($rota))->assertRedirect(route('login'));
})->with([
    'agendamentos',
    'agendamentos.calendario',
    'agendamentos.solicitacoes.index',
    'agendamentos.espacos.index',
    'agendamentos.equipamentos.index',
    'agendamentos.grupos.index',
]);

test('a contagem de solicitações pendentes é compartilhada com quem está logado', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $page) => $page->where('agenda.solicitacoes_pendentes', 0));
});

test('a contagem de solicitações pendentes conta só as pendentes do usuário logado', function () {
    $user = User::factory()->create();
    Solicitacao::factory()->count(2)->pendente()->for($user)->create();
    Solicitacao::factory()->for($user)->create(['status' => StatusSolicitacao::Aprovada]);
    Solicitacao::factory()->pendente()->create();

    $this->actingAs($user)
        ->get(route('agendamentos.calendario'))
        ->assertInertia(fn (Assert $page) => $page->where('agenda.solicitacoes_pendentes', 2));
});

test('visitantes não recebem a contagem da agenda', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('agenda', null));
});

test('o slug da agenda é criado na primeira vez e depois fica estável', function () {
    $user = User::factory()->create();

    expect($user->agenda_slug)->toBeNull();

    $slug = $user->agendaSlug();

    expect($slug)->toMatch('/^[a-z0-9]{10}$/')
        ->and($user->fresh()->agenda_slug)->toBe($slug)
        ->and($user->fresh()->agendaSlug())->toBe($slug);
});

test('o fuso da agenda é o de São Paulo', function () {
    expect(config('agendamentos.fuso'))->toBe('America/Sao_Paulo');
});
