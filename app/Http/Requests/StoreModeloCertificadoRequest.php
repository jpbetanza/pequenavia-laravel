<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreModeloCertificadoRequest extends FormRequest
{
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
            'imagem' => ['required', ...self::regrasDaImagem()],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::mensagensDaImagem() + [
            'title.required' => 'Informe o título do modelo.',
            'imagem.required' => 'Envie a imagem do modelo.',
        ];
    }

    /**
     * Imagem do modelo: PNG, JPG ou WEBP, até 50 MB e até 10000 × 10000 pixels.
     *
     * @return list<string>
     */
    public static function regrasDaImagem(): array
    {
        return ['file', 'mimes:png,jpg,jpeg,webp', 'max:51200', 'dimensions:max_width=10000,max_height=10000'];
    }

    /**
     * @return array<string, string>
     */
    public static function mensagensDaImagem(): array
    {
        return [
            'imagem.max' => 'A imagem pode ter no máximo 50 MB.',
            'imagem.mimes' => 'Envie uma imagem PNG, JPG ou WEBP.',
            'imagem.dimensions' => 'A imagem pode ter no máximo 10000 × 10000 pixels.',
        ];
    }
}
