<?php

namespace App\Http\Requests\Agendamentos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forma da consulta de disponibilidade. Espaço, equipamentos e reserva são conferidos como do dono no controller.
 */
class DisponibilidadeRequest extends FormRequest
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
            'espaco_id' => ['required', 'uuid'],
            'datas' => ['required', 'array', 'max:52'],
            'datas.*' => ['required', 'date_format:Y-m-d'],
            'inicio' => ['required', 'date_format:H:i,H:i:s'],
            'fim' => ['required', 'date_format:H:i,H:i:s'],
            'equipamentos' => ['present', 'array'],
            'equipamentos.*.id' => ['required', 'uuid', 'distinct'],
            'equipamentos.*.quantidade' => ['required', 'integer', 'min:1'],
            'ignorar_reserva_id' => ['nullable', 'uuid'],
            'pessoas' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'espaco_id.required' => 'Escolha o espaço.',
            'espaco_id.uuid' => 'O espaço escolhido é inválido.',
            'datas.required' => 'Informe pelo menos uma data.',
            'datas.max' => 'Uma busca aceita no máximo 52 datas.',
            'datas.*.required' => 'Informe a data.',
            'datas.*.date_format' => 'Uma das datas está em formato inválido.',
            'inicio.required' => 'Informe o horário de início.',
            'inicio.date_format' => 'O horário de início deve estar no formato HH:MM.',
            'fim.required' => 'Informe o horário de fim.',
            'fim.date_format' => 'O horário de fim deve estar no formato HH:MM.',
            'equipamentos.present' => 'Informe a lista de equipamentos, mesmo que vazia.',
            'equipamentos.*.id.required' => 'Escolha o equipamento.',
            'equipamentos.*.id.uuid' => 'Um dos equipamentos é inválido.',
            'equipamentos.*.id.distinct' => 'Um equipamento aparece mais de uma vez.',
            'equipamentos.*.quantidade.required' => 'Informe a quantidade.',
            'equipamentos.*.quantidade.integer' => 'A quantidade deve ser um número inteiro.',
            'equipamentos.*.quantidade.min' => 'A quantidade deve ser de pelo menos 1.',
            'ignorar_reserva_id.uuid' => 'A reserva informada é inválida.',
            'pessoas.integer' => 'O número de pessoas deve ser um número inteiro.',
            'pessoas.min' => 'O número de pessoas deve ser de pelo menos 1.',
        ];
    }
}
