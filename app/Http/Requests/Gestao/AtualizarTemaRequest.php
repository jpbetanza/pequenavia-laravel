<?php

namespace App\Http\Requests\Gestao;

use App\Enums\SituacaoDoTema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edição de um tema de pedido. Os campos são opcionais: só o que vier na requisição muda.
 */
class AtualizarTemaRequest extends FormRequest
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
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('temas_de_pedido', 'nome')->ignore($this->route('tema'))],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:300'],
            'situacao' => ['sometimes', 'required', Rule::enum(SituacaoDoTema::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do tema.',
            'nome.max' => 'O nome do tema tem no máximo 80 caracteres.',
            'nome.unique' => 'Já existe um tema com esse nome.',
            'descricao.max' => 'A descrição tem no máximo 300 caracteres.',
            'situacao.enum' => 'Escolha uma situação da lista.',
        ];
    }
}
