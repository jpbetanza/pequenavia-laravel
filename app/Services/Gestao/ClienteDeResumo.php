<?php

namespace App\Services\Gestao;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Pede ao Haiku (o mesmo modelo do assistente, via OpenRouter) um resumo curto e, se houver, o nome do pedido não atendido.
 */
class ClienteDeResumo
{
    private const INSTRUCOES = <<<'TEXTO'
Você lê uma conversa entre a secretaria de uma paróquia e o assistente do app PequenaVia e responde somente com JSON, neste formato:
{"resumo": "<até 2 frases>", "pedido": null | {"nome": "<até 5 palavras, como um título>", "descricao": "<1 frase>"}}

- "resumo" diz o que a secretaria queria e se conseguiu. Não cite nomes de pessoas.
- "pedido" só é preenchido se a secretaria pediu algo que o app ou o assistente não faz. Caso contrário, null.
- Se o pedido for igual a um dos temas já conhecidos, repita o nome exato desse tema.
TEXTO;

    /**
     * @param  list<array{de: string, texto: string}>  $mensagens
     * @param  list<string>  $nomesDosTemas
     *
     * @throws FalhaDoResumo
     */
    public function resumir(array $mensagens, array $nomesDosTemas): ResumoDaConversa
    {
        try {
            $resposta = Http::withToken((string) config('assistente.chave'))
                ->withHeaders(['X-Title' => 'PequenaVia'])
                ->timeout((int) config('assistente.timeout_segundos'))
                ->post((string) config('assistente.url'), [
                    'model' => config('gestao.haiku_modelo'),
                    'messages' => [
                        ['role' => 'system', 'content' => self::INSTRUCOES],
                        ['role' => 'user', 'content' => $this->conteudo($mensagens, $nomesDosTemas)],
                    ],
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'usage' => ['include' => true],
                ]);
        } catch (ConnectionException) {
            throw new FalhaDoResumo('timeout');
        }

        if ($resposta->failed()) {
            throw new FalhaDoResumo('http');
        }

        $conteudo = $resposta->json('choices.0.message.content');
        $dados = is_string($conteudo) ? json_decode($conteudo, true) : null;

        if (! is_array($dados) || ! is_string($dados['resumo'] ?? null) || trim($dados['resumo']) === '') {
            throw new FalhaDoResumo('json');
        }

        $pedido = $dados['pedido'] ?? null;
        $nome = is_array($pedido) && is_string($pedido['nome'] ?? null) ? trim($pedido['nome']) : '';

        return new ResumoDaConversa(
            resumo: trim($dados['resumo']),
            pedidoNome: $nome !== '' ? $nome : null,
            pedidoDescricao: $nome !== '' && is_string($pedido['descricao'] ?? null) ? trim($pedido['descricao']) : null,
            custo: (float) ($resposta->json('usage.cost') ?? 0),
        );
    }

    /**
     * @param  list<array{de: string, texto: string}>  $mensagens
     * @param  list<string>  $nomesDosTemas
     */
    private function conteudo(array $mensagens, array $nomesDosTemas): string
    {
        $temas = $nomesDosTemas === [] ? '(nenhum)' : implode("\n", array_map(fn (string $nome) => "- {$nome}", $nomesDosTemas));

        $linhas = array_map(
            fn (array $mensagem) => ($mensagem['de'] === 'secretaria' ? 'Secretaria' : 'Assistente').': '.$mensagem['texto'],
            $mensagens,
        );

        return "Temas já conhecidos:\n{$temas}\n\nConversa:\n".implode("\n", $linhas);
    }
}
