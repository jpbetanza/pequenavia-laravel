<?php

namespace App\Services\Assistente;

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ciclo de uma pergunta: o modelo responde (em texto ou pedindo ferramentas), as ferramentas rodam e o modelo responde de novo,
 * até dar a resposta final. Cada passo vira um evento SSE e fica gravado na conversa.
 *
 * O cliente pode fechar a conexão no meio do stream. O Laravel então destrói o gerador num `yield`, e o `finally` grava
 * o texto já recebido. Cada rodada grava todos os resultados das ferramentas antes de emitir qualquer evento, para que
 * nunca fique uma chamada sem resultado no histórico.
 *
 * @phpstan-type Uso array{entrada: int, saida: int, cache: int, custo: float|null}
 */
final class Assistente
{
    public function __construct(
        private readonly ClienteOpenRouter $cliente,
        private readonly MontadorDeContexto $contexto,
        private readonly CaixaDeFerramentas $caixa,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $anexos  anexos já gravados (ver Anexos::guardar)
     * @return Generator<int, EventoDoAssistente>
     */
    public function responder(User $usuario, Conversa $conversa, ?string $texto, array $anexos = []): Generator
    {
        $texto = trim((string) $texto);

        $conversa->mensagens()->create([
            'papel' => PapelDaMensagem::Usuario,
            'conteudo' => $texto,
            'anexos' => $anexos === [] ? null : $anexos,
        ]);

        if ($conversa->titulo === 'Nova conversa') {
            $conversa->titulo = $texto !== '' ? $this->tituloDe($texto) : $this->tituloDosAnexos($anexos);
        }

        $conversa->ultima_mensagem_em = now();
        $conversa->save();

        $textoVisivel = '';
        $cartoes = [];
        $uso = ['entrada' => 0, 'saida' => 0, 'cache' => 0, 'custo' => null];
        $concluido = false;

        try {
            for ($rodada = 1; $rodada <= (int) config('assistente.max_rodadas_de_ferramenta'); $rodada++) {
                $gerador = $this->cliente->conversar($this->contexto->montar($usuario, $conversa), $this->caixa->definicoes());

                foreach ($gerador as $pedaco) {
                    $textoVisivel .= $pedaco;
                    yield new EventoDoAssistente('texto', ['pedaco' => $pedaco]);
                }

                $resposta = $gerador->getReturn();
                $uso = $this->somar($uso, $resposta);

                if ($resposta->chamadas === []) {
                    $concluido = true;

                    return yield from $this->concluir($conversa, $textoVisivel, $cartoes, $uso);
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

            return yield from $this->concluir($conversa, $this->juntar($textoVisivel, 'Não consegui concluir essa consulta. Pode reformular?'), $cartoes, $uso);
        } catch (FalhaDoModelo $falha) {
            $concluido = true;
            Log::warning('assistente.falha', ['conversa' => $conversa->id, 'motivo' => $falha->motivo]);

            yield from $this->gravarFalha($conversa, $textoVisivel, $cartoes, $uso, $falha->getMessage());
        } catch (Throwable $erro) {
            report($erro);

            if (! $concluido) {
                $concluido = true;

                yield from $this->gravarFalha($conversa, $textoVisivel, $cartoes, $uso, 'Não consegui responder agora. Tente de novo.');
            }
        } finally {
            if (! $concluido && $textoVisivel !== '') {
                $this->gravarResposta($conversa, $textoVisivel, $cartoes, $uso);
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
     * @param  Uso  $uso
     * @return Generator<int, EventoDoAssistente, mixed, void>
     */
    private function concluir(Conversa $conversa, string $texto, array $cartoes, array $uso): Generator
    {
        $mensagem = $this->gravarResposta($conversa, $texto, $cartoes, $uso);

        yield new EventoDoAssistente('fim', [
            'mensagem_id' => $mensagem->id,
            'conversa' => ['id' => $conversa->id, 'titulo' => $conversa->titulo],
        ]);
    }

    /**
     * Falha no meio do ciclo: grava o texto já transmitido como resposta comum (se houver) e depois a mensagem de erro.
     *
     * @param  list<array<string, mixed>>  $cartoes
     * @param  Uso  $uso
     * @return Generator<int, EventoDoAssistente, mixed, void>
     */
    private function gravarFalha(Conversa $conversa, string $textoVisivel, array $cartoes, array $uso, string $mensagem): Generator
    {
        if ($textoVisivel !== '') {
            $this->gravarResposta($conversa, $textoVisivel, $cartoes, $uso);
        }

        $conversa->mensagens()->create([
            'papel' => PapelDaMensagem::Assistente,
            'conteudo' => $mensagem,
            'erro' => true,
        ]);

        yield new EventoDoAssistente('erro', ['mensagem' => $mensagem]);
    }

    /**
     * @param  list<array<string, mixed>>  $cartoes
     * @param  Uso  $uso
     */
    private function gravarResposta(Conversa $conversa, string $texto, array $cartoes, array $uso): Mensagem
    {
        $mensagem = $conversa->mensagens()->create([
            'papel' => PapelDaMensagem::Assistente,
            'conteudo' => $texto,
            'cartoes' => $cartoes,
            'tokens_entrada' => $uso['entrada'],
            'tokens_saida' => $uso['saida'],
            'tokens_do_cache' => $uso['cache'],
            'custo' => $uso['custo'] === null ? null : number_format($uso['custo'], 6, '.', ''),
        ]);

        $conversa->ultima_mensagem_em = now();
        $conversa->save();

        return $mensagem;
    }

    /**
     * Soma o uso de uma rodada ao do pedido. O custo fica nulo enquanto o provedor não informar nenhum.
     *
     * @param  Uso  $uso
     * @return Uso
     */
    private function somar(array $uso, RespostaDoModelo $resposta): array
    {
        return [
            'entrada' => $uso['entrada'] + ($resposta->tokensEntrada ?? 0),
            'saida' => $uso['saida'] + ($resposta->tokensSaida ?? 0),
            'cache' => $uso['cache'] + ($resposta->tokensDoCache ?? 0),
            'custo' => $resposta->custo === null ? $uso['custo'] : ($uso['custo'] ?? 0.0) + $resposta->custo,
        ];
    }

    private function juntar(string $textoVisivel, string $fixo): string
    {
        return $textoVisivel === '' ? $fixo : rtrim($textoVisivel).' '.$fixo;
    }

    /**
     * Título de uma conversa que começou só com arquivos: o nome do primeiro e quantos mais vieram.
     *
     * @param  list<array<string, mixed>>  $anexos
     */
    private function tituloDosAnexos(array $anexos): string
    {
        $nome = (string) ($anexos[0]['nome'] ?? 'arquivo');
        $outros = count($anexos) - 1;

        return Str::limit($outros > 0 ? "{$nome} e mais {$outros}" : $nome, 60, '…');
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
