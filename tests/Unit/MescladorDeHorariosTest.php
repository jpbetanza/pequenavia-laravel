<?php

use App\Services\Assistente\Cadastros\MescladorDeHorarios;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;

/** Segunda a sexta, 08:00–22:00. */
function horariosDaSemana(): array
{
    return array_map(fn (int $dia) => ['dia_semana' => $dia, 'abre' => '08:00', 'fecha' => '22:00'], [1, 2, 3, 4, 5]);
}

test('abrir sábado mantém segunda a sexta', function () {
    $final = MescladorDeHorarios::mesclar(horariosDaSemana(), [['dia' => 'sábado', 'abre' => '08:00', 'fecha' => '18:00']]);

    expect(array_column($final, 'dia_semana'))->toBe([1, 2, 3, 4, 5, 6])
        ->and($final[5])->toBe(['dia_semana' => 6, 'abre' => '08:00', 'fecha' => '18:00'])
        ->and($final[0])->toBe(['dia_semana' => 1, 'abre' => '08:00', 'fecha' => '22:00']);
});

test('fechado remove o dia', function () {
    $final = MescladorDeHorarios::mesclar(horariosDaSemana(), [['dia' => 'terca', 'fechado' => true]]);

    expect(array_column($final, 'dia_semana'))->toBe([1, 3, 4, 5]);
});

test('mudar só o fechamento de um dia existente mantém a abertura', function () {
    $final = MescladorDeHorarios::mesclar(horariosDaSemana(), [['dia' => 'quinta', 'fecha' => '20:00']]);

    expect($final[3])->toBe(['dia_semana' => 4, 'abre' => '08:00', 'fecha' => '20:00']);
});

test('dia novo sem abertura ou fechamento vira ArgumentoInvalido', function () {
    expect(fn () => MescladorDeHorarios::mesclar(horariosDaSemana(), [['dia' => 'sábado', 'abre' => '08:00']]))
        ->toThrow(ArgumentoInvalido::class, 'Informe abertura e fechamento de sábado.');
    expect(fn () => MescladorDeHorarios::mesclar(horariosDaSemana(), [['dia' => 'sabado']]))
        ->toThrow(ArgumentoInvalido::class, 'Informe abertura e fechamento de sábado.');
});

test('aceita o nome do dia com acento, sem acento, em maiúsculas e abreviado', function () {
    foreach (['Sábado', 'sábado', 'sabado', 'SÁBADO', 'sab'] as $dia) {
        $final = MescladorDeHorarios::mesclar([], [['dia' => $dia, 'abre' => '08:00', 'fecha' => '18:00']]);

        expect($final)->toBe([['dia_semana' => 6, 'abre' => '08:00', 'fecha' => '18:00']]);
    }
});

test('dia desconhecido vira ArgumentoInvalido', function () {
    expect(fn () => MescladorDeHorarios::mesclar([], [['dia' => 'feriado', 'abre' => '08:00', 'fecha' => '18:00']]))
        ->toThrow(ArgumentoInvalido::class, 'Dia da semana desconhecido: feriado.');
});

test('linhas mostram só os dias alterados, com rótulos com acento', function () {
    $atuais = horariosDaSemana();
    $finais = MescladorDeHorarios::mesclar($atuais, [
        ['dia' => 'sábado', 'abre' => '08:00', 'fecha' => '18:00'],
        ['dia' => 'terca', 'fecha' => '20:00'],
    ]);

    expect(MescladorDeHorarios::linhas($atuais, $finais))->toBe([
        ['rotulo' => 'Terça', 'antes' => '08:00–22:00', 'depois' => '08:00–20:00'],
        ['rotulo' => 'Sábado', 'antes' => 'Fechado', 'depois' => '08:00–18:00'],
    ]);
});

test('linhas mostram Fechado depois quando o dia é removido', function () {
    $atuais = horariosDaSemana();
    $finais = MescladorDeHorarios::mesclar($atuais, [['dia' => 'segunda', 'fechado' => true]]);

    expect(MescladorDeHorarios::linhas($atuais, $finais))->toBe([
        ['rotulo' => 'Segunda', 'antes' => '08:00–22:00', 'depois' => 'Fechado'],
    ]);
});
