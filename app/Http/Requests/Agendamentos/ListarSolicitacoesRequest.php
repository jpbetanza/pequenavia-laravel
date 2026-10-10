<?php

namespace App\Http\Requests\Agendamentos;

use App\Enums\StatusSolicitacao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtro da lista de solicitações pelo status. Sem filtro, a lista mostra as pendentes.
 */
class ListarSolicitacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(StatusSolicitacao::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'O status deve ser pendente, aprovada ou recusada.',
        ];
    }
}
