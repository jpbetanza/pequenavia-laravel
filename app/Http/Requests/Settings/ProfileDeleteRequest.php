<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * O email digitado é comparado em minúsculas e sem espaços nas pontas.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('confirmacao')) {
            $this->merge(['confirmacao' => Str::lower(trim((string) $this->input('confirmacao')))]);
        }
    }

    /**
     * Quem tem senha confirma com ela. Quem só entra pelo Google não tem senha e confirma digitando o
     * próprio email. O ramo depende da conta, nunca do que veio na requisição.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user()->tem_senha) {
            return [
                'password' => $this->currentPasswordRules(),
            ];
        }

        return [
            'confirmacao' => ['required', Rule::in([$this->user()->email])],
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
            'confirmacao.required' => 'Digite o seu email para confirmar.',
            'confirmacao.in' => 'Digite o seu email para confirmar.',
        ];
    }
}
