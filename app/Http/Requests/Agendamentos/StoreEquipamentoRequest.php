<?php

namespace App\Http\Requests\Agendamentos;

use App\Models\Equipamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados de um equipamento. O nome é único só entre os equipamentos do próprio dono.
 */
class StoreEquipamentoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->regras();
    }

    /**
     * Regras do equipamento. Na edição, o próprio equipamento fica de fora da checagem de nome repetido.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function regras(?Equipamento $equipamento = null): array
    {
        $nomeUnico = Rule::unique('equipamentos', 'nome')
            ->where('user_id', $this->user()->id)
            ->ignore($equipamento?->id);

        return [
            'nome' => ['required', 'string', 'max:120', $nomeUnico],
            'descricao' => ['nullable', 'string', 'max:160'],
            'quantidade' => ['required', 'integer', 'min:1'],
            'local_guarda' => ['nullable', 'string', 'max:120'],
            'manutencao_ate' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do equipamento.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'nome.unique' => 'Já existe um equipamento com este nome.',
            'descricao.max' => 'A descrição pode ter no máximo 160 caracteres.',
            'quantidade.required' => 'Informe quantas unidades existem.',
            'quantidade.integer' => 'A quantidade precisa ser um número inteiro.',
            'quantidade.min' => 'Informe pelo menos uma unidade.',
            'local_guarda.max' => 'O local de guarda pode ter no máximo 120 caracteres.',
            'manutencao_ate.date_format' => 'Informe a data de manutenção no formato dia/mês/ano.',
        ];
    }
}
