<?php

namespace App\Services\Agenda;

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\Solicitacao;
use Carbon\CarbonImmutable;

/**
 * Cartão de uma solicitação para a tela da secretaria. O estado de conflito usa o mesmo verificador das reservas.
 *
 * Só as pendentes passam pelo verificador: estado "conflito" ou "sem_conflito" com sugestões, "escolha_um_espaco" sem espaço.
 * Aprovadas e recusadas devolvem estado nulo, sem conflitos e sem sugestões.
 */
final class CartaoDeSolicitacao
{
    public function __construct(private readonly VerificadorDeDisponibilidade $verificador) {}

    /**
     * @return array<string, mixed>
     */
    public function montar(Solicitacao $solicitacao): array
    {
        $solicitacao->loadMissing(['grupo', 'espaco', 'equipamentos']);

        $pendente = $solicitacao->status === StatusSolicitacao::Pendente;
        $conflitos = $pendente ? $this->conflitos($solicitacao) : [];

        $estado = match (true) {
            ! $pendente => null,
            $solicitacao->espaco === null => 'escolha_um_espaco',
            $conflitos === [] => 'sem_conflito',
            default => 'conflito',
        };

        $sugestoes = $estado === 'conflito'
            ? $this->verificador->sugestoes(
                $solicitacao->espaco,
                $this->dia($solicitacao),
                $solicitacao->inicio,
                $solicitacao->fim,
                $this->equipamentosPedidos($solicitacao),
                null,
                $solicitacao->pessoas,
            )
            : ['espacos' => [], 'horario' => null];

        return [
            'id' => $solicitacao->id,
            'nome' => $solicitacao->nome,
            'telefone' => $solicitacao->telefone,
            'titulo' => $solicitacao->titulo,
            'grupo_nome' => $solicitacao->grupo?->nome,
            'data' => $solicitacao->data->toDateString(),
            'inicio' => substr($solicitacao->inicio, 0, 5),
            'fim' => substr($solicitacao->fim, 0, 5),
            'pessoas' => $solicitacao->pessoas,
            'observacoes' => $solicitacao->observacoes,
            'status' => $solicitacao->status->value,
            'motivo_recusa' => $solicitacao->motivo_recusa,
            'espaco_id' => $solicitacao->espaco_id,
            'recebida_em' => $solicitacao->created_at?->setTimezone(config('agendamentos.fuso'))->toIso8601String(),
            'equipamentos' => $solicitacao->equipamentos
                ->map(fn (Equipamento $equipamento) => [
                    'id' => $equipamento->id,
                    'nome' => $equipamento->nome,
                    'quantidade' => (int) $equipamento->pivot->quantidade,
                ])
                ->values()
                ->all(),
            'reserva_id' => $solicitacao->reserva_id,
            'estado' => $estado,
            'conflitos' => array_map(fn (Conflito $conflito) => $conflito->toArray(), $conflitos),
            'sugestoes' => $sugestoes,
        ];
    }

    /**
     * Conflitos da solicitação no espaço escolhido. Vazio quando não há espaço definido.
     *
     * @return list<Conflito>
     */
    public function conflitos(Solicitacao $solicitacao): array
    {
        $solicitacao->loadMissing(['espaco', 'equipamentos']);

        if ($solicitacao->espaco === null) {
            return [];
        }

        return $this->verificador->verificar(
            $solicitacao->espaco,
            $this->dia($solicitacao),
            $solicitacao->inicio,
            $solicitacao->fim,
            $this->equipamentosPedidos($solicitacao),
        );
    }

    /**
     * @return list<array{id: string, quantidade: int}>
     */
    public function equipamentosPedidos(Solicitacao $solicitacao): array
    {
        $solicitacao->loadMissing('equipamentos');

        return $solicitacao->equipamentos
            ->map(fn (Equipamento $equipamento) => ['id' => $equipamento->id, 'quantidade' => (int) $equipamento->pivot->quantidade])
            ->values()
            ->all();
    }

    private function dia(Solicitacao $solicitacao): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $solicitacao->data->toDateString())->startOfDay();
    }
}
