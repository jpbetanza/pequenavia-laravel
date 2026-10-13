<?php

namespace App\Services\Assistente\Cadastros;

use App\Enums\PapelDaMensagem;
use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;

/**
 * Expira as propostas pendentes da conversa que têm a mesma chave da proposta nova e vieram de um pedido anterior.
 * Propostas do mesmo pedido convivem: uma importação de planilha propõe várias reservas (chave 'reserva') de uma vez,
 * e uma correção na mensagem seguinte continua substituindo a proposta anterior.
 * Quem chama precisa estar dentro de uma transação: as pendentes ficam travadas até o fim dela.
 */
final class ExpiracaoDePropostas
{
    /**
     * Id do pedido em andamento: a última mensagem do usuário na conversa.
     */
    public static function pedidoAtual(Conversa $conversa): ?string
    {
        return $conversa->mensagens()
            ->reorder()
            ->where('papel', PapelDaMensagem::Usuario)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * @return list<string> títulos das propostas expiradas, na ordem em que foram criadas
     */
    public static function expirarPendentesComMesmaChave(Conversa $conversa, string $chave, ?string $pedidoId = null): array
    {
        $pendentes = AcaoProposta::query()
            ->where('conversa_id', $conversa->id)
            ->where('status', StatusDaAcao::Proposta->value)
            ->when($pedidoId !== null, fn ($consulta) => $consulta->where(fn ($outras) => $outras
                ->whereNull('pedido_id')
                ->orWhere('pedido_id', '!=', $pedidoId)))
            ->lockForUpdate()
            ->oldest('created_at')
            ->get()
            ->filter(fn (AcaoProposta $acao) => ChaveDaProposta::de($acao->tipo, $acao->dados) === $chave)
            ->values();

        if ($pendentes->isEmpty()) {
            return [];
        }

        AcaoProposta::query()
            ->whereKey($pendentes->modelKeys())
            ->update(['status' => StatusDaAcao::Expirada->value]);

        return $pendentes->map(fn (AcaoProposta $acao) => (string) ($acao->dados['titulo'] ?? ''))->all();
    }
}
