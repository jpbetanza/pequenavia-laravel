<?php

namespace App\Http\Requests\Agendamentos;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Recusa de uma solicitação, com motivo opcional. Quem não é dono recebe 404 antes da validação.
 */
class RecusarSolicitacaoRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('solicitacao'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo_recusa' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_recusa.string' => 'O motivo precisa ser um texto.',
            'motivo_recusa.max' => 'O motivo pode ter no máximo 200 caracteres.',
        ];
    }
}
