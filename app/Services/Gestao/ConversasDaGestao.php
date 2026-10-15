<?php

namespace App\Services\Gestao;

use App\Enums\CasoDeUso;
use App\Enums\PapelDaMensagem;
use App\Enums\ResultadoDaConversa;
use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\AcessoAConversa;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\Grupo;
use App\Models\Mensagem;
use App\Models\TemaDePedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Conversas classificadas do painel de gestão: a lista filtrada, a matriz de casos e o detalhe de uma conversa.
 * Todos os rótulos são os efetivos (a correção do gestor vence o JEV).
 *
 * @phpstan-type Filtros array{caso: ?CasoDeUso, resultado: ?ResultadoDaConversa, cliente: ?string, frustrada: bool, a_revisar: bool}
 */
final class ConversasDaGestao
{
    private const POR_PAGINA = 25;

    private const MENSAGENS_NO_DETALHE = 60;

    public function __construct(
        private readonly PainelDeGestao $painel,
        private readonly EstadoDaConversa $estado,
        private readonly Mascarador $mascarador,
        private readonly EscopoDaGestao $escopo,
    ) {}

    /**
     * Conversas classificadas no período (pela data da classificação), 25 por página. Sem página, usa a da URL.
     *
     * @param  Filtros  $filtros
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function lista(PeriodoDoPainel $periodo, array $filtros, ?int $pagina = null): LengthAwarePaginator
    {
        $pagina = $this->consulta($periodo, $filtros)
            ->with(['conversa:id,titulo', 'user:id,name'])
            ->orderByDesc('classificacoes_de_conversa.classificada_em')
            ->orderBy('classificacoes_de_conversa.id')
            ->paginate(self::POR_PAGINA, page: $pagina)
            ->withQueryString();

        // Os títulos nascem da primeira mensagem e podem trazer dados pessoais: mascara com os nomes do cliente.
        // Uma consulta só para os grupos de todos os clientes da página.
        $gruposPorCliente = Grupo::query()
            ->whereIn('user_id', $pagina->getCollection()->pluck('user_id')->unique()->all())
            ->get(['user_id', 'nome'])
            ->groupBy('user_id');

        return $pagina->through(fn (ClassificacaoDeConversa $classificacao): array => $this->linhaDaLista(
            $classificacao,
            array_values(array_filter([
                ...($gruposPorCliente->get($classificacao->user_id)?->pluck('nome')->all() ?? []),
                $classificacao->user->name,
            ])),
        ));
    }

    /**
     * Casos de uso do período com o resultado de cada um, e a tendência contra o período anterior de mesmo
     * tamanho. Respeita o filtro de cliente; os demais filtros não se aplicam a uma matriz.
     *
     * @return list<array{caso: string, rotulo: string, total: int, resolvida: int, parcial: int, nao_resolvida: int, aguardando: int, abandonada: int, tendencia_pct: float|null}>
     */
    public function matriz(PeriodoDoPainel $periodo, ?string $cliente): array
    {
        $atual = $this->agregados($periodo->inicio, $periodo->fim, $cliente);
        $anterior = $this->agregados($periodo->anteriorInicio, $periodo->anteriorFim, $cliente)->keyBy('caso')->all();

        return array_values($atual->sortByDesc('total')->map(function (\stdClass $linha) use ($anterior): array {
            $caso = (string) $linha->caso;
            $totalAnterior = isset($anterior[$caso]) ? (int) $anterior[$caso]->total : 0;
            $total = (int) $linha->total;

            return [
                'caso' => $caso,
                'rotulo' => CasoDeUso::from($caso)->rotulo(),
                'total' => $total,
                'resolvida' => (int) $linha->resolvida,
                'parcial' => (int) $linha->parcial,
                'nao_resolvida' => (int) $linha->nao_resolvida,
                'aguardando' => (int) $linha->aguardando,
                'abandonada' => (int) $linha->abandonada,
                'tendencia_pct' => $totalAnterior === 0 ? null : round(($total - $totalAnterior) * 100 / $totalAnterior, 1),
            ];
        })->all());
    }

