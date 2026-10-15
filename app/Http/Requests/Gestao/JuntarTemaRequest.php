<?php

namespace App\Http\Requests\Gestao;

use App\Enums\SituacaoDoTema;
use App\Models\TemaDePedido;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Junção de um tema em outro. O destino precisa estar ativo e ser diferente do tema de origem.
 */
class JuntarTemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TemaDePedido $origem */
        $origem = $this->route('tema');

        return [
            'destino_id' => [
                'required',
                'uuid',
                Rule::notIn([$origem->id]),
                Rule::exists('temas_de_pedido', 'id')->where(fn (Builder $consulta) => $consulta
                    ->whereNull('juntado_em_id')
                    ->where('situacao', '<>', SituacaoDoTema::Descartado->value)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destino_id.required' => 'Escolha o tema de destino.',
            'destino_id.uuid' => 'O tema de destino é inválido.',
            'destino_id.not_in' => 'Escolha outro tema: não dá para juntar um tema nele mesmo.',
            'destino_id.exists' => 'O tema de destino não está ativo.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                /** @var TemaDePedido $origem */
                $origem = $this->route('tema');

                if ($origem->juntado_em_id !== null) {
                    $validador->errors()->add('destino_id', 'Este tema já foi juntado a outro.');
                }
            },
        ];
    }
}
