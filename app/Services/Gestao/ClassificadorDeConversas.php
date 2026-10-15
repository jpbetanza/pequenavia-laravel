<?php

namespace App\Services\Gestao;

use App\Enums\CasoDeUso;
use App\Enums\PapelDaMensagem;
use App\Enums\ResultadoDaConversa;
use App\Enums\SituacaoDoTema;
use App\Enums\StatusDaAcao;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\RodadaDeClassificacao;
use App\Models\TemaDePedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Uma rodada: lê as conversas paradas, pergunta ao JEV, pede o resumo ao Haiku quando vale a pena e grava a leitura.
 */
class ClassificadorDeConversas
{
    /** Depois de tantas falhas seguidas do JEV, o serviço está fora do ar e a rodada para. */
    private const FALHAS_SEGUIDAS_PARA_PARAR = 3;

    /** @var Collection<int, TemaDePedido> Todos os temas, inclusive descartados, para achar um nome já existente. */
    private Collection $temas;

    /** @var list<array{id: string, nome: string, descricao: string|null}> Temas que entram como opção no JEV e no Haiku. */
    private array $ativos = [];

    public function __construct(
        private readonly ClienteJev $jev,
        private readonly ClienteDeResumo $haiku,
        private readonly EstadoDaConversa $estado,
        private readonly PerguntasDoJev $perguntas,
    ) {
        $this->temas = new Collection;
    }

    public function rodar(): RodadaDeClassificacao
    {
        $rodada = RodadaDeClassificacao::create([
            'iniciada_em' => now(),
            'conversas_lidas' => 0,
            'resumidas' => 0,
            'temas_novos' => 0,
            'falhas' => 0,
            'tokens_jev' => 0,
            'custo_jev' => 0,
            'custo_haiku' => 0,
        ]);

        try {
            $this->carregarTemas();

            $seguidas = 0;

            foreach ($this->candidatas() as $conversa) {
                $rodada->conversas_lidas++;

                try {
                    $this->classificar($conversa, $rodada);
                    $seguidas = 0;
                } catch (FalhaDoJev $falha) {
                    $rodada->falhas++;
                    $seguidas++;
                    Log::warning('gestao.jev', ['conversa' => $conversa->id, 'motivo' => $falha->motivo]);

                    if ($seguidas >= self::FALHAS_SEGUIDAS_PARA_PARAR) {
                        $rodada->erro = "O JEV falhou {$seguidas} vezes seguidas ({$falha->motivo}); a rodada parou.";

                        break;
                    }
                } catch (Throwable $erro) {
                    $rodada->falhas++;
                    $seguidas = 0;
                    Log::error('gestao.classificacao', ['conversa' => $conversa->id, 'erro' => $erro->getMessage()]);
                }
            }
        } catch (Throwable $erro) {
            Log::error('gestao.rodada', ['erro' => $erro->getMessage()]);
            $rodada->erro = 'Rodada interrompida por um erro inesperado.';
        }

        // Os custos são decimais: o atributo lê e grava texto, então o valor sai formatado com seis casas.
        $rodada->custo_jev = number_format($rodada->tokens_jev * (float) config('gestao.preco_jev_por_milhao') / 1_000_000, 6, '.', '');
        $rodada->terminada_em = Carbon::now();
        $rodada->save();

        return $rodada;
    }

    private function carregarTemas(): void
    {
        $this->temas = TemaDePedido::query()->orderBy('nome')->get();

        $this->ativos = array_values($this->temas
            ->filter(fn (TemaDePedido $tema) => $tema->situacao !== SituacaoDoTema::Descartado && $tema->juntado_em_id === null)
            ->map(fn (TemaDePedido $tema) => ['id' => $tema->id, 'nome' => $tema->nome, 'descricao' => $tema->descricao])
            ->all());
    }

    /**
     * Conversas ociosas há tempo suficiente que ainda não foram lidas, receberam mensagens depois da última leitura,
     * ou ficaram aguardando a secretaria além do prazo (para fechar como abandonadas).
     *
     * @return Collection<int, Conversa>
     */
    private function candidatas(): Collection
    {
        $prazo = now()->subDays((int) config('gestao.dias_aguardando'));

        return Conversa::query()
            ->where('ultima_mensagem_em', '<=', now()->subMinutes((int) config('gestao.ociosidade_minutos')))
            ->where(fn (Builder $consulta) => $consulta
                ->whereDoesntHave('classificacao')
                ->orWhereHas('classificacao', fn (Builder $classificacao) => $classificacao
                    ->whereColumn('classificacoes_de_conversa.mensagens_ate', '<', 'conversas.ultima_mensagem_em'))
                ->orWhere(fn (Builder $vencida) => $vencida
                    ->where('ultima_mensagem_em', '<', $prazo)
                    ->whereHas('classificacao', fn (Builder $classificacao) => $classificacao
                        ->where('resultado', ResultadoDaConversa::Aguardando->value)
                        ->whereNull('correcao->resultado'))))
            ->whereHas('mensagens', fn (Builder $mensagens) => $mensagens->where('papel', PapelDaMensagem::Usuario))
            ->orderBy('ultima_mensagem_em')
            ->limit((int) config('gestao.max_conversas_por_rodada'))
            ->get();
    }

