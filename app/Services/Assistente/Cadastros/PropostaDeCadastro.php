<?php

namespace App\Services\Assistente\Cadastros;

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Grava a proposta de um cadastro (criar, alterar ou excluir) como AcaoProposta pendente.
 * Uma proposta nova expira só as pendentes da conversa com a mesma chave (ver ChaveDaProposta).
 */
final class PropostaDeCadastro
{
    /**
     * @param  array<string, mixed>  $final  estado final validado (vazio na exclusão)
     * @param  list<array{rotulo: string, antes: ?string, depois: ?string}>  $linhas
     * @param  list<string>  $avisos
     * @return array{acao: AcaoProposta, substituiu: list<string>} títulos das propostas que a nova expirou
     */
    public static function criar(
        User $usuario,
        Conversa $conversa,
        string $entidade,
        string $operacao,
        ?Model $alvo,
        array $final,
        array $linhas,
        array $avisos,
        string $titulo,
        ?string $alvoImpressao = null,
    ): array {
        $tipo = "{$operacao}_{$entidade}";
        $dados = [
            'entidade' => $entidade,
            'operacao' => $operacao,
            'alvo_id' => $alvo?->getKey(),
            // Microssegundos: a alteração feita logo depois da proposta precisa ser percebida.
            'alvo_atualizado_em' => $alvo?->updated_at?->format('Y-m-d\TH:i:s.uP'),
            // Impressão digital do estado completo do alvo. É ela que decide se a proposta ainda vale.
            'alvo_impressao' => $alvoImpressao,
            'final' => $final,
            'linhas' => $linhas,
            'avisos' => $avisos,
            'titulo' => $titulo,
        ];

        return DB::transaction(function () use ($usuario, $conversa, $tipo, $dados): array {
            $substituiu = ExpiracaoDePropostas::expirarPendentesComMesmaChave($conversa, ChaveDaProposta::de($tipo, $dados));

            $acao = AcaoProposta::create([
                'conversa_id' => $conversa->id,
                'user_id' => $usuario->id,
                'tipo' => $tipo,
                'dados' => $dados,
                'status' => StatusDaAcao::Proposta,
            ]);

            return ['acao' => $acao, 'substituiu' => $substituiu];
        });
    }
}
