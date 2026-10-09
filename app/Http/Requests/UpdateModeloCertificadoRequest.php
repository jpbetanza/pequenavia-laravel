<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateModeloCertificadoRequest extends FormRequest
{
    /**
     * A autorização vem antes da validação: quem não vê o modelo recebe 404 mesmo com dados inválidos.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('modelo'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'imagem' => ['nullable', ...StoreModeloCertificadoRequest::regrasDaImagem()],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreModeloCertificadoRequest::mensagensDaImagem() + [
            'title.required' => 'Informe o título do modelo.',
        ];
    }
}
