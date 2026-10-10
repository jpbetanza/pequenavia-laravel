<?php

namespace App\Http\Requests\Agendamentos;

use App\Models\Grupo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados de um grupo ou pastoral. O nome é único só entre os grupos do próprio dono.
 */
class StoreGrupoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->regras();
    }

    /**
     * Regras do grupo. Na edição, o próprio grupo fica de fora da checagem de nome repetido.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function regras(?Grupo $grupo = null): array
    {
        $nomeUnico = Rule::unique('grupos', 'nome')
            ->where('user_id', $this->user()->id)
            ->ignore($grupo?->id);

        return [
            'nome' => ['required', 'string', 'max:120', $nomeUnico],
            'coordenacao' => ['nullable', 'string', 'max:120'],
            'contato' => ['nullable', 'string', 'max:120'],
            'observacao' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do grupo.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'nome.unique' => 'Já existe um grupo com este nome.',
            'coordenacao.max' => 'A coordenação pode ter no máximo 120 caracteres.',
            'contato.max' => 'O contato pode ter no máximo 120 caracteres.',
            'observacao.string' => 'A observação precisa ser um texto.',
            'ativo.boolean' => 'Informe se o grupo está ativo.',
        ];
    }
}
