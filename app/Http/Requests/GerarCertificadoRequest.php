<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GerarCertificadoRequest extends FormRequest
{
    private const LIMITE_DE_CARACTERES = 500;

    /**
     * A autorização vem antes da validação: quem não vê o modelo recebe 404 mesmo com dados inválidos.
     */
    public function authorize(): Response
    {
        return Gate::inspect('gerar', $this->route('modelo'));
    }

    /**
     * Os valores vêm indexados pelo nome do campo, que pode ter pontos. Por isso a regra `valores.*`
     * não serve (ela perde as chaves com ponto) e cada valor é conferido aqui.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'valores' => [
                'bail', 'present', 'array',
                function (string $atributo, mixed $valores, Closure $falhar): void {
                    foreach ($valores as $valor) {
                        if ($valor !== null && ! is_string($valor) && ! is_numeric($valor)) {
                            $falhar('Cada valor deve ser um texto.');

                            return;
                        }

                        if (mb_strlen((string) $valor) > self::LIMITE_DE_CARACTERES) {
                            $falhar('Cada valor pode ter no máximo 500 caracteres.');

                            return;
                        }
                    }
                },
            ],
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
            'valores.present' => 'Informe os valores dos campos.',
            'valores.array' => 'Os valores dos campos devem ser uma lista.',
        ];
    }
}
