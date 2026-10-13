<?php

namespace App\Services\Agenda;

use App\Models\Equipamento;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Marca um equipamento de uma reserva como entregue ou recolhido. Usado pela tela de equipamentos e pelas propostas do assistente.
 *
 * Quem marca de novo mantém o primeiro horário: a coluna só é preenchida se estiver vazia.
 */
final class EntregaDeEquipamentos
{
    /** Ações aceitas, cada uma vira uma coluna de `reserva_equipamento`. */
    public const ACOES = ['entregue', 'recolhido'];

    /**
     * @param  'entregue'|'recolhido'  $acao
     * @return bool se a marcação foi gravada agora (false: já estava marcado, ou o equipamento não é da reserva)
     */
    public function marcar(Reserva $reserva, Equipamento $equipamento, string $acao): bool
    {
        $coluna = self::coluna($acao);

        return DB::table('reserva_equipamento')
            ->where('reserva_id', $reserva->id)
            ->where('equipamento_id', $equipamento->id)
            ->whereNull($coluna)
            ->update([$coluna => now()]) > 0;
    }

    /**
     * @param  'entregue'|'recolhido'  $acao
     */
    public static function coluna(string $acao): string
    {
        return $acao === 'entregue' ? 'entregue_em' : 'recolhido_em';
    }
}
