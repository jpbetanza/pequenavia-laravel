<?php

namespace App\Http\Requests\Agendamentos;

use App\Enums\EscopoDaReserva;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Edição de uma reserva. A autorização vem antes da validação: quem não é dono recebe 404 mesmo com dados inválidos.
 *
 * A repetição não é editada aqui; o escopo diz se a mudança vale só para esta reserva ou para esta e as próximas da série.
 */
class UpdateReservaRequest extends StoreReservaRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('reserva'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->regrasDaReserva(),
            'escopo' => ['nullable', Rule::enum(EscopoDaReserva::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'escopo.enum' => 'Escolha se a mudança vale só para esta reserva ou para esta e as próximas.',
        ];
    }
}
