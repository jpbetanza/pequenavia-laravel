<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCompartilhamentoRequest extends FormRequest
{
    /**
     * A autorização vem antes da validação: quem não vê o modelo recebe 404 mesmo com dados inválidos.
     */
    public function authorize(): Response
    {
        return Gate::inspect('gerenciarCompartilhamentos', $this->route('modelo'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pode_editar' => ['required', 'boolean'],
        ];
    }
}
