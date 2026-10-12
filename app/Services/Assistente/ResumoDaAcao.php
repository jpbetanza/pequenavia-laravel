<?php

namespace App\Services\Assistente;

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Services\Assistente\Cadastros\ChaveDaProposta;

/**
 * Monta o cartão `acao_proposta` com os nomes atuais dos cadastros. Cadastro apagado aparece como "removido".
 */
final class ResumoDaAcao
{
    /**
     * @return array<string, mixed>
     */
    public function paraCartao(AcaoProposta $acao): array
    {
        if ($acao->tipo !== 'criar_reserva') {
            return $this->paraCadastro($acao);
        }

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
            'tipo' => $acao->tipo,
            'chave' => ChaveDaProposta::de($acao->tipo, $dados),
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

    /**
     * Cartão de criar, alterar ou excluir um cadastro. Só mostra o resultado depois de confirmada.
     *
     * @return array{id: string, status: string, tipo: string, chave: string, cadastro: array<string, mixed>, resultado: array{cadastro_id: string|null, entidade: string}|null}
     */
    private function paraCadastro(AcaoProposta $acao): array
    {
        $dados = $acao->dados;

        return [
            'id' => $acao->id,
            'status' => $acao->status->value,
            'tipo' => $acao->tipo,
            'chave' => ChaveDaProposta::de($acao->tipo, $dados),
            'cadastro' => [
                'entidade' => $dados['entidade'],
                'operacao' => $dados['operacao'],
                'titulo' => $dados['titulo'],
                'linhas' => $dados['linhas'] ?? [],
                'avisos' => $dados['avisos'] ?? [],
            ],
            'resultado' => $acao->status === StatusDaAcao::Confirmada
                ? ['cadastro_id' => $acao->resultado_id, 'entidade' => $dados['entidade']]
                : null,
        ];
    }
}
