<?php

namespace App\Services\Assistente;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Conversa com o OpenRouter (API de chat no formato OpenAI) em streaming.
 * Entrega os pedaços de texto enquanto chegam e devolve, no fim, o texto inteiro, as chamadas de ferramenta e o uso de tokens.
 */
class ClienteOpenRouter
{
    public function __construct(private readonly int $tamanhoDaLeitura = 8192) {}

    /**
     * @param  list<array<string, mixed>>  $mensagens
     * @param  list<array<string, mixed>>  $ferramentas
     * @return Generator<int, string, mixed, RespostaDoModelo>
     *
     * @throws FalhaDoModelo
     */
    public function conversar(array $mensagens, array $ferramentas): Generator
    {
        $corpo = array_filter([
            'model' => config('assistente.modelo'),
            'messages' => $mensagens,
            'tools' => $ferramentas === [] ? null : $ferramentas,
            'stream' => true,
            'usage' => ['include' => true],
        ], fn ($valor) => $valor !== null);

        try {
            $resposta = Http::withToken((string) config('assistente.chave'))
                ->withHeaders(['X-Title' => 'PequenaVia'])
                ->timeout((int) config('assistente.timeout_segundos'))
                ->withOptions(['stream' => true])
                ->post((string) config('assistente.url'), $corpo);
        } catch (ConnectionException) {
            throw new FalhaDoModelo('timeout');
        }

        if ($resposta->failed()) {
            Log::warning('assistente.http', ['status' => $resposta->status()]);

            throw new FalhaDoModelo(match ($resposta->status()) {
                401, 403 => 'chave',
                402 => 'credito',
                429 => 'limite',
                default => 'servidor',
            });
        }

        $texto = '';
        $chamadas = [];
        $tokensEntrada = null;
        $tokensSaida = null;

        try {
            foreach ($this->linhasDeDados($resposta->toPsrResponse()->getBody()) as $dados) {
                if ($dados === '[DONE]') {
                    break;
                }

                $chunk = json_decode($dados, true);

                if (! is_array($chunk)) {
                    continue;
                }

                if (isset($chunk['error'])) {
                    throw new FalhaDoModelo('stream');
                }

                if (isset($chunk['usage'])) {
                    $tokensEntrada = $chunk['usage']['prompt_tokens'] ?? null;
                    $tokensSaida = $chunk['usage']['completion_tokens'] ?? null;
                }

                $delta = $chunk['choices'][0]['delta'] ?? [];

                if (is_string($delta['content'] ?? null) && $delta['content'] !== '') {
                    $texto .= $delta['content'];
                    yield $delta['content'];
                }

                foreach ($delta['tool_calls'] ?? [] as $parcial) {
                    $indice = (int) ($parcial['index'] ?? 0);
                    $chamadas[$indice] ??= ['id' => '', 'nome' => '', 'argumentos' => ''];
                    $chamadas[$indice]['id'] = $parcial['id'] ?? $chamadas[$indice]['id'];
                    $chamadas[$indice]['nome'] = $parcial['function']['name'] ?? $chamadas[$indice]['nome'];
                    $chamadas[$indice]['argumentos'] .= $parcial['function']['arguments'] ?? '';
                }
            }
        } catch (FalhaDoModelo $falha) {
            throw $falha;
        } catch (Throwable $erro) {
            report($erro);

            throw new FalhaDoModelo('stream');
        }

        ksort($chamadas);

        return new RespostaDoModelo(
            $texto,
            array_values(array_map(fn (array $c) => new ChamadaDeFerramenta($c['id'], $c['nome'], $c['argumentos']), $chamadas)),
            $tokensEntrada,
            $tokensSaida,
        );
    }

    /**
     * Valor de cada linha `data:` do SSE, montando linhas partidas entre leituras e ignorando comentários.
     *
     * @return Generator<int, string>
     */
    private function linhasDeDados(StreamInterface $corpo): Generator
    {
        $resto = '';

        while (! $corpo->eof()) {
            $resto .= $corpo->read($this->tamanhoDaLeitura);

            while (($fim = strpos($resto, "\n")) !== false) {
                $linha = rtrim(substr($resto, 0, $fim), "\r");
                $resto = substr($resto, $fim + 1);

                if (str_starts_with($linha, 'data:')) {
                    yield ltrim(substr($linha, 5));
                }
            }
        }

        if (str_starts_with($resto, 'data:')) {
            yield ltrim(substr($resto, 5));
        }
    }
}
