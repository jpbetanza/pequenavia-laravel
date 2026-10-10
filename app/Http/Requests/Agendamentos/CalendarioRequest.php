<?php

namespace App\Http\Requests\Agendamentos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros do calendário: a data em foco e a visão (semana ou dia).
 */
class CalendarioRequest extends FormRequest
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
            'data' => ['nullable', 'date_format:Y-m-d'],
            'visao' => ['nullable', Rule::in(['semana', 'dia'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data.date_format' => 'A data do calendário deve estar no formato AAAA-MM-DD.',
            'visao.in' => 'A visão deve ser semana ou dia.',
        ];
    }
}
