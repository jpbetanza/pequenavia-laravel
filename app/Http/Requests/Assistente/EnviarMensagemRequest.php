<?php

namespace App\Http\Requests\Assistente;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Mensagem enviada ao assistente. Conversa de outro usuário responde 404 antes de qualquer validação.
 */
class EnviarMensagemRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('enviar', $this->route('conversa'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'max:'.config('assistente.max_caracteres_mensagem')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required' => 'Escreva uma mensagem.',
            'texto.string' => 'A mensagem precisa ser um texto.',
            'texto.max' => 'A mensagem pode ter até :max caracteres.',
        ];
    }
}
