<?php

namespace App\Http\Controllers\Agendamentos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\AtualizarEquipamentoDaReservaRequest;
use App\Models\Equipamento;
use App\Models\Reserva;
use App\Services\Agenda\EntregaDeEquipamentos;
use Illuminate\Http\RedirectResponse;

/**
 * Marcação de entrega e devolução de um equipamento dentro de uma reserva.
 */
class ReservaEquipamentoController extends Controller
{
    public function __construct(private readonly EntregaDeEquipamentos $entregas) {}

    /**
     * Grava o horário da marcação. Quem marca de novo mantém o primeiro horário: a coluna só é preenchida se estiver vazia.
     */
    public function update(AtualizarEquipamentoDaReservaRequest $request, Reserva $reserva, Equipamento $equipamento): RedirectResponse
    {
        $this->entregas->marcar($reserva, $equipamento, $request->validated('acao'));

        return redirect()->back(fallback: route('agendamentos.equipamentos.index'));
    }
}
