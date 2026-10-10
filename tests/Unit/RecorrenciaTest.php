<?php

use App\Services\Agenda\Recorrencia;
use Carbon\CarbonImmutable;

/**
 * @param  list<CarbonImmutable>  $datas
 * @return list<string>
 */
function emTextoDatas(array $datas): array
{
    return array_map(fn (CarbonImmutable $data) => $data->toDateString(), $datas);
}

test('sem repetição fica só a data inicial, mesmo com data final', function () {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-12'), 'nao', CarbonImmutable::parse('2026-12-31'));

    expect(emTextoDatas($datas))->toBe(['2026-10-12']);
});

test('repetição sem data final fica só a data inicial', function (string $tipo) {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-12'), $tipo, null);

    expect(emTextoDatas($datas))->toBe(['2026-10-12']);
})->with(['semanal', 'mensal']);

test('semanal repete a cada 7 dias até a data final, inclusive', function () {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-12'), 'semanal', CarbonImmutable::parse('2026-11-02'));

    expect(emTextoDatas($datas))->toBe(['2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02']);
});

test('semanal para na data final quando ela não cai no mesmo dia da semana', function () {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-12'), 'semanal', CarbonImmutable::parse('2026-10-25'));

    expect(emTextoDatas($datas))->toBe(['2026-10-12', '2026-10-19']);
});

test('semanal para em 52 ocorrências', function () {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-12'), 'semanal', CarbonImmutable::parse('2040-01-01'));

    expect($datas)->toHaveCount(52)
        ->and(end($datas)->toDateString())->toBe('2027-10-04');
});

test('mensal repete no mesmo n-ésimo dia da semana (2º sábado)', function () {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-10'), 'mensal', CarbonImmutable::parse('2027-01-31'));

    expect(emTextoDatas($datas))->toBe(['2026-10-10', '2026-11-14', '2026-12-12', '2027-01-09']);
});

test('mensal pula o mês que não tem o 5º dia da semana', function () {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-31'), 'mensal', CarbonImmutable::parse('2027-02-28'));

    expect(emTextoDatas($datas))->toBe(['2026-10-31', '2027-01-30']);
});

test('mensal para em 52 ocorrências', function () {
    $datas = Recorrencia::datas(CarbonImmutable::parse('2026-10-10'), 'mensal', CarbonImmutable::parse('2040-01-01'));

    expect($datas)->toHaveCount(52);
});
