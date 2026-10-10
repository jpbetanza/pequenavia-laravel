<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // "Hoje" é sábado, 10/10/2026, 10:00 no fuso da agenda. A semana vai de 05/10 (seg.) a 11/10 (dom.).
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));
});

/**
 * Cenário comum: projetor com 2 unidades, caixa com 1, microfone com 4 e um projetor antigo em manutenção.
 *
 * @return array{user: User, projetor: Equipamento, caixa: Equipamento, salao: Espaco}
 */
function cenarioDeEquipamentos(): array
{
    $user = User::factory()->create();
    $salao = Espaco::factory()->for($user)->create(['nome' => 'Salão paroquial']);
    $sala = Espaco::factory()->for($user)->create(['nome' => 'Sala São José']);

    $projetor = Equipamento::factory()->for($user)->create(['nome' => 'Projetor', 'quantidade' => 2]);
    $caixa = Equipamento::factory()->for($user)->create(['nome' => 'Caixa de som portátil', 'quantidade' => 1]);
    Equipamento::factory()->for($user)->create(['nome' => 'Microfone sem fio', 'quantidade' => 4]);
    Equipamento::factory()->for($user)->create([
        'nome' => 'Projetor antigo',
        'quantidade' => 1,
        'manutencao_ate' => '2026-10-31',
    ]);

    // Hoje, 09:00–12:00 no salão, usa 1 projetor e 1 caixa.
    $hoje = Reserva::factory()->for($user)->for($salao, 'espaco')->create([
        'titulo' => 'Encontro de noivos',
        'data' => '2026-10-10',
        'inicio' => '09:00',
        'fim' => '12:00',
    ]);
    $hoje->equipamentos()->attach($projetor, ['quantidade' => 1]);
    $hoje->equipamentos()->attach($caixa, ['quantidade' => 1]);

    // Quarta, 2 unidades do projetor em uso ao mesmo tempo: pico 2.
    $quarta = Reserva::factory()->for($user)->for($sala, 'espaco')->create([
        'titulo' => 'Catequese',
        'data' => '2026-10-07',
        'inicio' => '09:00',
        'fim' => '10:00',
    ]);
    $quarta->equipamentos()->attach($projetor, ['quantidade' => 2]);

    // Quinta, duas reservas sobrepostas do projetor: pico 2.
    $quintaA = Reserva::factory()->for($user)->for($salao, 'espaco')->create([
        'data' => '2026-10-08',
        'inicio' => '10:00',
        'fim' => '12:00',
    ]);
    $quintaA->equipamentos()->attach($projetor, ['quantidade' => 1]);
    $quintaB = Reserva::factory()->for($user)->for($sala, 'espaco')->create([
        'data' => '2026-10-08',
        'inicio' => '11:00',
        'fim' => '13:00',
    ]);
    $quintaB->equipamentos()->attach($projetor, ['quantidade' => 1]);

    // Sexta, duas reservas sequenciais do projetor: pico 1.
    $sextaA = Reserva::factory()->for($user)->for($salao, 'espaco')->create([
        'data' => '2026-10-09',
        'inicio' => '10:00',
        'fim' => '11:00',
    ]);
    $sextaA->equipamentos()->attach($projetor, ['quantidade' => 1]);
    $sextaB = Reserva::factory()->for($user)->for($sala, 'espaco')->create([
        'data' => '2026-10-09',
        'inicio' => '11:00',
        'fim' => '12:00',
    ]);
    $sextaB->equipamentos()->attach($projetor, ['quantidade' => 1]);

    return ['user' => $user, 'projetor' => $projetor, 'caixa' => $caixa, 'salao' => $salao];
}

