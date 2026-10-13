<?php

namespace App\Services\Agenda;

use App\Enums\StatusSolicitacao;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Aprova ou recusa uma solicitação pendente. Usado pela tela de solicitações e pelas propostas do assistente.
 *
 * Tudo roda numa transação com a agenda do dono travada: a aprovação revalida a disponibilidade e, com conflito,
 * não cria nada.
 */
final class RespostaASolicitacao
{
    public function __construct(
        private readonly CartaoDeSolicitacao $cartoes,
        private readonly CriadorDeReservas $criador,
    ) {}

    /**
     * Cria a reserva com os equipamentos pedidos e vincula à solicitação.
     *
     * @param  string|null  $espacoId  espaço escolhido na aprovação; sem ele, vale o da solicitação
     *
     * @throws SolicitacaoInvalidaException
     */
    public function aprovar(User $usuario, Solicitacao $solicitacao, ?string $espacoId = null): Reserva
    {
        return DB::transaction(function () use ($usuario, $solicitacao, $espacoId): Reserva {
            $this->criador->travarAgendaDo($usuario);
            $solicitacao->refresh();
            $this->exigirPendente($solicitacao, 'Esta solicitação já foi respondida e não pode ser aprovada.');

            if ($espacoId !== null && $espacoId !== $solicitacao->espaco_id) {
                $solicitacao->forceFill(['espaco_id' => $espacoId])->save();
                $solicitacao->unsetRelation('espaco');
            }

            if ($solicitacao->espaco_id === null) {
                throw new SolicitacaoInvalidaException('Escolha um espaço antes de aprovar.');
            }

            $conflitos = $this->cartoes->conflitos($solicitacao);

            if ($conflitos !== []) {
                throw new SolicitacaoInvalidaException(
                    'Há conflito de horário, espaço ou equipamento. Resolva antes de aprovar.',
                    array_map(fn (Conflito $conflito) => $conflito->toArray(), $conflitos),
                );
            }

            $reserva = $usuario->reservas()->create([
                'espaco_id' => $solicitacao->espaco_id,
                'grupo_id' => $solicitacao->grupo_id,
                'titulo' => $solicitacao->titulo,
                'data' => $solicitacao->data->toDateString(),
                'inicio' => $solicitacao->inicio,
                'fim' => $solicitacao->fim,
                'pessoas' => $solicitacao->pessoas,
                'observacoes' => $solicitacao->observacoes,
                'solicitacao_id' => $solicitacao->id,
            ]);

            $reserva->equipamentos()->attach($this->criador->pivotDosEquipamentos($this->cartoes->equipamentosPedidos($solicitacao)));

            $solicitacao->forceFill([
                'status' => StatusSolicitacao::Aprovada,
                'reserva_id' => $reserva->id,
            ])->save();

            return $reserva;
        });
    }

    /**
     * Recusa a solicitação pendente, com motivo opcional.
     *
     * @throws SolicitacaoInvalidaException
     */
    public function recusar(User $usuario, Solicitacao $solicitacao, ?string $motivo): void
    {
        DB::transaction(function () use ($usuario, $solicitacao, $motivo): void {
            $this->criador->travarAgendaDo($usuario);
            $solicitacao->refresh();
            $this->exigirPendente($solicitacao, 'Esta solicitação já foi respondida e não pode ser recusada.');

            $solicitacao->forceFill([
                'status' => StatusSolicitacao::Recusada,
                'motivo_recusa' => $motivo,
            ])->save();
        });
    }

    /**
     * @throws SolicitacaoInvalidaException
     */
    public function exigirPendente(Solicitacao $solicitacao, string $mensagem): void
    {
        if ($solicitacao->status !== StatusSolicitacao::Pendente) {
            throw new SolicitacaoInvalidaException($mensagem);
        }
    }
}
