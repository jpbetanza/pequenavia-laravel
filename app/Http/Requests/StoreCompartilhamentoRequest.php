<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCompartilhamentoRequest extends FormRequest
{
    /**
     * A autorização vem antes da validação: quem não vê o modelo recebe 404 mesmo com dados inválidos.
     */
    public function authorize(): Response
    {
        return Gate::inspect('gerenciarCompartilhamentos', $this->route('modelo'));
    }

    /**
     * O email é guardado e comparado em minúsculas, sem espaços nas pontas.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $modelo = $this->route('modelo');

        return [
            'email' => [
                'required', 'email', 'max:255',
                Rule::notIn([$modelo->user->email]),
                Rule::unique('compartilhamentos_modelo', 'email')->where('modelo_id', $modelo->id),
            ],
            'pode_editar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o email.',
            'email.email' => 'Informe um email válido.',
            'email.not_in' => 'Você não precisa compartilhar um modelo com você mesmo.',
            'email.unique' => 'Este modelo já está compartilhado com este email.',
        ];
    }
}
