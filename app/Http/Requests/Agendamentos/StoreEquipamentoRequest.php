<?php

namespace App\Http\Requests\Agendamentos;

use App\Models\User;
use App\Services\Agenda\Cadastros\RegrasDeEquipamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dados de um equipamento. As regras ficam em RegrasDeEquipamento, compartilhadas com o assistente.
 */
class StoreEquipamentoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $dono */
        $dono = $this->user();

        return RegrasDeEquipamento::regras($dono);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RegrasDeEquipamento::mensagens();
    }
}
