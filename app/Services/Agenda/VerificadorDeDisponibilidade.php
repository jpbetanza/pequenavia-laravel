<?php

namespace App\Services\Agenda;

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\Reserva;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Regras de conflito de uma reserva candidata. Todas as regras são avaliadas e reportadas, não só a primeira.
 *
 * Horários são "HH:MM" ou "HH:MM:SS". Reservas e equipamentos são lidos uma vez por data, no contexto.
 */
final class VerificadorDeDisponibilidade
{
    /** Passo, em minutos, da varredura que procura o próximo horário livre. */
    private const PASSO_DA_VARREDURA_MIN = 15;

    /** Número máximo de espaços sugeridos. */
    private const LIMITE_DE_ESPACOS_SUGERIDOS = 3;

    /**
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return list<Conflito>
     */
    public function verificar(Espaco $espaco, CarbonImmutable $data, string $inicio, string $fim, array $equipamentos, ?string $ignorarReservaId = null): array
    {
        return $this->avaliar($espaco, $data, $inicio, $fim, $equipamentos, $ignorarReservaId, $this->contexto($espaco, $data, $equipamentos));
    }

    /**
     * Espaços livres do mesmo dono (até 3, por capacidade crescente) e o primeiro horário livre do próprio espaço.
     *
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return array{espacos: list<array{id: string, nome: string, capacidade: int|null}>, horario: array{inicio: string, fim: string}|null}
     */
    public function sugestoes(Espaco $espaco, CarbonImmutable $data, string $inicio, string $fim, array $equipamentos, ?string $ignorarReservaId = null, ?int $pessoas = null): array
    {
        $contexto = $this->contexto($espaco, $data, $equipamentos);

        return [
            'espacos' => $this->espacosLivres($espaco, $data, $inicio, $fim, $equipamentos, $ignorarReservaId, $pessoas, $contexto)
                ->map(fn (Espaco $livre) => ['id' => $livre->id, 'nome' => $livre->nome, 'capacidade' => $livre->capacidade])
                ->values()
                ->all(),
            'horario' => $this->proximoHorarioLivre($espaco, $data, $inicio, $fim, $equipamentos, $ignorarReservaId, $contexto),
        ];
    }

    /**
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @param  array{reservas: Collection<int, Reserva>, equipamentos: Collection<string, Equipamento>}  $contexto
     * @return list<Conflito>
     */
    private function avaliar(Espaco $espaco, CarbonImmutable $data, string $inicio, string $fim, array $equipamentos, ?string $ignorarReservaId, array $contexto): array
    {
        $inicioMin = self::minutos($inicio);
        $fimMin = self::minutos($fim);

        if ($fimMin <= $inicioMin) {
            return [new Conflito(Conflito::FORA_DO_HORARIO, 'O horário final precisa ser depois do horário inicial.')];
        }

        $espaco->loadMissing(['horarios', 'bloqueios']);
        $reservas = $contexto['reservas']->reject(fn (Reserva $reserva) => $reserva->id === $ignorarReservaId);

        return [
            ...$this->regraEspacoInativo($espaco),
            ...$this->regraHorario($espaco, $data, $inicioMin, $fimMin),
            ...$this->regraBloqueio($espaco, $data),
            ...$this->regraSalaOcupada($espaco, $reservas, $inicioMin, $fimMin),
            ...$this->regraEquipamentos($equipamentos, $contexto['equipamentos'], $reservas, $data, $inicioMin, $fimMin),
        ];
    }

    /**
     * @return list<Conflito>
     */
    private function regraEspacoInativo(Espaco $espaco): array
    {
        if ($espaco->ativo) {
            return [];
        }

        return [new Conflito(Conflito::ESPACO_INATIVO, "O espaço “{$espaco->nome}” está inativo.")];
    }

