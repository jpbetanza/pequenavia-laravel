<?php

namespace App\Http\Requests\Agendamentos;

use App\Enums\CorDoEspaco;
use App\Models\Espaco;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dados de um espaço, com horários e bloqueios. O nome é único só entre os espaços do próprio dono.
 */
class StoreEspacoRequest extends FormRequest
{
    /** Padrão de um horário "HH:MM", como o formulário envia. */
    private const PADRAO_HORARIO = '/^\d{2}:\d{2}$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->regras();
    }

    /**
     * Regras do espaço. Na edição, o próprio espaço fica de fora da checagem de nome repetido.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function regras(?Espaco $espaco = null): array
    {
        $nomeUnico = Rule::unique('espacos', 'nome')
            ->where('user_id', $this->user()->id)
            ->ignore($espaco?->id);

        return [
            'nome' => ['required', 'string', 'max:120', $nomeUnico],
            'capacidade' => ['nullable', 'integer', 'between:1,2000'],
            'localizacao' => ['nullable', 'string', 'max:160'],
            'cor' => ['required', Rule::enum(CorDoEspaco::class)],
            'recursos_fixos' => ['sometimes', 'array'],
            'recursos_fixos.*' => ['string', 'max:80', 'distinct'],
            'aceita_solicitacoes' => ['sometimes', 'boolean'],
            'antecedencia_min_horas' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'duracao_max_min' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'intervalo_min' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'ativo' => ['sometimes', 'boolean'],
            'horarios' => ['nullable', 'array'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:0,6', 'distinct'],
            'horarios.*.abre' => ['required', 'date_format:H:i'],
            'horarios.*.fecha' => ['required', 'date_format:H:i'],
            'bloqueios' => ['nullable', 'array'],
            'bloqueios.*.inicio' => ['required', 'date_format:Y-m-d'],
            'bloqueios.*.fim' => ['required', 'date_format:Y-m-d'],
            'bloqueios.*.motivo' => ['required', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do espaço.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'nome.unique' => 'Já existe um espaço com este nome.',
            'capacidade.integer' => 'A capacidade precisa ser um número inteiro.',
            'capacidade.between' => 'A capacidade precisa estar entre 1 e 2000 pessoas.',
            'localizacao.max' => 'A localização pode ter no máximo 160 caracteres.',
            'cor.required' => 'Escolha uma cor para o espaço.',
            'cor.enum' => 'Escolha uma das cores da paleta.',
            'recursos_fixos.*.max' => 'Cada recurso pode ter no máximo 80 caracteres.',
            'recursos_fixos.*.distinct' => 'Este recurso está repetido.',
            'aceita_solicitacoes.boolean' => 'Informe se o espaço aparece no formulário de solicitação.',
            'antecedencia_min_horas.integer' => 'A antecedência precisa ser um número de horas.',
            'antecedencia_min_horas.min' => 'A antecedência não pode ser negativa.',
            'antecedencia_min_horas.max' => 'A antecedência pode ser no máximo 10080 horas.',
            'duracao_max_min.integer' => 'A duração máxima precisa ser um número de minutos.',
            'duracao_max_min.min' => 'A duração máxima precisa ser de pelo menos 1 minuto.',
            'duracao_max_min.max' => 'A duração máxima pode ser no máximo 10080 minutos.',
            'intervalo_min.integer' => 'O intervalo precisa ser um número de minutos.',
            'intervalo_min.min' => 'O intervalo não pode ser negativo.',
            'intervalo_min.max' => 'O intervalo pode ser no máximo 10080 minutos.',
            'ativo.boolean' => 'Informe se o espaço está ativo.',
            'horarios.*.dia_semana.required' => 'Informe o dia da semana do horário.',
            'horarios.*.dia_semana.between' => 'O dia da semana precisa estar entre domingo e sábado.',
            'horarios.*.dia_semana.distinct' => 'Este dia da semana aparece mais de uma vez.',
            'horarios.*.abre.required' => 'Informe o horário de abertura.',
            'horarios.*.abre.date_format' => 'O horário de abertura precisa estar no formato HH:MM.',
            'horarios.*.fecha.required' => 'Informe o horário de fechamento.',
            'horarios.*.fecha.date_format' => 'O horário de fechamento precisa estar no formato HH:MM.',
            'bloqueios.*.inicio.required' => 'Informe a data de início do bloqueio.',
            'bloqueios.*.inicio.date_format' => 'A data de início do bloqueio é inválida.',
            'bloqueios.*.fim.required' => 'Informe a data de fim do bloqueio.',
            'bloqueios.*.fim.date_format' => 'A data de fim do bloqueio é inválida.',
            'bloqueios.*.motivo.required' => 'Informe o motivo do bloqueio.',
            'bloqueios.*.motivo.max' => 'O motivo pode ter no máximo 160 caracteres.',
        ];
    }

    /**
     * Confere as regras que cruzam dois campos: o fechamento depois da abertura e o fim do bloqueio depois do início.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validador): void {
            foreach ($this->input('horarios', []) as $indice => $horario) {
                $abre = $horario['abre'] ?? null;
                $fecha = $horario['fecha'] ?? null;

                $temErroDeFormato = $validador->errors()->has("horarios.{$indice}.abre")
                    || $validador->errors()->has("horarios.{$indice}.fecha");

                if ($temErroDeFormato || ! $this->horarioValido($abre) || ! $this->horarioValido($fecha)) {
                    continue;
                }

                if ($this->minutos($fecha) <= $this->minutos($abre)) {
                    $validador->errors()->add(
                        "horarios.{$indice}.fecha",
                        'O fechamento precisa ser depois da abertura.',
                    );
                }
            }

            foreach ($this->input('bloqueios', []) as $indice => $bloqueio) {
                $inicio = $bloqueio['inicio'] ?? null;
                $fim = $bloqueio['fim'] ?? null;

                if (! is_string($inicio) || ! is_string($fim) || $inicio === '' || $fim === '') {
                    continue;
                }

                if (! $validador->errors()->has("bloqueios.{$indice}.inicio")
                    && ! $validador->errors()->has("bloqueios.{$indice}.fim")
                    && $fim < $inicio) {
                    $validador->errors()->add(
                        "bloqueios.{$indice}.fim",
                        'O fim do bloqueio não pode ser antes do início.',
                    );
                }
            }
        });
    }

    private function horarioValido(mixed $horario): bool
    {
        return is_string($horario) && preg_match(self::PADRAO_HORARIO, $horario) === 1;
    }

    private function minutos(string $horario): int
    {
        [$horas, $minutos] = explode(':', $horario);

        return ((int) $horas * 60) + (int) $minutos;
    }
}
