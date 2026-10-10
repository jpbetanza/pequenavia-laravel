<?php

namespace App\Http\Requests\Agendamentos;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Edição de uma solicitação pela secretaria: espaço, data, horário, pessoas e equipamentos com quantidade.
 *
 * A autorização vem antes da validação: quem não é dono recebe 404 mesmo com dados inválidos.
 * A duração máxima e a antecedência mínima não valem aqui, só para o pedido público.
 */
class UpdateSolicitacaoRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('solicitacao'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'espaco_id' => ['nullable', 'uuid'],
            'data' => ['required', 'date_format:Y-m-d'],
            'inicio' => ['required', 'date_format:H:i,H:i:s'],
            'fim' => ['required', 'date_format:H:i,H:i:s'],
            'pessoas' => ['nullable', 'integer', 'min:1', 'max:2000'],
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

            if ($validator->errors()->hasAny(['inicio', 'fim']) || ! is_string($inicio) || ! is_string($fim)) {
                return;
            }

            if (substr($fim, 0, 5) <= substr($inicio, 0, 5)) {
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
            'espaco_id.uuid' => 'O espaço escolhido é inválido.',
            'data.required' => 'Informe a data.',
            'data.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'inicio.required' => 'Informe o horário de início.',
            'inicio.date_format' => 'O horário de início deve estar no formato HH:MM.',
            'fim.required' => 'Informe o horário de fim.',
            'fim.date_format' => 'O horário de fim deve estar no formato HH:MM.',
            'pessoas.integer' => 'O número de pessoas deve ser um número inteiro.',
            'pessoas.max' => 'O número de pessoas pode ser no máximo 2000.',
            'pessoas.min' => 'O número de pessoas deve ser de pelo menos 1.',
            'equipamentos.present' => 'Informe a lista de equipamentos, mesmo que vazia.',
            'equipamentos.*.id.required' => 'Escolha o equipamento.',
            'equipamentos.*.id.uuid' => 'Um dos equipamentos é inválido.',
            'equipamentos.*.id.distinct' => 'Um equipamento aparece mais de uma vez.',
            'equipamentos.*.quantidade.required' => 'Informe a quantidade.',
            'equipamentos.*.quantidade.integer' => 'A quantidade deve ser um número inteiro.',
            'equipamentos.*.quantidade.min' => 'A quantidade deve ser de pelo menos 1.',
            'equipamentos.*.quantidade.max' => 'A quantidade pode ser no máximo 1000.',
        ];
    }
}
