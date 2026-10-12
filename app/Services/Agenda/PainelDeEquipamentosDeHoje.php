<?php

namespace App\Services\Agenda;

use App\Models\Equipamento;
use App\Models\Reserva;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Lista de levar e recolher equipamentos do dia: cada reserva com equipamento vira dois movimentos.
 */
class PainelDeEquipamentosDeHoje
{
    /** Minutos antes do início em que o equipamento deve ser separado. */
    public const ANTECEDENCIA_DA_SEPARACAO_MIN = 15;

    /**
     * Levar (início − 15 min) e recolher (fim) de cada reserva de hoje que tem equipamento, em ordem de horário.
     *
     * @return array{rotulo: string, movimentos: list<array<string, mixed>>}
     */
    public function montar(User $user, CarbonInterface $agora): array
    {
        $fuso = config('agendamentos.fuso');

        $reservas = Reserva::query()
            ->where('user_id', $user->id)
            ->where('data', $agora->toDateString())
            ->whereHas('equipamentos')
            ->with(['equipamentos' => fn ($consulta) => $consulta->orderBy('equipamentos.nome'), 'espaco:id,nome'])
            ->get();

        $movimentos = $reservas
            ->flatMap(function (Reserva $reserva) use ($fuso) {
                $inicio = CarbonImmutable::parse($reserva->data->toDateString().' '.$reserva->inicio, $fuso);
                $fim = CarbonImmutable::parse($reserva->data->toDateString().' '.$reserva->fim, $fuso);

                return [
                    $this->movimento($reserva, 'levar', $inicio->subMinutes(self::ANTECEDENCIA_DA_SEPARACAO_MIN)->format('H:i')),
                    $this->movimento($reserva, 'recolher', $fim->format('H:i')),
                ];
            })
            ->sortBy([['horario', 'asc'], ['titulo', 'asc']])
            ->values()
            ->all();

        return [
            'rotulo' => $agora->locale('pt_BR')->isoFormat('dddd, D [de] MMMM'),
            'movimentos' => $movimentos,
        ];
    }

    /**
     * @return array{chave: string, acao: string, horario: string, titulo: string, espaco: string, reserva_id: string, itens: list<array{equipamento_id: string, nome: string, quantidade: int, marcado: bool}>}
     */
    private function movimento(Reserva $reserva, string $acao, string $horario): array
    {
        $coluna = $acao === 'levar' ? 'entregue_em' : 'recolhido_em';

        return [
            'chave' => "{$reserva->id}-{$acao}",
            'acao' => $acao,
            'horario' => $horario,
            'titulo' => $reserva->titulo,
            'espaco' => $reserva->espaco->nome,
            'reserva_id' => $reserva->id,
            'itens' => $reserva->equipamentos->map(fn (Equipamento $equipamento) => [
                'equipamento_id' => $equipamento->id,
                'nome' => $equipamento->nome,
                'quantidade' => (int) $equipamento->pivot->quantidade,
                'marcado' => $equipamento->pivot->{$coluna} !== null,
            ])->values()->all(),
        ];
    }
}
