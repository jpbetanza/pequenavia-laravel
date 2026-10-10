<?php

namespace App\Http\Requests\Agendamentos;

use App\Enums\EscopoDaReserva;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Exclusão de uma reserva. Reserva de outro dono responde 404.
 */
class DestroyReservaRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->route('reserva'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'escopo' => ['nullable', Rule::enum(EscopoDaReserva::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'escopo.enum' => 'Escolha se a exclusão vale só para esta reserva ou para esta e as próximas.',
        ];
    }
}
