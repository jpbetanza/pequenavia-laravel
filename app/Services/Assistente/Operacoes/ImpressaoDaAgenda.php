<?php

namespace App\Services\Assistente\Operacoes;

use App\Models\Equipamento;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Services\Agenda\PivotDoEquipamento;
use Illuminate\Support\Arr;

/**
 * Impressão digital de uma reserva ou solicitação: os campos e os equipamentos pedidos. Se mudar entre a proposta e o
 * clique em Confirmar, a proposta não vale mais (a secretaria aprovaria ou alteraria algo diferente do que viu).
 * A entrega e a devolução de equipamentos não contam: marcar o projetor não invalida um cancelamento.
 */
final class ImpressaoDaAgenda
{
    public static function de(Reserva|Solicitacao $alvo): string
    {
        $estado = Arr::except($alvo->attributesToArray(), ['created_at', 'updated_at']);
        ksort($estado);

        $estado['equipamentos'] = $alvo->equipamentos()->get()
            ->map(fn (Equipamento $equipamento) => [$equipamento->id, PivotDoEquipamento::quantidade($equipamento)])
            ->sortBy(0)
            ->values()
            ->all();

        return hash('sha256', json_encode($estado, JSON_THROW_ON_ERROR));
    }
}
