<?php

namespace App\Services\Assistente;

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ciclo de uma pergunta: o modelo responde (em texto ou pedindo ferramentas), as ferramentas rodam e o modelo responde de novo,
 * até dar a resposta final. Cada passo vira um evento SSE e fica gravado na conversa.
 *
 * O cliente pode fechar a conexão no meio do stream. O Laravel então destrói o gerador num `yield`, e o `finally` grava
 * o texto já recebido. Cada rodada grava todos os resultados das ferramentas antes de emitir qualquer evento, para que
 * nunca fique uma chamada sem resultado no histórico.
 */
final class Assistente
{
    public function __construct(
        private readonly ClienteOpenRouter $cliente,
        private readonly MontadorDeContexto $contexto,
        private readonly CaixaDeFerramentas $caixa,
    ) {}

    /**
     * @return Generator<int, EventoDoAssistente>
     */
    public function responder(User $usuario, Conversa $conversa, string $texto): Generator
    {
        $conversa->mensagens()->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => $texto]);

        if ($conversa->titulo === 'Nova conversa') {
            $conversa->titulo = $this->tituloDe($texto);
        }

        $conversa->ultima_mensagem_em = now();
        $conversa->save();

        $textoVisivel = '';
        $cartoes = [];
        $tokensEntrada = 0;
        $tokensSaida = 0;
        $concluido = false;

        try {
            for ($rodada = 1; $rodada <= (int) config('assistente.max_rodadas_de_ferramenta'); $rodada++) {
                $gerador = $this->cliente->conversar($this->contexto->montar($usuario, $conversa), $this->caixa->definicoes());

                foreach ($gerador as $pedaco) {
                    $textoVisivel .= $pedaco;
                    yield new EventoDoAssistente('texto', ['pedaco' => $pedaco]);
                }

                $resposta = $gerador->getReturn();
                $tokensEntrada += $resposta->tokensEntrada ?? 0;
                $tokensSaida += $resposta->tokensSaida ?? 0;

                if ($resposta->chamadas === []) {
                    $concluido = true;

                    return yield from $this->concluir($conversa, $textoVisivel, $cartoes, $tokensEntrada, $tokensSaida);
                }

                $conversa->mensagens()->create([
                    'papel' => PapelDaMensagem::Assistente,
                    'conteudo' => null,
                    'chamadas' => array_map(fn (ChamadaDeFerramenta $c) => ['id' => $c->id, 'nome' => $c->nome, 'argumentos' => $c->argumentosJson], $resposta->chamadas),
                ]);

                foreach ($this->executarFerramentas($usuario, $conversa, $resposta->chamadas, $cartoes) as $evento) {
                    yield $evento;
                }
            }

            $concluido = true;

            return yield from $this->concluir($conversa, $this->juntar($textoVisivel, 'Não consegui concluir essa consulta. Pode reformular?'), $cartoes, $tokensEntrada, $tokensSaida);
        } catch (FalhaDoModelo $falha) {
            $concluido = true;
            Log::warning('assistente.falha', ['conversa' => $conversa->id, 'motivo' => $falha->motivo]);

            $conversa->mensagens()->create([
                'papel' => PapelDaMensagem::Assistente,
                'conteudo' => $falha->getMessage(),
                'erro' => true,
            ]);

            yield new EventoDoAssistente('erro', ['mensagem' => $falha->getMessage()]);
        } finally {
            if (! $concluido && $textoVisivel !== '') {
                $this->gravarResposta($conversa, $textoVisivel, $cartoes, $tokensEntrada, $tokensSaida);
            }
        }
    }

    /**
     * Executa e grava todas as ferramentas da rodada antes de devolver os eventos para serem emitidos.
     *
     * @param  list<ChamadaDeFerramenta>  $chamadas
     * @param  list<array{tipo: string, dados: array<string, mixed>}>  $cartoes
     * @return list<EventoDoAssistente>
     */
    private function executarFerramentas(User $usuario, Conversa $conversa, array $chamadas, array &$cartoes): array
    {
        $eventos = [];

        foreach ($chamadas as $chamada) {
            $resultado = $this->caixa->executar($usuario, $conversa, $chamada);

            $conversa->mensagens()->create([
                'papel' => PapelDaMensagem::Ferramenta,
                'ferramenta_id' => $chamada->id,
                'conteudo' => json_encode(['dados' => $resultado->paraOModelo], JSON_UNESCAPED_UNICODE),
            ]);

            $eventos[] = new EventoDoAssistente('status', ['texto' => $this->caixa->statusDe($chamada->nome)]);

            if ($resultado->cartao !== null) {
                $cartoes[] = $resultado->cartao;
                $eventos[] = new EventoDoAssistente('cartao', $resultado->cartao);
            }
        }

        return $eventos;
    }

    /**
     * @param  list<array<string, mixed>>  $cartoes
     * @return Generator<int, EventoDoAssistente, mixed, void>
     */
    private function concluir(Conversa $conversa, string $texto, array $cartoes, int $tokensEntrada, int $tokensSaida): Generator
    {
        $mensagem = $this->gravarResposta($conversa, $texto, $cartoes, $tokensEntrada, $tokensSaida);

        yield new EventoDoAssistente('fim', [
            'mensagem_id' => $mensagem->id,
            'conversa' => ['id' => $conversa->id, 'titulo' => $conversa->titulo],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $cartoes
     */
    private function gravarResposta(Conversa $conversa, string $texto, array $cartoes, int $tokensEntrada, int $tokensSaida): Mensagem
    {
        $mensagem = $conversa->mensagens()->create([
            'papel' => PapelDaMensagem::Assistente,
            'conteudo' => $texto,
            'cartoes' => $cartoes,
            'tokens_entrada' => $tokensEntrada,
            'tokens_saida' => $tokensSaida,
        ]);

        $conversa->ultima_mensagem_em = now();
        $conversa->save();

        return $mensagem;
    }

    private function juntar(string $textoVisivel, string $fixo): string
    {
        return $textoVisivel === '' ? $fixo : rtrim($textoVisivel).' '.$fixo;
    }

    /**
     * Título da conversa: a primeira frase do primeiro pedido, com até 60 caracteres.
     */
    private function tituloDe(string $texto): string
    {
        $texto = trim($texto);
        $frase = preg_match('/^(.*?[.?!])(\s|$)/su', $texto, $casa) === 1 ? $casa[1] : $texto;

        return Str::limit($frase, 60, '…');
    }
}