it('mostra o estado de agora: em uso no espaço, livre ou em manutenção', function () {
    $cenario = cenarioDeEquipamentos();

    $this->actingAs($cenario['user'])
        ->get(route('agendamentos.equipamentos.index'))
        ->assertInertia(fn (Assert $page) => $page
            // Ordem por nome: Caixa, Microfone, Projetor, Projetor antigo.
            ->where('equipamentos.0.agora', [
                'situacao' => 'em_uso',
                'em_uso' => 1,
                'total' => 1,
                'livres' => 0,
                'espacos' => ['Salão paroquial'],
                'ate' => null,
            ])
            ->where('equipamentos.1.agora', [
                'situacao' => 'livre',
                'em_uso' => 0,
                'total' => 4,
                'livres' => 4,
                'espacos' => [],
                'ate' => null,
            ])
            ->where('equipamentos.2.agora', [
                'situacao' => 'em_uso',
                'em_uso' => 1,
                'total' => 2,
                'livres' => 1,
                'espacos' => ['Salão paroquial'],
                'ate' => null,
            ])
            ->where('equipamentos.3.agora', [
                'situacao' => 'manutencao',
                'em_uso' => 0,
                'total' => 1,
                'livres' => 0,
                'espacos' => [],
                'ate' => '31/10',
            ])
            ->where('equipamentos.3.em_manutencao', true));
});

it('mostra no semana um dia por célula, de segunda a domingo, com o pico simultâneo', function () {
    $cenario = cenarioDeEquipamentos();

    $this->actingAs($cenario['user'])
        ->get(route('agendamentos.equipamentos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('equipamentos.2.semana', 7)
            ->where('equipamentos.2.semana.0', ['data' => '2026-10-05', 'pico' => 0, 'quantidade' => 2])
            ->where('equipamentos.2.semana.2', ['data' => '2026-10-07', 'pico' => 2, 'quantidade' => 2])
            ->where('equipamentos.2.semana.3', ['data' => '2026-10-08', 'pico' => 2, 'quantidade' => 2])
            ->where('equipamentos.2.semana.4', ['data' => '2026-10-09', 'pico' => 1, 'quantidade' => 2])
            ->where('equipamentos.2.semana.6', ['data' => '2026-10-11', 'pico' => 0, 'quantidade' => 2]));
});

it('conta no semana só as reservas da semana atual', function () {
    $cenario = cenarioDeEquipamentos();
    $reservaDaProximaSemana = Reserva::factory()->for($cenario['user'])->for($cenario['salao'], 'espaco')->create([
        'data' => '2026-10-19',
        'inicio' => '09:00',
        'fim' => '12:00',
    ]);
    $reservaDaProximaSemana->equipamentos()->attach($cenario['caixa'], ['quantidade' => 1]);

    $this->actingAs($cenario['user'])
        ->get(route('agendamentos.equipamentos.index'))
        ->assertInertia(fn (Assert $page) => $page
            // Caixa: 1 unidade só hoje (sábado); o dia 19/10 fica fora da semana.
            ->where('equipamentos.0.semana.5', ['data' => '2026-10-10', 'pico' => 1, 'quantidade' => 1])
            ->where('equipamentos.0.semana.6', ['data' => '2026-10-11', 'pico' => 0, 'quantidade' => 1]));
});

it('lista no painel de hoje o levar (início − 15 min) e o recolher (fim) das reservas de hoje com equipamentos', function () {
    $cenario = cenarioDeEquipamentos();

    $this->actingAs($cenario['user'])
        ->get(route('agendamentos.equipamentos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hoje.rotulo', 'sábado, 10 de outubro')
            ->has('hoje.movimentos', 2)
            ->where('hoje.movimentos.0.acao', 'levar')
            ->where('hoje.movimentos.0.horario', '08:45')
            ->where('hoje.movimentos.0.titulo', 'Encontro de noivos')
            ->where('hoje.movimentos.0.espaco', 'Salão paroquial')
            ->has('hoje.movimentos.0.itens', 2)
            ->where('hoje.movimentos.0.itens.0.marcado', false)
            ->where('hoje.movimentos.1.acao', 'recolher')
            ->where('hoje.movimentos.1.horario', '12:00'));
});

it('não lista no painel de hoje reservas sem equipamento nem equipamentos de outros dias', function () {
    $user = User::factory()->create();
    Reserva::factory()->for($user)->for(Espaco::factory()->for($user))->create([
        'data' => '2026-10-10',
        'inicio' => '14:00',
        'fim' => '15:00',
    ]);
    $amanha = Reserva::factory()->for($user)->for(Espaco::factory()->for($user))->create(['data' => '2026-10-11']);
    $amanha->equipamentos()->attach(Equipamento::factory()->for($user)->create(), ['quantidade' => 1]);

    $this->actingAs($user)
        ->get(route('agendamentos.equipamentos.index'))
        ->assertInertia(fn (Assert $page) => $page->has('hoje.movimentos', 0));
});