    /**
     * @throws FalhaDoJev
     */
    private function classificar(Conversa $conversa, RodadaDeClassificacao $rodada): void
    {
        // Carrega uma vez só: o estado e o resumo reaproveitam estas relações.
        $conversa->load([
            'mensagens' => fn ($consulta) => $consulta->orderBy('created_at')->orderBy('id'),
            'acoes',
            'user',
        ]);
        $mensagens = $conversa->mensagens;
        $acoes = $conversa->acoes;

        $resposta = $this->jev->perguntar(
            $this->estado->montar($conversa),
            $this->perguntas->montar($this->ativos),
        );
        $rodada->tokens_jev += $resposta->tokensEntrada;

        $caso = $this->rotulo($resposta->escolha('use_case'), array_column(CasoDeUso::cases(), 'value'), CasoDeUso::Outro->value);
        $resultado = $this->rotulo($resposta->escolha('outcome'), array_column(ResultadoDaConversa::cases(), 'value'), ResultadoDaConversa::Parcial->value);
        $frustracao = $resposta->nota('frustration');
        $confusa = $resposta->noul('confused');
        $pedidoFora = $resposta->noul('unmet_request');
        $erroDoAssistente = $resposta->noul('assistant_error');
        $esperando = $resposta->noul('ended_waiting');

        $confianca = PerguntasDoJev::confiancaMinima();
        $aRevisar = $caso['confianca'] < $confianca || $resultado['confianca'] < $confianca;
        $parouEsperando = $esperando >= PerguntasDoJev::SIM && $resultado['valor'] !== ResultadoDaConversa::Resolvida->value;

        // Parou esperando a secretaria: enquanto é recente, só aguarda (ela pode voltar); passado o prazo, abandonou.
        $recente = $conversa->ultima_mensagem_em?->greaterThan(now()->subDays((int) config('gestao.dias_aguardando'))) ?? false;
        $abandonada = $parouEsperando && ! $recente;

        if ($parouEsperando && $recente) {
            $resultado = ['valor' => ResultadoDaConversa::Aguardando->value, 'confianca' => $esperando];
            $aRevisar = $caso['confianca'] < $confianca;
        }

        [$temaId, $temaOrigem, $temaConfianca] = $this->temaDoJev($resposta->escolha('unmet_theme'), $pedidoFora);

        $resumo = null;

        $emAberto = in_array($resultado['valor'], [ResultadoDaConversa::Parcial->value, ResultadoDaConversa::NaoResolvida->value], true);

        if (($emAberto || $pedidoFora >= PerguntasDoJev::SIM || $aRevisar)
            && $rodada->resumidas < (int) config('gestao.max_resumos_por_rodada')) {
            try {
                $pedido = $this->haiku->resumir(
                    $this->estado->mensagensParaResumo($conversa),
                    array_column($this->ativos, 'nome'),
                );

                $resumo = $pedido->resumo;
                $rodada->resumidas++;
                $rodada->custo_haiku = number_format((float) $rodada->custo_haiku + $pedido->custo, 6, '.', '');

                $tema = $temaId === null && $pedidoFora >= PerguntasDoJev::SIM && $pedido->pedidoNome !== null
                    ? $this->temaDoHaiku($pedido, $rodada)
                    : null;

                if ($tema !== null) {
                    $temaId = $tema->id;
                    $temaOrigem = 'haiku';
                    $temaConfianca = null;
                }
            } catch (FalhaDoResumo $falha) {
                // Sem resumo a conversa ainda é classificada; o Haiku não é pré-requisito.
                Log::warning('gestao.haiku', ['conversa' => $conversa->id, 'motivo' => $falha->motivo]);
            }
        }

        $assistente = $mensagens->filter(fn (Mensagem $mensagem) => $mensagem->papel === PapelDaMensagem::Assistente);

        ClassificacaoDeConversa::updateOrCreate(
            ['conversa_id' => $conversa->id],
            [
                'user_id' => $conversa->user_id,
                'rodada_id' => $rodada->id,
                'mensagens_ate' => $conversa->ultima_mensagem_em,
                'caso_de_uso' => $caso['valor'],
                'caso_confianca' => $caso['confianca'],
                'resultado' => $resultado['valor'],
                'resultado_confianca' => $resultado['confianca'],
                'abandonada' => $abandonada,
                'frustracao' => $frustracao['nota'],
                'frustracao_confianca' => $frustracao['confianca'],
                'confusa' => $confusa,
                'pedido_fora' => $pedidoFora,
                'erro_do_assistente' => $erroDoAssistente,
                'tema_id' => $temaId,
                'tema_origem' => $temaOrigem,
                'tema_confianca' => $temaConfianca,
                'a_revisar' => $aRevisar,
                'resumo' => $resumo,
                'ferramentas' => $assistente
                    ->flatMap(fn (Mensagem $mensagem) => array_column($mensagem->chamadas ?? [], 'nome'))
                    ->unique()
                    ->values()
                    ->all(),
                'acoes_propostas' => $acoes->count(),
                'acoes_confirmadas' => $acoes->filter(fn ($acao) => $acao->status === StatusDaAcao::Confirmada)->count(),
                'mensagens' => $mensagens->count(),
                'tokens_assistente_entrada' => (int) $assistente->sum('tokens_entrada'),
                'tokens_assistente_saida' => (int) $assistente->sum('tokens_saida'),
                'modelo_jev' => $resposta->modelo,
                'classificada_em' => now(),
            ],
        );
    }

