<?php

namespace App\Services\Agenda;

/**
 * Uso de um equipamento em um dia, sem banco. Reservas que só se encostam (fim = início seguinte) não somam.
 */
final class UsoDeEquipamento
{
    /**
     * Pico de unidades em uso ao mesmo tempo. Na mesma hora, as saídas são processadas antes das entradas.
     *
     * @param  list<array{inicio: string, fim: string, quantidade: int}>  $usos
     */
    public static function picoDoDia(array $usos): int
    {
        $eventos = [];

        foreach ($usos as $uso) {
            if ($uso['quantidade'] <= 0 || self::minutos($uso['fim']) <= self::minutos($uso['inicio'])) {
                continue;
            }

            $eventos[] = [self::minutos($uso['inicio']), 1, $uso['quantidade']];
            $eventos[] = [self::minutos($uso['fim']), 0, -$uso['quantidade']];
        }

        usort($eventos, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $emUso = 0;
        $pico = 0;

        foreach ($eventos as [, , $delta]) {
            $emUso += $delta;
            $pico = max($pico, $emUso);
        }

        return $pico;
    }

    /**
     * Unidades em uso no instante informado. O início conta e o fim não.
     *
     * @param  list<array{inicio: string, fim: string, quantidade: int}>  $usos
     */
    public static function emUsoNoHorario(array $usos, string $hora): int
    {
        $instante = self::minutos($hora);

        return array_sum(array_map(
            fn (array $uso): int => self::minutos($uso['inicio']) <= $instante && $instante < self::minutos($uso['fim'])
                ? $uso['quantidade']
                : 0,
            $usos,
        ));
    }

    private static function minutos(string $hora): int
    {
        [$horas, $minutos] = array_map(intval(...), explode(':', $hora));

        return $horas * 60 + $minutos;
    }
}
