<?php

namespace App\Services\Agenda;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Calcula as datas de uma série de reservas. Sem repetição, o limite é a própria data inicial.
 */
final class Recorrencia
{
    /** Máximo de ocorrências de uma série. */
    public const LIMITE = 52;

    /**
     * A data inicial é sempre a primeira ocorrência. Na mensal, o mês que não tem o n-ésimo dia da semana é pulado.
     *
     * @param  'nao'|'semanal'|'mensal'  $tipo
     * @return list<CarbonImmutable>
     */
    public static function datas(CarbonImmutable $inicio, string $tipo, ?CarbonImmutable $ate): array
    {
        if (! in_array($tipo, ['nao', 'semanal', 'mensal'], true)) {
            throw new InvalidArgumentException("Tipo de repetição desconhecido: {$tipo}.");
        }

        if ($tipo === 'nao' || $ate === null) {
            return [$inicio];
        }

        return $tipo === 'semanal'
            ? self::semanais($inicio, $ate)
            : self::mensais($inicio, $ate);
    }

    /**
     * @return list<CarbonImmutable>
     */
    private static function semanais(CarbonImmutable $inicio, CarbonImmutable $ate): array
    {
        $datas = [$inicio];

        for ($data = $inicio->addWeek(); count($datas) < self::LIMITE && $data->lessThanOrEqualTo($ate->endOfDay()); $data = $data->addWeek()) {
            $datas[] = $data;
        }

        return $datas;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private static function mensais(CarbonImmutable $inicio, CarbonImmutable $ate): array
    {
        $datas = [$inicio];
        $posicaoNoMes = intdiv($inicio->day - 1, 7) + 1;

        for ($mes = $inicio->startOfMonth()->addMonth(); count($datas) < self::LIMITE && $mes->lessThanOrEqualTo($ate); $mes = $mes->addMonth()) {
            $ocorrencia = self::nEsimoDiaDaSemana($inicio, $mes, $posicaoNoMes);

            if ($ocorrencia !== null && $ocorrencia->lessThanOrEqualTo($ate->endOfDay())) {
                $datas[] = $ocorrencia;
            }
        }

        return $datas;
    }

    /**
     * Devolve o n-ésimo dia da semana de $inicio dentro do mês de $mes, ou null se o mês não tem esse dia.
     */
    private static function nEsimoDiaDaSemana(CarbonImmutable $inicio, CarbonImmutable $mes, int $posicao): ?CarbonImmutable
    {
        $primeiroDoMes = $mes->startOfMonth();
        $deslocamento = ($inicio->dayOfWeek - $primeiroDoMes->dayOfWeek + 7) % 7 + ($posicao - 1) * 7;
        $dia = $deslocamento + 1;

        if ($dia > $primeiroDoMes->daysInMonth) {
            return null;
        }

        return $inicio->setDate($primeiroDoMes->year, $primeiroDoMes->month, $dia);
    }
}
