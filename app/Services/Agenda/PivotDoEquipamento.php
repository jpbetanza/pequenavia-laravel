<?php

namespace App\Services\Agenda;

use App\Models\Equipamento;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Lê o pivot de um equipamento carregado por uma reserva ou solicitação (quantidade, entregue_em, recolhido_em),
 * com tipo para a análise estática.
 */
final class PivotDoEquipamento
{
    public static function quantidade(Equipamento $equipamento): int
    {
        return (int) self::pivot($equipamento)->getAttribute('quantidade');
    }

    /** Momento gravado (UTC, texto do banco) da entrega, ou null. */
    public static function entregueEm(Equipamento $equipamento): ?string
    {
        return self::pivot($equipamento)->getAttribute('entregue_em');
    }

    /** Momento gravado (UTC, texto do banco) da devolução, ou null. */
    public static function recolhidoEm(Equipamento $equipamento): ?string
    {
        return self::pivot($equipamento)->getAttribute('recolhido_em');
    }

    private static function pivot(Equipamento $equipamento): Pivot
    {
        /** @var Pivot $pivot */
        $pivot = $equipamento->getRelation('pivot');

        return $pivot;
    }
}