    /**
     * Opção que o JEV escolheu, se for uma das válidas; senão o padrão com confiança zero, o que manda a conversa para "a revisar".
     *
     * @param  array{escolha: string, confianca: float, probabilidades: array<string, float>}  $escolha
     * @param  list<string>  $validos
     * @return array{valor: string, confianca: float}
     */
    private function rotulo(array $escolha, array $validos, string $padrao): array
    {
        return in_array($escolha['escolha'], $validos, true)
            ? ['valor' => $escolha['escolha'], 'confianca' => $escolha['confianca']]
            : ['valor' => $padrao, 'confianca' => 0.0];
    }

    /**
     * Tema escolhido pelo JEV, se for um dos temas ativos, com confiança suficiente e um pedido de fato.
     *
     * @param  array{escolha: string, confianca: float, probabilidades: array<string, float>}  $escolha
     * @return array{0: string|null, 1: string|null, 2: float|null}
     */
    private function temaDoJev(array $escolha, float $pedidoFora): array
    {
        $id = Str::after($escolha['escolha'], 'tema_');

        $valido = str_starts_with($escolha['escolha'], 'tema_')
            && in_array($id, array_column($this->ativos, 'id'), true)
            && $escolha['confianca'] >= PerguntasDoJev::TEMA_CONFIANCA_MINIMA
            && $pedidoFora >= PerguntasDoJev::SIM;

        return $valido ? [$id, 'jev', $escolha['confianca']] : [null, null, null];
    }

    /**
     * Segue a cadeia de temas juntados até o tema que sobrou; um laço na cadeia para onde está.
     */
    private function destinoFinal(TemaDePedido $tema): TemaDePedido
    {
        $vistos = [$tema->id => true];

        while ($tema->juntado_em_id !== null && ! isset($vistos[$tema->juntado_em_id])) {
            $proximo = $this->temas->firstWhere('id', $tema->juntado_em_id);

            if ($proximo === null) {
                break;
            }

            $tema = $proximo;
            $vistos[$tema->id] = true;
        }

        return $tema;
    }

    /**
     * Acha o tema pelo nome normalizado; se não existir, cria um novo, em situação "novo".
     */
    private function temaDoHaiku(ResumoDaConversa $pedido, RodadaDeClassificacao $rodada): ?TemaDePedido
    {
        $nome = mb_substr(trim((string) $pedido->pedidoNome), 0, 80);
        $slug = Str::slug($nome);

        if ($slug === '') {
            return null;
        }

        $existente = $this->temas->first(fn (TemaDePedido $tema) => Str::slug($tema->nome) === $slug);

        if ($existente !== null) {
            return $this->destinoFinal($existente);
        }

        $tema = TemaDePedido::create([
            'nome' => $nome,
            'descricao' => $pedido->pedidoDescricao !== null ? mb_substr($pedido->pedidoDescricao, 0, 300) : null,
            'situacao' => SituacaoDoTema::Novo,
            'criado_por' => 'haiku',
        ]);

        $this->temas->push($tema);
        $rodada->temas_novos++;

        return $tema;
    }
}
