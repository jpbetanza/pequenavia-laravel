<?php

use App\Services\Agenda\UsoDeEquipamento;

test('reservas sequenciais não somam no pico do dia', function () {
    $usos = [
        ['inicio' => '10:00', 'fim' => '11:00', 'quantidade' => 1],
        ['inicio' => '11:00', 'fim' => '12:00', 'quantidade' => 1],
    ];

    expect(UsoDeEquipamento::picoDoDia($usos))->toBe(1);
});

test('reservas sobrepostas somam no pico do dia', function () {
    $usos = [
        ['inicio' => '10:00', 'fim' => '12:00', 'quantidade' => 1],
        ['inicio' => '11:00', 'fim' => '13:00', 'quantidade' => 1],
    ];

    expect(UsoDeEquipamento::picoDoDia($usos))->toBe(2);
});

test('o pico soma as quantidades e pega o ponto de máximo, não o total do dia', function () {
    $usos = [
        ['inicio' => '09:00', 'fim' => '12:00', 'quantidade' => 2],
        ['inicio' => '11:00', 'fim' => '13:00', 'quantidade' => 1],
        ['inicio' => '14:00', 'fim' => '15:00', 'quantidade' => 2],
    ];

    expect(UsoDeEquipamento::picoDoDia($usos))->toBe(3);
});

test('na borda 10–12 contra 12–13 não há soma', function () {
    $usos = [
        ['inicio' => '10:00', 'fim' => '12:00', 'quantidade' => 2],
        ['inicio' => '12:00', 'fim' => '13:00', 'quantidade' => 2],
    ];

    expect(UsoDeEquipamento::picoDoDia($usos))->toBe(2);
});

test('aceita horários com segundos vindos do banco', function () {
    $usos = [
        ['inicio' => '10:00:00', 'fim' => '12:00:00', 'quantidade' => 1],
        ['inicio' => '11:00:00', 'fim' => '13:00:00', 'quantidade' => 1],
    ];

    expect(UsoDeEquipamento::picoDoDia($usos))->toBe(2);
});

test('sem reservas o pico é zero', function () {
    expect(UsoDeEquipamento::picoDoDia([]))->toBe(0);
});

test('em uso no horário conta quem cobre o instante, com início incluso e fim excluso', function () {
    $usos = [
        ['inicio' => '10:00', 'fim' => '12:00', 'quantidade' => 1],
        ['inicio' => '12:00', 'fim' => '13:00', 'quantidade' => 3],
    ];

    expect(UsoDeEquipamento::emUsoNoHorario($usos, '10:00'))->toBe(1)
        ->and(UsoDeEquipamento::emUsoNoHorario($usos, '11:59'))->toBe(1)
        ->and(UsoDeEquipamento::emUsoNoHorario($usos, '12:00'))->toBe(3)
        ->and(UsoDeEquipamento::emUsoNoHorario($usos, '09:59'))->toBe(0)
        ->and(UsoDeEquipamento::emUsoNoHorario($usos, '13:00'))->toBe(0);
});
