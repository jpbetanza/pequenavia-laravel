<?php

namespace App\Http\Requests\Agendamentos;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Marca um equipamento de uma reserva como entregue ou recolhido. Reserva e equipamento precisam ser do dono (404 caso contrário).
 */
class AtualizarEquipamentoDaReservaRequest extends FormRequest
{
    /** Ações aceitas, cada uma vira uma coluna de `reserva_equipamento`. */
    public const ACOES = ['entregue', 'recolhido'];

    public function authorize(): Response
    {
        $reserva = Gate::inspect('update', $this->route('reserva'));

        if ($reserva->denied()) {
            return $reserva;
        }

        return Gate::inspect('update', $this->route('equipamento'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'acao' => ['required', Rule::in(self::ACOES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'acao.required' => 'Informe a ação.',
            'acao.in' => 'A ação precisa ser entregue ou recolhido.',
        ];
    }
}
