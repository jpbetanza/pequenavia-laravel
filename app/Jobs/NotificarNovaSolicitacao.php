<?php

namespace App\Jobs;

use App\Models\Solicitacao;
use App\Services\Push\Notificador;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Avisa no celular da secretaria que chegou um pedido pelo formulário público. Roda na fila, depois do commit:
 * o envio fala com o Google e a Apple e não deve atrasar a resposta de quem pediu.
 */
class NotificarNovaSolicitacao implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $solicitacaoId)
    {
        $this->afterCommit();
    }

    public function handle(Notificador $notificador): void
    {
        $solicitacao = Solicitacao::query()->with(['user', 'espaco'])->find($this->solicitacaoId);

        if ($solicitacao === null) {
            return;
        }

        $data = $solicitacao->data->copy();
        $data->locale('pt_BR');
        $quando = $data->isoFormat('ddd DD/MM');
        $detalhes = collect([
            Str::limit($solicitacao->nome, 40),
            $quando.', '.substr($solicitacao->inicio, 0, 5).'–'.substr($solicitacao->fim, 0, 5),
            $solicitacao->espaco?->nome,
        ])->filter()->implode(' · ');

        $notificador->notificar(
            $solicitacao->user,
            'Nova solicitação: '.Str::limit($solicitacao->titulo, 60),
            $detalhes,
            route('agendamentos.solicitacoes.index', absolute: false),
            "solicitacao-{$solicitacao->id}",
        );
    }
}
