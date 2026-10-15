<?php

namespace App\Services\Gestao;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Faz as perguntas fechadas do JEV (TypeSafe, via OpenRouter) sobre o estado de uma conversa.
 */
class ClienteJev
{
    /** Três novas tentativas, com essas esperas em ms. */
    private const BACKOFF_MS = [500, 1000, 2000];

    /**
     * @param  array<string, mixed>|string  $estado
     * @param  array<string, array<string, mixed>>  $perguntas
     *
     * @throws FalhaDoJev
     */
    public function perguntar(array|string $estado, array $perguntas): RespostaDoJev
    {
        try {
            $resposta = Http::withToken((string) config('gestao.jev_chave'))
                ->withHeaders(['X-Title' => 'PequenaVia'])
                ->timeout(30)
                ->retry(
                    self::BACKOFF_MS,
                    when: fn (Throwable $erro) => $erro instanceof RequestException && $this->tentarDeNovo($erro->response->status()),
                    throw: false,
                )
                ->post((string) config('gestao.jev_url'), [
                    'model' => config('gestao.jev_modelo'),
                    'state' => $estado,
                    'questions' => $perguntas,
                ]);
        } catch (ConnectionException) {
            throw new FalhaDoJev('timeout');
        }

        if ($resposta->failed()) {
            throw new FalhaDoJev($this->motivo($resposta->status()));
        }

        $dados = $resposta->json();

        if (! is_array($dados) || ! is_array($dados['answers'] ?? null)) {
            throw new FalhaDoJev('resposta');
        }

        return new RespostaDoJev(
            modelo: (string) ($dados['model'] ?? config('gestao.jev_modelo')),
            respostas: $dados['answers'],
            tokensEntrada: (int) ($dados['usage']['input_tokens'] ?? 0),
        );
    }

    private function tentarDeNovo(int $status): bool
    {
        return $status === 408 || $status === 429 || $status >= 500;
    }

    private function motivo(int $status): string
    {
        return match (true) {
            $status === 401, $status === 403 => 'chave',
            $status === 402 => 'credito',
            $status === 429 => 'limite',
            in_array($status, [400, 413, 415, 422], true) => 'pedido',
            default => 'servidor',
        };
    }
}
