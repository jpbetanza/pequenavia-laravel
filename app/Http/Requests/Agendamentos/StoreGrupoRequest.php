<?php

namespace App\Http\Requests\Agendamentos;

use App\Models\User;
use App\Services\Agenda\Cadastros\RegrasDeGrupo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dados de um grupo ou pastoral. As regras ficam em RegrasDeGrupo, compartilhadas com o assistente.
 */
class StoreGrupoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $dono */
        $dono = $this->user();

        return RegrasDeGrupo::regras($dono);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RegrasDeGrupo::mensagens();
    }
}
