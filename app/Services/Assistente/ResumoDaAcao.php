<?php

namespace App\Services\Assistente;

use App\Models\AcaoProposta;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;

/**
 * Monta o cartão `acao_proposta` com os nomes atuais dos cadastros. Cadastro apagado aparece como "removido".
 */
final class ResumoDaAcao
{
    /**
     * @return array{id: string, status: string, resumo: array<string, mixed>, resultado: array{reserva_id: string, data: string}|null}
     */
    public function paraCartao(AcaoProposta $acao): array
    {
        $dados = $acao->dados;
        $espaco = Espaco::query()->whereKey($dados['espaco_id'])->first();
        $grupo = isset($dados['grupo_id']) ? Grupo::query()->whereKey($dados['grupo_id'])->first() : null;
        $equipamentos = Equipamento::query()
            ->whereIn('id', array_column($dados['equipamentos'] ?? [], 'id'))
            ->get()
            ->keyBy('id');

        return [
            'id' => $acao->id,
            'status' => $acao->status->value,
            'resumo' => [
                'titulo' => $dados['titulo'],
                'grupo_id' => $dados['grupo_id'] ?? null,
                'grupo_nome' => isset($dados['grupo_id']) ? ($grupo?->nome ?? 'Grupo removido') : null,
                'espaco_id' => $dados['espaco_id'],
                'espaco_nome' => $espaco?->nome ?? 'Espaço removido',
                'data' => $dados['data'],
                'inicio' => $dados['inicio'],
                'fim' => $dados['fim'],
                'pessoas' => $dados['pessoas'] ?? null,
                'equipamentos' => collect($dados['equipamentos'] ?? [])
                    ->map(fn (array $pedido) => [
                        'id' => $pedido['id'],
                        'nome' => $equipamentos->get($pedido['id'])?->nome ?? 'Equipamento removido',
                        'quantidade' => $pedido['quantidade'],
                    ])
                    ->values()
                    ->all(),
                'observacoes' => $dados['observacoes'] ?? null,
            ],
            'resultado' => $acao->resultado_id === null ? null : [
                'reserva_id' => $acao->resultado_id,
                'data' => $dados['data'],
            ],
        ];
    }
}
