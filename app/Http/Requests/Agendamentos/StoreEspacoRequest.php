<?php

namespace App\Http\Requests\Agendamentos;

use App\Models\User;
use App\Services\Agenda\Cadastros\RegrasDeEspaco;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Dados de um espaço, com horários e bloqueios. As regras ficam em RegrasDeEspaco, compartilhadas com o assistente.
 */
class StoreEspacoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $dono */
        $dono = $this->user();

        return RegrasDeEspaco::regras($dono);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RegrasDeEspaco::mensagens();
    }

    /**
     * Confere as regras que cruzam dois campos, com as mesmas mensagens do assistente.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validador): void {
            foreach (RegrasDeEspaco::errosCruzados($this->all()) as $campo => $mensagem) {
                $validador->errors()->add($campo, $mensagem);
            }
        });
    }
}
