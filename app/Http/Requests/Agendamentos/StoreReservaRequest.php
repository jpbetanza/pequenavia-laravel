<?php

namespace App\Http\Requests\Agendamentos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dados de uma reserva feita pela secretaria, com repetição opcional. A duração máxima e a antecedência do espaço não valem aqui.
 *
 * Espaço, grupo e equipamentos são conferidos como do dono no controller (404 caso contrário).
 */
class StoreReservaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->regrasDaReserva(),
            'repetir' => ['required', Rule::in(['nao', 'semanal', 'mensal'])],
            'ate' => ['required_unless:repetir,nao', 'date_format:Y-m-d', 'after_or_equal:data'],
            'pular' => ['nullable', 'array'],
            'pular.*' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Campos comuns à criação e à edição.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function regrasDaReserva(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:160'],
            'grupo_id' => ['nullable', 'uuid'],
            'pessoas' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'espaco_id' => ['required', 'uuid'],
            'data' => ['required', 'date_format:Y-m-d'],
            'inicio' => ['required', 'date_format:H:i,H:i:s'],
            'fim' => ['required', 'date_format:H:i,H:i:s'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'equipamentos' => ['present', 'array'],
            'equipamentos.*.id' => ['required', 'uuid', 'distinct'],
            'equipamentos.*.quantidade' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $inicio = $this->input('inicio');
            $fim = $this->input('fim');

            if ($validator->errors()->has('inicio') || $validator->errors()->has('fim')) {
                return;
            }

            if (is_string($inicio) && is_string($fim) && substr($fim, 0, 5) <= substr($inicio, 0, 5)) {
                $validator->errors()->add('fim', 'O horário final precisa ser depois do horário inicial.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Informe o título da reserva.',
            'titulo.max' => 'O título pode ter no máximo 160 caracteres.',
            'grupo_id.uuid' => 'O grupo escolhido é inválido.',
            'pessoas.integer' => 'O número de pessoas deve ser um número inteiro.',
            'pessoas.min' => 'O número de pessoas deve ser de pelo menos 1.',
            'pessoas.max' => 'O número de pessoas pode ser no máximo 2000.',
            'espaco_id.required' => 'Escolha o espaço.',
            'espaco_id.uuid' => 'O espaço escolhido é inválido.',
            'data.required' => 'Informe a data.',
            'data.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'inicio.required' => 'Informe o horário de início.',
            'inicio.date_format' => 'O horário de início deve estar no formato HH:MM.',
            'fim.required' => 'Informe o horário de fim.',
            'fim.date_format' => 'O horário de fim deve estar no formato HH:MM.',
            'observacoes.max' => 'As observações podem ter no máximo 2000 caracteres.',
            'equipamentos.present' => 'Informe a lista de equipamentos, mesmo que vazia.',
            'equipamentos.*.id.required' => 'Escolha o equipamento.',
            'equipamentos.*.id.uuid' => 'Um dos equipamentos é inválido.',
            'equipamentos.*.id.distinct' => 'Um equipamento aparece mais de uma vez.',
            'equipamentos.*.quantidade.required' => 'Informe a quantidade.',
            'equipamentos.*.quantidade.integer' => 'A quantidade deve ser um número inteiro.',
            'equipamentos.*.quantidade.min' => 'A quantidade deve ser de pelo menos 1.',
            'equipamentos.*.quantidade.max' => 'A quantidade pode ser no máximo 1000.',
            'repetir.required' => 'Escolha se a reserva repete.',
            'repetir.in' => 'A repetição deve ser: não repete, toda semana ou todo mês.',
            'ate.required_unless' => 'Informe até quando a reserva repete.',
            'ate.date_format' => 'A data final deve estar no formato AAAA-MM-DD.',
            'ate.after_or_equal' => 'A data final não pode ser antes da primeira data.',
            'pular.array' => 'As datas puladas devem ser uma lista.',
            'pular.*.required' => 'Uma das datas puladas está vazia.',
            'pular.*.date_format' => 'Uma das datas puladas está em formato inválido.',
        ];
    }
}