    /**
     * @return list<Conflito>
     */
    private function regraHorario(Espaco $espaco, CarbonImmutable $data, int $inicioMin, int $fimMin): array
    {
        $horario = $espaco->horarios->firstWhere('dia_semana', $data->dayOfWeek);

        if ($horario === null) {
            return [new Conflito(Conflito::FORA_DO_HORARIO, 'O espaço não abre neste dia da semana.')];
        }

        $abreMin = self::minutos($horario->abre);
        $fechaMin = self::minutos($horario->fecha);

        if ($inicioMin < $abreMin || $fimMin > $fechaMin) {
            return [new Conflito(
                Conflito::FORA_DO_HORARIO,
                'Fora do horário de funcionamento (abre às '.self::hora($abreMin).', fecha às '.self::hora($fechaMin).').',
            )];
        }

        return [];
    }

    /**
     * @return list<Conflito>
     */
    private function regraBloqueio(Espaco $espaco, CarbonImmutable $data): array
    {
        $dia = $data->toDateString();

        return $espaco->bloqueios
            ->filter(fn (EspacoBloqueio $bloqueio) => $bloqueio->inicio->toDateString() <= $dia && $bloqueio->fim->toDateString() >= $dia)
            ->map(fn (EspacoBloqueio $bloqueio) => new Conflito(
                Conflito::BLOQUEIO,
                "Bloqueado: {$bloqueio->motivo} (de {$bloqueio->inicio->format('d/m')} a {$bloqueio->fim->format('d/m')}).",
            ))
            ->values()
            ->all();
    }

    /**
     * Sobreposição considera o intervalo mínimo do espaço: [a,b) e [c,d) conflitam se a < d + intervalo e c < b + intervalo.
     *
     * @param  Collection<int, Reserva>  $reservas
     * @return list<Conflito>
     */
    private function regraSalaOcupada(Espaco $espaco, Collection $reservas, int $inicioMin, int $fimMin): array
    {
        $intervalo = $espaco->intervalo_min;

        return $reservas
            ->where('espaco_id', $espaco->id)
            ->filter(fn (Reserva $reserva) => $inicioMin < self::minutos($reserva->fim) + $intervalo
                && self::minutos($reserva->inicio) < $fimMin + $intervalo)
            ->map(fn (Reserva $reserva) => new Conflito(
                Conflito::SALA_OCUPADA,
                "Já reservado para “{$reserva->titulo}”, ".self::hora(self::minutos($reserva->inicio)).' – '.self::hora(self::minutos($reserva->fim)).'.',
            ))
            ->values()
            ->all();
    }

    /**
     * Soma as unidades das reservas que se sobrepõem estritamente no horário, em qualquer espaço do dono.
     *
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @param  Collection<string, Equipamento>  $modelos
     * @param  Collection<int, Reserva>  $reservas
     * @return list<Conflito>
     */
    private function regraEquipamentos(array $equipamentos, Collection $modelos, Collection $reservas, CarbonImmutable $data, int $inicioMin, int $fimMin): array
    {
        $simultaneas = $reservas->filter(fn (Reserva $reserva) => $inicioMin < self::minutos($reserva->fim)
            && self::minutos($reserva->inicio) < $fimMin);
        $conflitos = [];

        foreach ($equipamentos as $pedido) {
            $modelo = $modelos->get($pedido['id']);

            if ($modelo === null) {
                $conflitos[] = new Conflito(Conflito::EQUIPAMENTO_INDISPONIVEL, 'Equipamento não encontrado.');

                continue;
            }

            if ($modelo->manutencao_ate !== null && $modelo->manutencao_ate->toDateString() >= $data->toDateString()) {
                $conflitos[] = new Conflito(
                    Conflito::EQUIPAMENTO_INDISPONIVEL,
                    "“{$modelo->nome}” está em manutenção até {$modelo->manutencao_ate->format('d/m')} e não pode ser reservado.",
                );

                continue;
            }

            $usadas = $simultaneas->sum(fn (Reserva $reserva) => (int) ($reserva->equipamentos->firstWhere('id', $modelo->id)?->pivot->quantidade ?? 0));
            $livres = max(0, $modelo->quantidade - $usadas);

            if ($usadas + $pedido['quantidade'] > $modelo->quantidade) {
                $conflitos[] = new Conflito(
                    Conflito::EQUIPAMENTO_INDISPONIVEL,
                    "“{$modelo->nome}”: pedidas {$pedido['quantidade']}, livres {$livres} de {$modelo->quantidade} neste horário.",
                );
            }
        }

        return $conflitos;
    }

