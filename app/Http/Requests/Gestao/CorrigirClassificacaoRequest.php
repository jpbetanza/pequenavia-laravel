<?php

namespace App\Http\Requests\Gestao;

use App\Enums\CasoDeUso;
use App\Enums\ResultadoDaConversa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Correção manual de uma classificação. Só os campos enviados entram na correção; nulo em caso ou resultado volta
 * ao valor do JEV, e nulo em tema quer dizer "nenhum tema".
 */
class CorrigirClassificacaoRequest extends FormRequest
{
    private const CAMPOS = ['caso_de_uso', 'resultado', 'tema_id'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'caso_de_uso' => ['sometimes', 'nullable', Rule::enum(CasoDeUso::class)],
            'resultado' => ['sometimes', 'nullable', Rule::enum(ResultadoDaConversa::class)],
            'tema_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('temas_de_pedido', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'caso_de_uso.enum' => 'Escolha um caso de uso da lista.',
            'resultado.enum' => 'Escolha um resultado da lista.',
            'tema_id.uuid' => 'O tema informado é inválido.',
            'tema_id.exists' => 'O tema informado não existe.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                if (! $this->hasAny(self::CAMPOS)) {
                    $validador->errors()->add('caso_de_uso', 'Informe ao menos um campo para corrigir.');
                }
            },
        ];
    }
}
