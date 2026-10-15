<?php

namespace App\Services\Gestao;

/**
 * Respostas do JEV por id de pergunta. Pergunta sem resposta devolve valores zerados,
 * para que a conversa caia em "a revisar" em vez de ganhar uma classificação falsa.
 */
readonly class RespostaDoJev
{
    /**
     * @param  array<string, mixed>  $respostas
     */
    public function __construct(
        public string $modelo,
        public array $respostas,
        public int $tokensEntrada,
    ) {}

    /**
     * @return array{escolha: string, confianca: float, probabilidades: array<string, float>}
     */
    public function escolha(string $id): array
    {
        $resposta = $this->resposta($id);
        $probabilidades = is_array($resposta['probabilities'] ?? null) ? $resposta['probabilities'] : [];

        return [
            'escolha' => (string) ($resposta['choice'] ?? ''),
            'confianca' => (float) ($resposta['confidence'] ?? 0),
            'probabilidades' => array_map(fn ($p) => (float) $p, $probabilidades),
        ];
    }

    /**
     * @return array{nota: float, confianca: float}
     */
    public function nota(string $id): array
    {
        $resposta = $this->resposta($id);

        return [
            'nota' => (float) ($resposta['score'] ?? 0),
            'confianca' => (float) ($resposta['confidence'] ?? 0),
        ];
    }

    /** Quanto o JEV acha que a resposta é "não" (0 a 1). */
    public function noul(string $id): float
    {
        return (float) ($this->resposta($id)['noul'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function resposta(string $id): array
    {
        $resposta = $this->respostas[$id] ?? null;

        return is_array($resposta) ? $resposta : [];
    }
}
