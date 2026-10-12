<?php

namespace App\Services\Assistente\Cadastros;

use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use Illuminate\Support\Str;

/**
 * Aplica mudanças por dia sobre os horários semanais de um espaço. Puro, sem banco: quem chama grava o resultado.
 */
final class MescladorDeHorarios
{
    /** Dias em pt-BR (sem acento e minúsculos aceitos) → 0=domingo … 6=sábado. */
    public const DIAS = ['domingo' => 0, 'segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6];

    /** Ordem de exibição nas linhas do cartão: segunda a sábado e domingo no fim. */
    private const ORDEM_DE_EXIBICAO = [1, 2, 3, 4, 5, 6, 0];

    private const ROTULOS = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];

    /**
     * @param  list<array{dia_semana: int, abre: string, fecha: string}>  $atuais
     * @param  list<array{dia: string, abre?: string, fecha?: string, fechado?: bool}>  $mudancas
     * @return list<array{dia_semana: int, abre: string, fecha: string}> ordenado por dia_semana
     *
     * @throws ArgumentoInvalido dia desconhecido; abre/fecha ausentes sem `fechado`
     */
    public static function mesclar(array $atuais, array $mudancas): array
    {
        $porDia = self::porDia($atuais);

        foreach ($mudancas as $mudanca) {
            $dia = self::diaSemana((string) ($mudanca['dia'] ?? ''));

            if (($mudanca['fechado'] ?? false) === true) {
                unset($porDia[$dia]);

                continue;
            }

            $abre = $mudanca['abre'] ?? $porDia[$dia]['abre'] ?? null;
            $fecha = $mudanca['fecha'] ?? $porDia[$dia]['fecha'] ?? null;

            if ($abre === null || $fecha === null) {
                throw new ArgumentoInvalido('Informe abertura e fechamento de '.mb_strtolower(self::ROTULOS[$dia]).'.');
            }

            $porDia[$dia] = ['dia_semana' => $dia, 'abre' => $abre, 'fecha' => $fecha];
        }

        ksort($porDia);

        return array_values($porDia);
    }

    /**
     * Linhas antes → depois só dos dias que mudaram, na ordem de exibição (segunda a domingo).
     *
     * @param  list<array{dia_semana: int, abre: string, fecha: string}>  $atuais
     * @param  list<array{dia_semana: int, abre: string, fecha: string}>  $finais
     * @return list<array{rotulo: string, antes: string, depois: string}>
     */
    public static function linhas(array $atuais, array $finais): array
    {
        $antes = self::porDia($atuais);
        $depois = self::porDia($finais);
        $linhas = [];

        foreach (self::ORDEM_DE_EXIBICAO as $dia) {
            $textoAntes = self::faixa($antes[$dia] ?? null);
            $textoDepois = self::faixa($depois[$dia] ?? null);

            if ($textoAntes !== $textoDepois) {
                $linhas[] = ['rotulo' => self::ROTULOS[$dia], 'antes' => $textoAntes, 'depois' => $textoDepois];
            }
        }

        return $linhas;
    }

    /**
     * Resumo dos horários de um espaço novo, ex.: "Segunda 08:00–22:00, Terça 08:00–22:00". Sem dias: "Fechado".
     *
     * @param  list<array{dia_semana: int, abre: string, fecha: string}>  $horarios
     */
    public static function resumo(array $horarios): string
    {
        if ($horarios === []) {
            return 'Fechado';
        }

        $porDia = self::porDia($horarios);

        return collect(self::ORDEM_DE_EXIBICAO)
            ->filter(fn (int $dia) => isset($porDia[$dia]))
            ->map(fn (int $dia) => self::ROTULOS[$dia].' '.self::faixa($porDia[$dia]))
            ->implode(', ');
    }

    /**
     * @param  list<array{dia_semana: int, abre: string, fecha: string}>  $horarios
     * @return array<int, array{dia_semana: int, abre: string, fecha: string}>
     */
    private static function porDia(array $horarios): array
    {
        $porDia = [];

        foreach ($horarios as $horario) {
            $dia = (int) $horario['dia_semana'];
            $porDia[$dia] = ['dia_semana' => $dia, 'abre' => (string) $horario['abre'], 'fecha' => (string) $horario['fecha']];
        }

        return $porDia;
    }

    /**
     * @param  array{dia_semana: int, abre: string, fecha: string}|null  $horario
     */
    private static function faixa(?array $horario): string
    {
        return $horario === null ? 'Fechado' : $horario['abre'].'–'.$horario['fecha'];
    }

    /**
     * Aceita o nome completo sem acento e sem caixa, ou um prefixo de três letras que só casa com um dia (ex.: "sab").
     *
     * @throws ArgumentoInvalido
     */
    private static function diaSemana(string $dia): int
    {
        $procurado = Str::lower(Str::ascii(trim($dia)));

        if (isset(self::DIAS[$procurado])) {
            return self::DIAS[$procurado];
        }

        if (strlen($procurado) >= 3) {
            $candidatos = array_filter(array_keys(self::DIAS), fn (string $nome) => str_starts_with($nome, $procurado));

            if (count($candidatos) === 1) {
                return self::DIAS[reset($candidatos)];
            }
        }

        throw new ArgumentoInvalido("Dia da semana desconhecido: {$dia}.");
    }
}