    /**
     * Opções dos filtros e da correção: casos, resultados, clientes e temas ativos.
     *
     * @return array{casos: list<array{valor: string, rotulo: string}>, resultados: list<array{valor: string, rotulo: string}>, clientes: list<array{id: string, nome: string}>, temas: list<array{id: string, nome: string}>}
     */
    public function opcoes(): array
    {
        return [
            'casos' => array_map(fn (CasoDeUso $caso): array => ['valor' => $caso->value, 'rotulo' => $caso->rotulo()], CasoDeUso::cases()),
            'resultados' => array_map(fn (ResultadoDaConversa $resultado): array => ['valor' => $resultado->value, 'rotulo' => $resultado->rotulo()], ResultadoDaConversa::cases()),
            'clientes' => array_values(User::query()->whereIn('id', $this->clientes())->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $cliente): array => ['id' => $cliente->id, 'nome' => $cliente->name])->all()),
            'temas' => array_values(TemaDePedido::query()->ativos()->orderBy('nome')->get(['id', 'nome'])
                ->map(fn (TemaDePedido $tema): array => ['id' => $tema->id, 'nome' => $tema->nome])->all()),
        ];
    }

    /**
     * Fila de revisão inteira: estado atual, não só o período.
     */
    public function aRevisarTotal(): int
    {
        return ClassificacaoDeConversa::query()
            ->where('a_revisar', true)
            ->whereIn('user_id', $this->clientes())
            ->count();
    }

    /**
     * Detalhe de uma conversa. Registra quem abriu. Mensagens e nomes passam pelo Mascarador.
     *
     * @return array<string, mixed>
     */
    public function detalhe(Conversa $conversa, User $gestor): array
    {
        AcessoAConversa::query()->create(['user_id' => $gestor->id, 'conversa_id' => $conversa->id]);

        $conversa->loadMissing([
            'user:id,name',
            'mensagens' => fn ($consulta) => $consulta->orderBy('created_at')->orderBy('id'),
            'acoes',
            'classificacao.tema',
        ]);

        $classificacao = $conversa->classificacao;
        $nomes = $this->estado->nomes($conversa);

        $tokens = $classificacao !== null
            ? ['entrada' => $classificacao->tokens_assistente_entrada, 'saida' => $classificacao->tokens_assistente_saida]
            : $this->tokensDasMensagens($conversa);

        return [
            'id' => $conversa->id,
            'titulo' => $this->mascararTitulo($conversa->titulo, $nomes),
            'cliente' => ['id' => $conversa->user->id, 'nome' => $conversa->user->name],
            'quando' => $this->iso($classificacao !== null ? $classificacao->mensagens_ate : $conversa->ultima_mensagem_em),
            'caso' => $classificacao === null ? null : [
                'valor' => $classificacao->casoEfetivo()->value,
                'rotulo' => $classificacao->casoEfetivo()->rotulo(),
                'confianca' => $this->corrigido($classificacao, 'caso_de_uso') ? null : $classificacao->caso_confianca,
            ],
            'resultado' => $classificacao === null ? null : [
                'valor' => $classificacao->resultadoEfetivo()->value,
                'rotulo' => $classificacao->resultadoEfetivo()->rotulo(),
                'confianca' => $this->corrigido($classificacao, 'resultado') ? null : $classificacao->resultado_confianca,
            ],
            'frustracao' => $classificacao === null ? null : [
                'nota' => $classificacao->frustracao,
                'confianca' => $classificacao->frustracao_confianca,
            ],
            'abandonada' => $classificacao?->abandonada,
            'confusa' => $classificacao === null ? null : $classificacao->confusa >= PerguntasDoJev::SIM,
            'erro_do_assistente' => $classificacao === null ? null : $classificacao->erro_do_assistente >= PerguntasDoJev::SIM,
            'tema' => $this->temaDoDetalhe($classificacao),
            'resumo' => $classificacao?->resumo,
            'ferramentas' => $classificacao === null ? [] : $classificacao->ferramentas,
            'acoes' => [
                'propostas' => $conversa->acoes->count(),
                'confirmadas' => $conversa->acoes->filter(fn (AcaoProposta $acao): bool => $acao->status === StatusDaAcao::Confirmada)->count(),
            ],
            'tokens' => $tokens,
            'custo_assistente' => $this->painel->custoDoAssistente((int) $tokens['entrada'], (int) $tokens['saida']),
            'corrigida' => $classificacao?->correcao !== null,
            'a_revisar' => $classificacao !== null && $classificacao->a_revisar,
            'modelo_jev' => $classificacao?->modelo_jev,
            'mensagens' => $this->mensagensDoDetalhe($conversa, $nomes),
        ];
    }

    /**
     * Consulta base das classificações: clientes, período, filtros sobre os valores efetivos.
     *
     * @param  Filtros  $filtros
     * @return Builder<ClassificacaoDeConversa>
     */
    private function consulta(PeriodoDoPainel $periodo, array $filtros): Builder
    {
        $consulta = ClassificacaoDeConversa::query()
            ->whereIn('classificacoes_de_conversa.user_id', $this->clientes())
            ->where('classificacoes_de_conversa.classificada_em', '>=', $periodo->inicio->utc())
            ->where('classificacoes_de_conversa.classificada_em', '<', $periodo->fim->utc());

        if ($filtros['caso'] !== null) {
            $consulta->whereRaw(PainelDeGestao::CASO.' = ?', [$filtros['caso']->value]);
        }

        if ($filtros['resultado'] !== null) {
            $consulta->whereRaw(PainelDeGestao::RESULTADO.' = ?', [$filtros['resultado']->value]);
        }

        if ($filtros['cliente'] !== null) {
            $consulta->where('classificacoes_de_conversa.user_id', $filtros['cliente']);
        }

        if ($filtros['frustrada']) {
            $consulta->where('classificacoes_de_conversa.frustracao', '>=', PainelDeGestao::FRUSTRACAO_ALTA);
        }

        if ($filtros['a_revisar']) {
            $consulta->where('classificacoes_de_conversa.a_revisar', true);
        }

        return $consulta;
    }

    /**
     * Totais por caso de uso (efetivo) e resultado, numa janela semiaberta.
     *
     * @return Collection<int, \stdClass>
     */
    private function agregados(CarbonImmutable $de, CarbonImmutable $ate, ?string $cliente): Collection
    {
        $consulta = DB::table('classificacoes_de_conversa')
            ->whereIn('user_id', $this->clientes())
            ->where('classificada_em', '>=', $de->utc())
            ->where('classificada_em', '<', $ate->utc());

        if ($cliente !== null) {
            $consulta->where('user_id', $cliente);
        }

        return $consulta
            ->selectRaw(
                PainelDeGestao::CASO.' AS caso, count(*) AS total, '
                .'sum(case when '.PainelDeGestao::RESULTADO.' = ? then 1 else 0 end) AS resolvida, '
                .'sum(case when '.PainelDeGestao::RESULTADO.' = ? then 1 else 0 end) AS parcial, '
                .'sum(case when '.PainelDeGestao::RESULTADO.' = ? then 1 else 0 end) AS nao_resolvida, '
                .'sum(case when '.PainelDeGestao::RESULTADO.' = ? then 1 else 0 end) AS aguardando, '
                .'sum(case when abandonada then 1 else 0 end) AS abandonada',
                [ResultadoDaConversa::Resolvida->value, ResultadoDaConversa::Parcial->value, ResultadoDaConversa::NaoResolvida->value, ResultadoDaConversa::Aguardando->value],
            )
            ->groupByRaw(PainelDeGestao::CASO)
            ->get();
    }

    /**
     * @param  list<string>  $nomes
     * @return array<string, mixed>
     */
    private function linhaDaLista(ClassificacaoDeConversa $classificacao, array $nomes): array
    {
        return [
            'id' => $classificacao->conversa_id,
            'titulo' => $this->mascararTitulo($classificacao->conversa->titulo, $nomes),
            'cliente' => $classificacao->user->name,
            'mensagens' => $classificacao->mensagens,
            'caso' => $classificacao->casoEfetivo()->value,
            'caso_rotulo' => $classificacao->casoEfetivo()->rotulo(),
            'resultado' => $classificacao->resultadoEfetivo()->value,
            'resultado_rotulo' => $classificacao->resultadoEfetivo()->rotulo(),
            'frustracao' => $classificacao->frustracao,
            'a_revisar' => $classificacao->a_revisar,
            'quando' => $this->iso($classificacao->mensagens_ate),
        ];
    }

    /**
     * @return array{id: string, nome: string, origem: string, confianca: float|null}|null
     */
    private function temaDoDetalhe(?ClassificacaoDeConversa $classificacao): ?array
    {
        if ($classificacao === null || $classificacao->temaEfetivoId() === null) {
            return null;
        }

        $temaId = $classificacao->temaEfetivoId();

        $tema = TemaDePedido::query()->find($temaId);

        if ($tema === null) {
            return null;
        }

        // Tema escolhido pelo gestor não tem origem do JEV nem confiança.
        $corrigido = $this->corrigido($classificacao, 'tema_id', true);

        return [
            'id' => $tema->id,
            'nome' => $tema->nome,
            'origem' => $corrigido ? 'gestor' : (string) $classificacao->tema_origem,
            'confianca' => $corrigido ? null : $classificacao->tema_confianca,
        ];
    }

    /**
     * Mensagens de secretaria e assistente com texto, as últimas 60, já mascaradas.
     *
     * @param  list<string>  $nomes
     * @return list<array{id: string, de: string, texto: string, quando: string|null}>
     */
    private function mensagensDoDetalhe(Conversa $conversa, array $nomes): array
    {
        return array_values($conversa->mensagens
            ->filter(fn (Mensagem $mensagem): bool => in_array($mensagem->papel, [PapelDaMensagem::Usuario, PapelDaMensagem::Assistente], true)
                && trim((string) $mensagem->conteudo) !== '')
            ->slice(-self::MENSAGENS_NO_DETALHE)
            ->map(fn (Mensagem $mensagem): array => [
                'id' => $mensagem->id,
                'de' => $mensagem->papel === PapelDaMensagem::Usuario ? 'secretaria' : 'assistente',
                'texto' => $this->mascarador->mascarar((string) $mensagem->conteudo, $nomes),
                'quando' => $this->iso($mensagem->created_at),
            ])
            ->all());
    }

    /**
     * @return array{entrada: int, saida: int}
     */
    private function tokensDasMensagens(Conversa $conversa): array
    {
        $assistente = $conversa->mensagens->filter(fn (Mensagem $mensagem): bool => $mensagem->papel === PapelDaMensagem::Assistente);

        return [
            'entrada' => (int) $assistente->sum('tokens_entrada'),
            'saida' => (int) $assistente->sum('tokens_saida'),
        ];
    }

    /**
     * Um campo está corrigido quando a correção tem um valor para ele. Para o tema, a chave pode existir com nulo
     * (nenhum tema), e isso também conta como correção.
     */
    private function corrigido(ClassificacaoDeConversa $classificacao, string $campo, bool $aceitaNulo = false): bool
    {
        $correcao = $classificacao->correcao ?? [];

        if ($aceitaNulo) {
            return array_key_exists($campo, $correcao);
        }

        return ($correcao[$campo] ?? null) !== null;
    }

    /**
     * Subconsulta com os ids das contas do painel.
     */
    private function clientes(): QueryBuilder
    {
        return $this->escopo->ids();
    }

    /**
     * @param  list<string>  $nomes
     */
    private function mascararTitulo(?string $titulo, array $nomes): ?string
    {
        return $titulo === null ? null : $this->mascarador->mascarar($titulo, $nomes);
    }

    private function iso(mixed $momento): ?string
    {
        if ($momento === null) {
            return null;
        }

        return CarbonImmutable::instance($momento)->setTimezone((string) config('gestao.fuso'))->toIso8601String();
    }
}
