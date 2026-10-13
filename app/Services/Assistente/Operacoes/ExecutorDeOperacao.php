<?php

namespace App\Services\Assistente\Operacoes;

use App\Enums\EscopoDaReserva;
use App\Models\AcaoProposta;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Agenda\ConflitosDeReservaException;
use App\Services\Agenda\CriadorDeReservas;
use App\Services\Agenda\EdicaoDeReservas;
use App\Services\Agenda\EntregaDeEquipamentos;
use App\Services\Agenda\RespostaASolicitacao;
use App\Services\Agenda\SolicitacaoInvalidaException;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Executa uma operação proposta (ver Operacoes) depois do clique em Confirmar, com os mesmos serviços das telas.
 * Antes, confere que o alvo ainda existe e não mudou desde a proposta. Quem chama abre a transação.
 */
final class ExecutorDeOperacao
{
    public function __construct(
        private readonly CriadorDeReservas $criador,
        private readonly RespostaASolicitacao $respostas,
        private readonly EdicaoDeReservas $edicao,
        private readonly EntregaDeEquipamentos $entregas,
    ) {}

    /**
     * Grava a operação e devolve o id do resultado (a reserva criada na aprovação, a reserva alterada), ou null.
     *
     * @throws HttpResponseException 422 com {message} quando a operação não pode mais acontecer
     * @throws ConflitosDeReservaException 422 com os conflitos por data, na alteração de reserva
     */
    public function executar(User $usuario, AcaoProposta $acao): ?string
    {
        // Trava a agenda antes de conferir a impressão: nada muda entre a conferência e a gravação.
        $this->criador->travarAgendaDo($usuario);
        $dados = $acao->dados;

        return match ($acao->tipo) {
            'aprovar_solicitacao' => $this->aprovar($usuario, $dados),
            'recusar_solicitacao' => $this->recusarSolicitacao($usuario, $dados),
            'alterar_reserva' => $this->alterarReserva($usuario, $dados),
            'cancelar_reserva' => $this->cancelarReserva($usuario, $dados),
            'entregar_equipamento', 'recolher_equipamento' => $this->marcarEquipamento($usuario, $acao->tipo, $dados),
            default => $this->recusar('Esta ação não pode ser confirmada.'),
        };
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function aprovar(User $usuario, array $dados): string
    {
        $solicitacao = $this->solicitacao($usuario, $dados);

        if (isset($dados['espaco_id']) && ! Espaco::query()->where('user_id', $usuario->id)->whereKey($dados['espaco_id'])->exists()) {
            $this->recusar('O espaço não existe mais.');
        }

        try {
            return $this->respostas->aprovar($usuario, $solicitacao, $dados['espaco_id'] ?? null)->id;
        } catch (SolicitacaoInvalidaException $erro) {
            $this->recusar(trim($erro->getMessage().' '.implode(' ', array_column($erro->conflitos, 'mensagem'))));
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function recusarSolicitacao(User $usuario, array $dados): null
    {
        $solicitacao = $this->solicitacao($usuario, $dados);

        try {
            $this->respostas->recusar($usuario, $solicitacao, $dados['motivo'] ?? null);
        } catch (SolicitacaoInvalidaException $erro) {
            $this->recusar($erro->getMessage());
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function alterarReserva(User $usuario, array $dados): string
    {
        $reserva = $this->reserva($usuario, $dados);
        $final = $dados['final'];

        $espaco = Espaco::query()->where('user_id', $usuario->id)->whereKey($final['espaco_id'])->first()
            ?? $this->recusar('O espaço não existe mais.');
        $grupo = isset($final['grupo_id'])
            ? (Grupo::query()->where('user_id', $usuario->id)->whereKey($final['grupo_id'])->first() ?? $this->recusar('O grupo não existe mais.'))
            : null;
        $equipamentos = $final['equipamentos'] ?? [];

        if (Equipamento::query()->where('user_id', $usuario->id)->whereIn('id', array_column($equipamentos, 'id'))->count() !== count($equipamentos)) {
            $this->recusar('Um dos equipamentos não existe mais.');
        }

        $this->edicao->atualizar($usuario, $reserva, $espaco, $grupo, $final, $equipamentos, EscopoDaReserva::from($dados['escopo']));

        return $reserva->id;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function cancelarReserva(User $usuario, array $dados): null
    {
        $this->edicao->excluir($usuario, $this->reserva($usuario, $dados), EscopoDaReserva::from($dados['escopo']));

        return null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function marcarEquipamento(User $usuario, string $tipo, array $dados): string
    {
        $reserva = Reserva::query()->where('user_id', $usuario->id)->whereKey($dados['reserva_id'])->first()
            ?? $this->recusar('A reserva não existe mais.');
        $equipamento = $reserva->equipamentos()->whereKey($dados['equipamento_id'])->first()
            ?? $this->recusar('O equipamento não está mais nesta reserva.');
        $acao = $tipo === 'entregar_equipamento' ? 'entregue' : 'recolhido';

        if (! $this->entregas->marcar($reserva, $equipamento, $acao)) {
            $this->recusar("Já estava marcado como {$acao}.");
        }

        return $reserva->id;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function solicitacao(User $usuario, array $dados): Solicitacao
    {
        $solicitacao = Solicitacao::query()->where('user_id', $usuario->id)->whereKey($dados['solicitacao_id'])->first()
            ?? $this->recusar('A solicitação não existe mais.');

        if (isset($dados['impressao']) && ! hash_equals($dados['impressao'], ImpressaoDaAgenda::de($solicitacao))) {
            $this->recusar('A solicitação mudou desde a proposta. Peça de novo.');
        }

        return $solicitacao;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function reserva(User $usuario, array $dados): Reserva
    {
        $reserva = Reserva::query()->where('user_id', $usuario->id)->whereKey($dados['reserva_id'])->first()
            ?? $this->recusar('A reserva não existe mais.');

        if (! hash_equals($dados['impressao'], ImpressaoDaAgenda::de($reserva))) {
            $this->recusar('A reserva mudou desde a proposta. Peça de novo.');
        }

        return $reserva;
    }

    private function recusar(string $mensagem): never
    {
        abort(response()->json(['message' => $mensagem], 422));
    }
}
