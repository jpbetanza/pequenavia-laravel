<?php

namespace App\Services\Gestao;

use Carbon\CarbonImmutable;

/**
 * Janela de análise do painel: os últimos N dias, incluindo hoje, no fuso da gestão. Os intervalos são
 * semiabertos: início incluído, fim excluído (começo do dia seguinte).
 */
final readonly class PeriodoDoPainel
{
    public function __construct(
        public int $dias,
        public CarbonImmutable $inicio,
        public CarbonImmutable $fim,
        public CarbonImmutable $anteriorInicio,
        public CarbonImmutable $anteriorFim,
    ) {}
}
