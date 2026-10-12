<?php

namespace App\Services\Assistente\Cadastros;

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;

/**
 * Expira as propostas pendentes da conversa que têm a mesma chave da proposta nova.
 * Quem chama precisa estar dentro de uma transação: as pendentes ficam travadas até o fim dela.
 */
final class ExpiracaoDePropostas
{
    /**
     * @return list<string> títulos das propostas expiradas, na ordem em que foram criadas
     */
    public static function expirarPendentesComMesmaChave(Conversa $conversa, string $chave): array
    {
        $pendentes = AcaoProposta::query()
            ->where('conversa_id', $conversa->id)
            ->where('status', StatusDaAcao::Proposta->value)
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
