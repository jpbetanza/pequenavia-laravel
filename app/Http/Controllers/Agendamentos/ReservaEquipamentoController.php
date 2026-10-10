<?php

namespace App\Http\Controllers\Agendamentos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\AtualizarEquipamentoDaReservaRequest;
use App\Models\Equipamento;
use App\Models\Reserva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Marcação de entrega e devolução de um equipamento dentro de uma reserva.
 */
class ReservaEquipamentoController extends Controller
{
    /**
     * Grava o horário da marcação. Quem marca de novo mantém o primeiro horário: a coluna só é preenchida se estiver vazia.
     */
    public function update(AtualizarEquipamentoDaReservaRequest $request, Reserva $reserva, Equipamento $equipamento): RedirectResponse
    {
        $coluna = $request->validated('acao') === 'entregue' ? 'entregue_em' : 'recolhido_em';

        DB::table('reserva_equipamento')
            ->where('reserva_id', $reserva->id)
            ->where('equipamento_id', $equipamento->id)
            ->whereNull($coluna)
            ->update([$coluna => now()]);

        return to_route('agendamentos.equipamentos.index');
    }
}
