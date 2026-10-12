<?php

namespace App\Services\Assistente\Cadastros;

use Illuminate\Support\Str;

/**
 * Chave que diz quais propostas se substituem na mesma conversa. Uma proposta nova expira só as pendentes
 * com a mesma chave: propostas de cadastros diferentes, ou de reserva e cadastro, ficam pendentes juntas.
 *
 * - criar_reserva: 'reserva' (uma reserva nova substitui a anterior, como antes).
 * - alterar_* e excluir_*: "{entidade}:{alvo_id}" (mudanças do mesmo cadastro).
 * - criar_*: "{entidade}:novo:{nome normalizado}" (o mesmo nome novo substitui a proposta anterior).
 *
 * O cartão leva a chave, para que o painel faça a mesma conta com os cartões que já estão na tela.
 */
final class ChaveDaProposta
{
    /**
     * @param  array<string, mixed>  $dados  dados da AcaoProposta (do cadastro ou da reserva)
     */
    public static function de(string $tipo, array $dados): string
    {
        if ($tipo === 'criar_reserva') {
            return 'reserva';
        }

        $entidade = (string) $dados['entidade'];

        if ($dados['operacao'] === 'criar') {
            return $entidade.':novo:'.Str::lower(Str::ascii(trim((string) ($dados['final']['nome'] ?? ''))));
        }

        return $entidade.':'.$dados['alvo_id'];
    }
}
