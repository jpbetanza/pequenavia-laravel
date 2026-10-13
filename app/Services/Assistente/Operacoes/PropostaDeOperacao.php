<?php

namespace App\Services\Assistente\Operacoes;

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\User;
use App\Services\Assistente\Cadastros\ChaveDaProposta;
use App\Services\Assistente\Cadastros\ExpiracaoDePropostas;
use Illuminate\Support\Facades\DB;

/**
 * Grava uma operação proposta (ver Operacoes) como AcaoProposta pendente. Como nos cadastros, a proposta nova
 * expira só as pendentes da conversa com a mesma chave, vindas de um pedido anterior.
 */
final class PropostaDeOperacao
{
    /**
     * @param  array{titulo: string, chave: string, linhas: list<array{rotulo: string, antes: ?string, depois: ?string}>, avisos: list<string>, link: string}&array<string, mixed>  $dados
     * @return array{acao: AcaoProposta, substituiu: list<string>}
     */
    public static function criar(User $usuario, Conversa $conversa, string $tipo, array $dados): array
    {
        return DB::transaction(function () use ($usuario, $conversa, $tipo, $dados): array {
            $pedido = ExpiracaoDePropostas::pedidoAtual($conversa);
            $substituiu = ExpiracaoDePropostas::expirarPendentesComMesmaChave($conversa, ChaveDaProposta::de($tipo, $dados), $pedido);

            $acao = AcaoProposta::create([
                'conversa_id' => $conversa->id,
                'user_id' => $usuario->id,
                'pedido_id' => $pedido,
                'tipo' => $tipo,
                'dados' => $dados,
                'status' => StatusDaAcao::Proposta,
            ]);

            return ['acao' => $acao, 'substituiu' => $substituiu];
        });
    }
}
