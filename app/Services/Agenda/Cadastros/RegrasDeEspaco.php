<?php

namespace App\Services\Agenda\Cadastros;

use App\Enums\CorDoEspaco;
use App\Models\Espaco;
use App\Models\User;
use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;

/**
 * Regras de validação de um espaço, usadas pela tela e pelo assistente. O nome é único só entre os espaços do dono.
 */
final class RegrasDeEspaco
{
    /**
     * @return array<string, mixed>
     */
    public static function regras(User $dono, ?Espaco $atual = null): array
    {
        $nomeUnico = Rule::unique('espacos', 'nome')
            ->where('user_id', $dono->id)
            ->ignore($atual?->id);

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
    public static function mensagens(): array
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
     * Regras que cruzam dois campos: o fechamento depois da abertura e o fim do bloqueio depois do início.
     * Valores fora do formato são ignorados, porque a validação de formato já aponta o erro.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, string> campo => mensagem
     */
    public static function errosCruzados(array $dados): array
    {
        $erros = [];

        $horarios = is_array($dados['horarios'] ?? null) ? $dados['horarios'] : [];

        foreach ($horarios as $indice => $horario) {
            $abre = $horario['abre'] ?? null;
            $fecha = $horario['fecha'] ?? null;

            if (! self::horarioValido($abre) || ! self::horarioValido($fecha)) {
                continue;
            }

            if (self::minutos($fecha) <= self::minutos($abre)) {
                $erros["horarios.{$indice}.fecha"] = 'O fechamento precisa ser depois da abertura.';
            }
        }

        $bloqueios = is_array($dados['bloqueios'] ?? null) ? $dados['bloqueios'] : [];

        foreach ($bloqueios as $indice => $bloqueio) {
            $inicio = $bloqueio['inicio'] ?? null;
            $fim = $bloqueio['fim'] ?? null;

            if (! self::dataValida($inicio) || ! self::dataValida($fim)) {
                continue;
            }

            if ($fim < $inicio) {
                $erros["bloqueios.{$indice}.fim"] = 'O fim do bloqueio não pode ser antes do início.';
            }
        }

        return $erros;
    }

    /** Mesma regra de `date_format:H:i`: o horário precisa voltar idêntico ao ser lido. */
    private static function horarioValido(mixed $horario): bool
    {
        return self::casaComFormato($horario, 'H:i');
    }

    /** Mesma regra de `date_format:Y-m-d`. */
    private static function dataValida(mixed $data): bool
    {
        return self::casaComFormato($data, 'Y-m-d');
    }

    private static function casaComFormato(mixed $valor, string $formato): bool
    {
        if (! is_string($valor) || $valor === '') {
            return false;
        }

        $lido = DateTime::createFromFormat('!'.$formato, $valor, new DateTimeZone('UTC'));

        return $lido !== false && $lido->format($formato) === $valor;
    }

    private static function minutos(string $horario): int
    {
        [$horas, $minutos] = explode(':', $horario);

        return ((int) $horas * 60) + (int) $minutos;
    }
}