    /**
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @param  array{reservas: Collection<int, Reserva>, equipamentos: Collection<string, Equipamento>}  $contexto
     * @return Collection<int, Espaco>
     */
    private function espacosLivres(Espaco $espaco, CarbonImmutable $data, string $inicio, string $fim, array $equipamentos, ?string $ignorarReservaId, ?int $pessoas, array $contexto): Collection
    {
        return Espaco::query()
            ->where('user_id', $espaco->user_id)
            ->where('ativo', true)
            ->whereKeyNot($espaco->getKey())
            ->get()
            ->filter(function (Espaco $candidato) use ($data, $inicio, $fim, $equipamentos, $ignorarReservaId, $pessoas, $contexto) {
                if ($pessoas !== null && ($candidato->capacidade === null || $candidato->capacidade < $pessoas)) {
                    return false;
                }

                return $this->avaliar($candidato, $data, $inicio, $fim, $equipamentos, $ignorarReservaId, $contexto) === [];
            })
            ->sort(fn (Espaco $a, Espaco $b): int => [$a->capacidade === null, $a->capacidade ?? 0, $a->nome]
                <=> [$b->capacidade === null, $b->capacidade ?? 0, $b->nome])
            ->take(self::LIMITE_DE_ESPACOS_SUGERIDOS);
    }

    /**
     * Varre o dia de abertura até o fechamento, de 15 em 15 minutos, mantendo a duração pedida.
     *
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @param  array{reservas: Collection<int, Reserva>, equipamentos: Collection<string, Equipamento>}  $contexto
     * @return array{inicio: string, fim: string}|null
     */
    private function proximoHorarioLivre(Espaco $espaco, CarbonImmutable $data, string $inicio, string $fim, array $equipamentos, ?string $ignorarReservaId, array $contexto): ?array
    {
        $duracao = self::minutos($fim) - self::minutos($inicio);
        $espaco->loadMissing('horarios');
        $horario = $espaco->horarios->firstWhere('dia_semana', $data->dayOfWeek);

        if ($duracao <= 0 || $horario === null) {
            return null;
        }

        $fechaMin = self::minutos($horario->fecha);

        for ($comeco = self::minutos($horario->abre); $comeco + $duracao <= $fechaMin; $comeco += self::PASSO_DA_VARREDURA_MIN) {
            $candidatoInicio = self::hora($comeco);
            $candidatoFim = self::hora($comeco + $duracao);

            if ($this->avaliar($espaco, $data, $candidatoInicio, $candidatoFim, $equipamentos, $ignorarReservaId, $contexto) === []) {
                return ['inicio' => $candidatoInicio, 'fim' => $candidatoFim];
            }
        }

        return null;
    }

    /**
     * Reservas do dono na data e os equipamentos pedidos, lidos em uma consulta cada.
     *
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return array{reservas: Collection<int, Reserva>, equipamentos: Collection<string, Equipamento>}
     */
    private function contexto(Espaco $espaco, CarbonImmutable $data, array $equipamentos): array
    {
        return [
            'reservas' => Reserva::query()
                ->where('user_id', $espaco->user_id)
                ->where('data', $data->toDateString())
                ->with('equipamentos')
                ->get(),
            'equipamentos' => Equipamento::query()
                ->whereIn('id', array_column($equipamentos, 'id'))
                ->get()
                ->keyBy('id'),
        ];
    }

    private static function minutos(string $hora): int
    {
        [$horas, $minutos] = array_map(intval(...), explode(':', $hora));

        return $horas * 60 + $minutos;
    }

    private static function hora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
