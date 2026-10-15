<?php

namespace App\Services\Gestao;

use App\Enums\CasoDeUso;
use App\Enums\PapelDaMensagem;
use App\Enums\ResultadoDaConversa;
use App\Enums\SituacaoDoTema;
use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\RodadaDeClassificacao;
use App\Models\Solicitacao;
use App\Models\TemaDePedido;
use App\Models\User;
use App\Services\PrimeirosPassos\PrimeirosPassos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas do painel de gestão: visão geral, clientes, cliente e casos. Os números usam o valor efetivo da
 * classificação (a correção do gestor, quando existe, vence o rótulo do JEV), por isso as expressões abaixo.
 */
final class PainelDeGestao
{
    private const PERIODOS = [7, 30, 90];

    private const PERIODO_PADRAO = 30;

    private const ALERTAS_NO_MAXIMO = 5;

    private const CONVERSAS_RECENTES = 10;

    private const ITENS_NA_LINHA_DO_TEMPO = 8;

    private const HORARIOS_DA_RODADA = ['05:00', '17:00'];

    public const FRUSTRACAO_ALTA = 1.5;

    private const ATENCAO_A_PARTIR_DE = 0.25;

    private const DIAS_DO_TEMA_NOVO = 7;

    private const FATOR_DE_ERROS_DO_ASSISTENTE = 2;

    public const CASO = "COALESCE(classificacoes_de_conversa.correcao->>'caso_de_uso', classificacoes_de_conversa.caso_de_uso)";

    /** Resultados que contam como "sem resolver": aguardando fica de fora até a secretaria voltar ou o prazo passar. */
    private const EM_ABERTO = ['parcial', 'nao_resolvida'];

    public const RESULTADO = "COALESCE(classificacoes_de_conversa.correcao->>'resultado', classificacoes_de_conversa.resultado)";

    /** Correção com tema nulo quer dizer "nenhum tema", por isso a chave é testada com IS NOT NULL no JSON. */
    public const TEMA = "CASE WHEN classificacoes_de_conversa.correcao->'tema_id' IS NOT NULL THEN classificacoes_de_conversa.correcao->>'tema_id' ELSE classificacoes_de_conversa.tema_id::text END";

    private const TEMAS_ATIVOS = "SELECT id::text FROM temas_de_pedido WHERE juntado_em_id IS NULL AND situacao <> 'descartado'";

    private const SEM_ATIVIDADE = ['mensagens' => 0, 'reservas' => 0, 'conversas' => 0, 'classificadas' => 0, 'resolvidas' => 0];

    public function __construct(
        private readonly PrimeirosPassos $primeirosPassos,
        private readonly EstadoDaConversa $estado,
        private readonly Mascarador $mascarador,
        private readonly EscopoDaGestao $escopo,
    ) {}

    /**
     * Período pedido na URL. Qualquer valor fora de 7, 30 e 90 cai no padrão.
     */
    public function periodo(mixed $valor): PeriodoDoPainel
    {
        $dias = in_array((int) $valor, self::PERIODOS, true) ? (int) $valor : self::PERIODO_PADRAO;
        $hoje = CarbonImmutable::now($this->fuso())->startOfDay();
        $inicio = $hoje->subDays($dias - 1);

        return new PeriodoDoPainel(
            dias: $dias,
            inicio: $inicio,
            fim: $hoje->addDay(),
            anteriorInicio: $inicio->subDays($dias),
            anteriorFim: $inicio,
        );
    }

    /**
     * @return array{paroquias_ativas: int, paroquias_total: int, paroquias_novas: int, conversas: int, conversas_anterior: int, resolvidas_pct: float|null, a_revisar: int, acoes_confirmadas: int, acoes_propostas: int, custo: array{assistente: float|null, jev: float, haiku: float, total: float}}
     */
    public function resumo(PeriodoDoPainel $periodo): array
    {
        $indicadores = $this->indicadores($periodo);
        $classificadas = array_sum(array_column($indicadores, 'classificadas'));
        $resolvidas = array_sum(array_column($indicadores, 'resolvidas'));

        $tokens = $this->tokensDoAssistente($periodo);
        $assistente = $this->custoDoAssistente($tokens['entrada'], $tokens['saida']);
        $jev = $this->custoDasRodadas($periodo, 'custo_jev');
        $haiku = $this->custoDasRodadas($periodo, 'custo_haiku');

        return [
            'paroquias_ativas' => count(array_filter($indicadores, fn (array $i): bool => $i['mensagens'] + $i['reservas'] > 0)),
            'paroquias_total' => $this->consultaDeClientes()->count(),
            'paroquias_novas' => $this->entre($this->consultaDeClientes(), 'users.created_at', $periodo->inicio, $periodo->fim)->count(),
            'conversas' => $this->contarConversas($periodo->inicio, $periodo->fim),
            'conversas_anterior' => $this->contarConversas($periodo->anteriorInicio, $periodo->anteriorFim),
            'resolvidas_pct' => $this->porcento($resolvidas, $classificadas),
            // Fila de revisão é estado atual: conta tudo que ainda está aberto, não só o período.
            'a_revisar' => DB::table('classificacoes_de_conversa')->where('a_revisar', true)->whereIn('user_id', $this->subconsultaDeClientes())->count(),
            'acoes_confirmadas' => $this->entre(
                $this->acoesDeClientes()->where('status', StatusDaAcao::Confirmada->value),
                'acoes_propostas.confirmada_em',
                $periodo->inicio,
                $periodo->fim,
            )->count(),
            'acoes_propostas' => $this->entre($this->acoesDeClientes(), 'acoes_propostas.created_at', $periodo->inicio, $periodo->fim)->count(),
            'custo' => [
                'assistente' => $assistente,
                'jev' => $jev,
                'haiku' => $haiku,
                'total' => round(($assistente ?? 0.0) + $jev + $haiku, 6),
            ],
        ];
    }

    /**
     * Um dia por dia do período (inclusive os zerados). Parcial conta como não resolvida; aguardando fica à parte.
     *
     * @return list<array{data: string, resolvidas: int, nao_resolvidas: int, aguardando: int}>
     */
    public function porDia(PeriodoDoPainel $periodo): array
    {
        $fuso = $this->fuso();
        $linhas = $this->entre(
            DB::table('classificacoes_de_conversa')->whereIn('user_id', $this->subconsultaDeClientes()),
            'classificacoes_de_conversa.classificada_em',
            $periodo->inicio,
            $periodo->fim,
        )
            ->selectRaw(
                "to_char((classificacoes_de_conversa.classificada_em AT TIME ZONE 'UTC') AT TIME ZONE ?, 'YYYY-MM-DD') AS dia, count(*) AS total, "
                .'sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS resolvidas, '
                .'sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS aguardando',
                [$fuso, ResultadoDaConversa::Resolvida->value, ResultadoDaConversa::Aguardando->value],
            )
            ->groupByRaw('dia')
            ->get()
            ->keyBy('dia');

        $dias = [];

        for ($i = 0; $i < $periodo->dias; $i++) {
            $data = $periodo->inicio->addDays($i)->toDateString();
            $linha = $linhas->get($data);
            $total = $linha === null ? 0 : (int) $linha->total;
            $resolvidas = $linha === null ? 0 : (int) $linha->resolvidas;
            $aguardando = $linha === null ? 0 : (int) $linha->aguardando;

            $dias[] = ['data' => $data, 'resolvidas' => $resolvidas, 'nao_resolvidas' => $total - $resolvidas - $aguardando, 'aguardando' => $aguardando];
        }

        return $dias;
    }

    /**
     * Casos de uso do período, do mais frequente para o menos.
     *
     * @return list<array{caso: string, rotulo: string, total: int, pct: float|null}>
     */
    public function casos(PeriodoDoPainel $periodo): array
    {
        $linhas = $this->entre(
            DB::table('classificacoes_de_conversa')->whereIn('user_id', $this->subconsultaDeClientes()),
            'classificacoes_de_conversa.classificada_em',
            $periodo->inicio,
            $periodo->fim,
        )
            ->selectRaw(self::CASO.' AS caso, count(*) AS total')
            ->groupByRaw(self::CASO)
            ->orderByDesc('total')
            ->orderBy('caso')
            ->get();

        $soma = (int) $linhas->sum('total');

        return array_values($linhas->map(fn (object $linha): array => [
            'caso' => (string) $linha->caso,
            'rotulo' => CasoDeUso::from((string) $linha->caso)->rotulo(),
            'total' => (int) $linha->total,
            'pct' => $this->porcento((int) $linha->total, $soma),
        ])->all());
    }

    /**
     * Até cinco alertas, na ordem de prioridade: erros do assistente, clientes parados, tema mais citado, temas
     * novos e fila de revisão.
     *
     * @return list<array{tipo: string, nivel: 'alto'|'medio'|'baixo', titulo: string, texto: string, link: string}>
     */
    public function alertas(PeriodoDoPainel $periodo): array
    {
        $alertas = [
            ...$this->alertaDeErrosDoAssistente($periodo),
            ...$this->alertasDeClientesParados(),
            ...$this->alertaDoTemaMaisCitado($periodo),
            ...$this->alertasDeTemasNovos(),
            ...$this->alertaDeRevisao(),
        ];

        return array_slice($alertas, 0, self::ALERTAS_NO_MAXIMO);
    }

    /**
     * Clientes com a atividade do período, do mais ativo para o menos. Com busca, filtra por nome ou e-mail.
     *
     * @return list<array{id: string, nome: string, email: string, configuracao: array{feitos: int, total: int}|null, reservas: int, conversas: int, resolvidas_pct: float|null, ultimo_acesso: string|null}>
     */
    public function clientes(PeriodoDoPainel $periodo, ?string $busca = null, ?int $limite = null): array
    {
        $indicadores = $this->indicadores($periodo);
        $acessos = $this->ultimosAcessos();

        $consulta = User::query()->whereIn('id', $this->subconsultaDeClientes())->select(['id', 'name', 'email'])->orderBy('name');

        if ($busca !== null && trim($busca) !== '') {
            $like = '%'.addcslashes(trim($busca), '%_\\').'%';
            $consulta->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
        }

        $lista = $consulta->get()->map(fn (User $usuario): array => [
            'usuario' => $usuario,
            'indicadores' => $indicadores[$usuario->id] ?? self::SEM_ATIVIDADE,
            'ultimo' => $acessos[$usuario->id] ?? null,
        ])->all();

        usort($lista, function (array $a, array $b): int {
            $atividadeA = $a['indicadores']['mensagens'] + $a['indicadores']['reservas'];
            $atividadeB = $b['indicadores']['mensagens'] + $b['indicadores']['reservas'];

            return ($atividadeB <=> $atividadeA)
                ?: (($b['ultimo']?->getTimestamp() ?? 0) <=> ($a['ultimo']?->getTimestamp() ?? 0))
                ?: strcmp((string) $a['usuario']->name, (string) $b['usuario']->name);
        });

        if ($limite !== null) {
            $lista = array_slice($lista, 0, $limite);
        }

        return array_map(fn (array $item): array => [
            'id' => $item['usuario']->id,
            'nome' => $item['usuario']->name,
            'email' => $item['usuario']->email,
            'configuracao' => $this->configuracaoResumida($item['usuario']),
            'reservas' => $item['indicadores']['reservas'],
            'conversas' => $item['indicadores']['conversas'],
            'resolvidas_pct' => $this->porcento($item['indicadores']['resolvidas'], $item['indicadores']['classificadas']),
            'ultimo_acesso' => $this->data($item['ultimo']),
        ], $lista);
    }

    /**
     * Última rodada e a próxima (05:00 ou 17:00, a que vier primeiro no fuso).
     *
     * @return array{ultima: array{iniciada_em: string, conversas_lidas: int, resumidas: int}|null, proxima: string}
     */
    public function classificacao(): array
    {
        $fuso = $this->fuso();
        $ultima = RodadaDeClassificacao::query()->latest('iniciada_em')->first();

        return [
            'ultima' => $ultima === null ? null : [
                'iniciada_em' => $ultima->iniciada_em->setTimezone($fuso)->toIso8601String(),
                'conversas_lidas' => $ultima->conversas_lidas,
                'resumidas' => $ultima->resumidas,
            ],
            'proxima' => $this->proximaRodada(CarbonImmutable::now($fuso))->format('H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cliente(User $cliente): array
    {
        return [
            'id' => $cliente->id,
            'nome' => $cliente->name,
            'email' => $cliente->email,
            'desde' => $this->data($cliente->created_at),
            'ultimo_acesso' => $this->data($this->ultimosAcessos($cliente->id)[$cliente->id] ?? null),
        ];
    }

    /**
     * Parado: sem acesso há o limite configurado ou mais. Atenção: mais de 25% das classificadas do período não
     * resolvidas. Bem, nos demais casos.
     *
     * @return array{nivel: 'bem'|'atencao'|'parado', texto: string}
     */
    public function situacao(User $cliente, PeriodoDoPainel $periodo): array
    {
        $ultimo = $this->ultimosAcessos($cliente->id)[$cliente->id] ?? null;
        $diasSemAcesso = $ultimo === null ? null : (int) floor((CarbonImmutable::now()->getTimestamp() - $ultimo->getTimestamp()) / 86400);

        if ($diasSemAcesso === null || $diasSemAcesso >= (int) config('gestao.dias_sem_acesso_alerta')) {
            return [
                'nivel' => 'parado',
                'texto' => $diasSemAcesso === null ? 'Ainda não usou o app.' : "Sem acesso há {$diasSemAcesso} dias.",
            ];
        }

        $contagem = $this->contagemDeClassificacoes($cliente->id, $periodo);
        $naoResolvidas = $contagem['classificadas'] - $contagem['resolvidas'];

        if ($contagem['classificadas'] > 0 && $naoResolvidas / $contagem['classificadas'] > self::ATENCAO_A_PARTIR_DE) {
            $pct = (int) round($naoResolvidas * 100 / $contagem['classificadas']);

            return ['nivel' => 'atencao', 'texto' => "{$pct}% das conversas do período não foram resolvidas."];
        }

        return [
            'nivel' => 'bem',
            'texto' => $contagem['classificadas'] > 0
                ? 'Usa o app e a maioria das conversas foi resolvida.'
                : 'Usa o app. Ainda não há conversas classificadas no período.',
        ];
    }

    /**
     * @return array{feitos: int, total: int, passos: list<array{titulo: string, feito: bool, pulado: bool}>}|null
     */
    public function configuracao(User $cliente): ?array
    {
        $estado = $this->primeirosPassos->estado($cliente);

        if ($estado === null) {
            return null;
        }

        return [
            'feitos' => $estado['feitos'],
            'total' => $estado['total'],
            'passos' => array_map(fn (array $passo): array => [
                'titulo' => $passo['titulo'],
                'feito' => $passo['feito'],
                'pulado' => $passo['pulado'],
            ], $estado['passos']),
        ];
    }

    /**
     * @return array{espacos: int, equipamentos: int, grupos: int}
     */
    public function cadastros(User $cliente): array
    {
        return [
            'espacos' => Espaco::query()->where('user_id', $cliente->id)->count(),
            'equipamentos' => Equipamento::query()->where('user_id', $cliente->id)->count(),
            'grupos' => Grupo::query()->where('user_id', $cliente->id)->count(),
        ];
    }

    /**
     * Reservas criadas no período, separadas por quem as fez: confirmadas pelo assistente ou pelo formulário.
     *
     * @return array{total: int, pelo_assistente: int, pelo_formulario: int}
     */
    public function reservas(User $cliente, PeriodoDoPainel $periodo): array
    {
        $base = fn (): Builder => $this->entre(
            DB::table('reservas')->where('user_id', $cliente->id),
            'reservas.created_at',
            $periodo->inicio,
            $periodo->fim,
        );

        $reservasDoAssistente = DB::table('acoes_propostas')
            ->select('resultado_id')
            ->where('user_id', $cliente->id)
            ->where('status', StatusDaAcao::Confirmada->value)
            ->whereNotNull('resultado_id');

        return [
            'total' => $base()->count(),
            'pelo_assistente' => $base()->whereIn('reservas.id', $reservasDoAssistente)->count(),
            'pelo_formulario' => $base()->whereNotNull('reservas.solicitacao_id')->count(),
        ];
    }

    /**
     * @return array{conversas: int, resolvidas_pct: float|null, custo: float|null}
     */
    public function assistente(User $cliente, PeriodoDoPainel $periodo): array
    {
        $contagem = $this->contagemDeClassificacoes($cliente->id, $periodo);
        $tokens = $this->tokensDoAssistente($periodo, $cliente->id);

        return [
            'conversas' => $this->entre(DB::table('conversas')->where('user_id', $cliente->id), 'conversas.created_at', $periodo->inicio, $periodo->fim)->count(),
            'resolvidas_pct' => $this->porcento($contagem['resolvidas'], $contagem['classificadas']),
            'custo' => $this->custoDoAssistente($tokens['entrada'], $tokens['saida']),
        ];
    }

    /**
     * Casos de uso do cliente no período, com o resultado de cada um.
     *
     * @return list<array{rotulo: string, total: int, resolvida: int, parcial: int, nao_resolvida: int, aguardando: int}>
     */
    public function casosDoCliente(User $cliente, PeriodoDoPainel $periodo): array
    {
        $linhas = $this->entre(
            DB::table('classificacoes_de_conversa')->where('user_id', $cliente->id),
            'classificacoes_de_conversa.classificada_em',
            $periodo->inicio,
            $periodo->fim,
        )
            ->selectRaw(
                self::CASO.' AS caso, count(*) AS total, '
                .'sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS resolvida, '
                .'sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS parcial, '
                .'sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS nao_resolvida, '
                .'sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS aguardando',
                [ResultadoDaConversa::Resolvida->value, ResultadoDaConversa::Parcial->value, ResultadoDaConversa::NaoResolvida->value, ResultadoDaConversa::Aguardando->value],
            )
            ->groupByRaw(self::CASO)
            ->orderByDesc('total')
            ->orderBy('caso')
            ->get();

        return array_values($linhas->map(fn (object $linha): array => [
            'rotulo' => CasoDeUso::from((string) $linha->caso)->rotulo(),
            'total' => (int) $linha->total,
            'resolvida' => (int) $linha->resolvida,
            'parcial' => (int) $linha->parcial,
            'nao_resolvida' => (int) $linha->nao_resolvida,
            'aguardando' => (int) $linha->aguardando,
        ])->all());
    }

    /**
     * O tema que mais deixou conversas sem resolver, somando todo o histórico do cliente.
     *
     * @return array{tema: string, conversas: int}|null
     */
    public function trava(User $cliente): ?array
    {
        $linha = DB::table('classificacoes_de_conversa')
            ->where('user_id', $cliente->id)
            ->whereRaw('('.self::TEMA.') IS NOT NULL')
            ->whereIn(DB::raw(self::RESULTADO), self::EM_ABERTO)
            ->selectRaw(self::TEMA.' AS tema_id, count(*) AS conversas')
            ->groupByRaw(self::TEMA)
            ->orderByDesc('conversas')
            ->orderBy('tema_id')
            ->first();

        if ($linha === null) {
            return null;
        }

        return [
            'tema' => (string) TemaDePedido::query()->whereKey((string) $linha->tema_id)->value('nome'),
            'conversas' => (int) $linha->conversas,
        ];
    }

    /**
     * Até oito marcos da conta, do mais recente para o mais antigo.
     *
     * @return list<array{data: string, texto: string, tom: 'bom'|'ruim'|'neutro'}>
     */
    public function linhaDoTempo(User $cliente): array
    {
        $itens = [
            ['quando' => $cliente->created_at->toImmutable(), 'texto' => 'Conta criada', 'tom' => 'neutro'],
        ];

        $primeiroEspaco = Espaco::query()->where('user_id', $cliente->id)->oldest('created_at')->first();

        if ($primeiroEspaco !== null) {
            $itens[] = ['quando' => $primeiroEspaco->created_at->toImmutable(), 'texto' => "Primeiro espaço cadastrado: {$primeiroEspaco->nome}", 'tom' => 'bom'];
        }

        $primeiraSolicitacao = Solicitacao::query()->where('user_id', $cliente->id)->oldest('created_at')->first();

        if ($primeiraSolicitacao !== null) {
            $itens[] = ['quando' => $primeiraSolicitacao->created_at->toImmutable(), 'texto' => 'Recebeu a primeira solicitação pelo formulário', 'tom' => 'bom'];
        }

        // Cadastros feitos pelo assistente, agrupados por dia de confirmação.
        $cadastros = AcaoProposta::query()
            ->where('user_id', $cliente->id)
            ->where('status', StatusDaAcao::Confirmada->value)
            ->whereNotNull('confirmada_em')
            ->where(fn ($q) => $q->where('tipo', 'like', '%_espaco')->orWhere('tipo', 'like', '%_grupo')->orWhere('tipo', 'like', '%_equipamento'))
            ->get(['confirmada_em']);

        foreach ($cadastros->groupBy(fn (AcaoProposta $acao): string => $acao->confirmada_em->setTimezone($this->fuso())->toDateString()) as $grupo) {
            $quantidade = $grupo->count();
            $itens[] = [
                'quando' => $grupo->max('confirmada_em')->toImmutable(),
                'texto' => $quantidade === 1 ? 'Confirmou 1 cadastro pelo assistente' : "Confirmou {$quantidade} cadastros pelo assistente",
                'tom' => 'bom',
            ];
        }

        $naoResolvidas = DB::table('classificacoes_de_conversa')
            ->join('conversas', 'conversas.id', '=', 'classificacoes_de_conversa.conversa_id')
            ->where('classificacoes_de_conversa.user_id', $cliente->id)
            ->whereIn(DB::raw(self::RESULTADO), self::EM_ABERTO)
            ->orderByDesc('classificacoes_de_conversa.mensagens_ate')
            ->limit(5)
            ->get(['conversas.titulo', 'classificacoes_de_conversa.mensagens_ate']);

        $nomes = $this->estado->nomesDoCliente((string) $cliente->id, $cliente->name);

        foreach ($naoResolvidas as $linha) {
            $titulo = $this->mascarador->mascarar((string) $linha->titulo, $nomes);

            $itens[] = [
                'quando' => CarbonImmutable::parse((string) $linha->mensagens_ate, 'UTC'),
                'texto' => "Conversa sem resolver: {$titulo}",
                'tom' => 'ruim',
            ];
        }

        usort($itens, fn (array $a, array $b): int => $b['quando']->getTimestamp() <=> $a['quando']->getTimestamp());

        return array_map(fn (array $item): array => [
            'data' => $this->data($item['quando']),
            'texto' => $item['texto'],
            'tom' => $item['tom'],
        ], array_slice($itens, 0, self::ITENS_NA_LINHA_DO_TEMPO));
    }

    /**
     * As conversas classificadas mais recentes do cliente, com os rótulos efetivos.
     *
     * @return list<array{id: string, titulo: string, caso: string, caso_rotulo: string, resultado: string, resultado_rotulo: string, frustracao: float, quando: string|null}>
     */
    public function conversasRecentes(User $cliente): array
    {
        $linhas = DB::table('classificacoes_de_conversa')
            ->join('conversas', 'conversas.id', '=', 'classificacoes_de_conversa.conversa_id')
            ->where('classificacoes_de_conversa.user_id', $cliente->id)
            ->select(['conversas.id', 'conversas.titulo', 'classificacoes_de_conversa.frustracao', 'classificacoes_de_conversa.classificada_em'])
            ->selectRaw(self::CASO.' AS caso, '.self::RESULTADO.' AS resultado')
            ->orderByDesc('classificacoes_de_conversa.classificada_em')
            ->limit(self::CONVERSAS_RECENTES)
            ->get();

        // O título é escrito pela IA a partir da conversa e pode trazer nomes: passa pela máscara.
        $nomes = $this->estado->nomesDoCliente((string) $cliente->id, $cliente->name);

        return array_values($linhas->map(fn (object $linha): array => [
            'id' => (string) $linha->id,
            'titulo' => $this->mascarador->mascarar((string) $linha->titulo, $nomes),
            'caso' => (string) $linha->caso,
            'caso_rotulo' => CasoDeUso::from((string) $linha->caso)->rotulo(),
            'resultado' => (string) $linha->resultado,
            'resultado_rotulo' => ResultadoDaConversa::from((string) $linha->resultado)->rotulo(),
            'frustracao' => (float) $linha->frustracao,
            'quando' => $this->data(CarbonImmutable::parse((string) $linha->classificada_em, 'UTC')),
        ])->all());
    }

    private function fuso(): string
    {
        return (string) config('gestao.fuso');
    }

    /**
     * Subconsulta com os ids das contas do painel (só clientes, ou também gestores se ligado). Usada como
     * filtro em todas as consultas do painel.
     */
    private function subconsultaDeClientes(): Builder
    {
        return $this->escopo->ids();
    }

    private function consultaDeClientes(): Builder
    {
        return $this->escopo->usuarios();
    }

    private function acoesDeClientes(): Builder
    {
        return DB::table('acoes_propostas')->whereIn('user_id', $this->subconsultaDeClientes());
    }

    /**
     * Filtra por janela semiaberta [de, ate), convertendo para UTC, que é como o banco guarda as datas.
     */
    private function entre(Builder $consulta, string $coluna, CarbonImmutable $de, CarbonImmutable $ate): Builder
    {
        return $consulta->where($coluna, '>=', $de->utc())->where($coluna, '<', $ate->utc());
    }

    private function contarConversas(CarbonImmutable $de, CarbonImmutable $ate): int
    {
        return $this->entre(
            DB::table('conversas')->whereIn('user_id', $this->subconsultaDeClientes()),
            'conversas.created_at',
            $de,
            $ate,
        )->count();
    }

    /**
     * Números por cliente no período, com todo cliente presente (zerado quando não tem nada).
     *
     * @return array<string, array{mensagens: int, reservas: int, conversas: int, classificadas: int, resolvidas: int}>
     */
    private function indicadores(PeriodoDoPainel $periodo): array
    {
        $mensagens = $this->entre(
            DB::table('mensagens')
                ->join('conversas', 'conversas.id', '=', 'mensagens.conversa_id')
                ->where('mensagens.papel', PapelDaMensagem::Usuario->value)
                ->whereIn('conversas.user_id', $this->subconsultaDeClientes())
                ->groupBy('conversas.user_id')
                ->selectRaw('conversas.user_id, count(*) AS total'),
            'mensagens.created_at',
            $periodo->inicio,
            $periodo->fim,
        )->pluck('total', 'user_id');

        $reservas = $this->entre(
            DB::table('reservas')
                ->whereIn('user_id', $this->subconsultaDeClientes())
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) AS total'),
            'reservas.created_at',
            $periodo->inicio,
            $periodo->fim,
        )->pluck('total', 'user_id');

        $conversas = $this->entre(
            DB::table('conversas')
                ->whereIn('user_id', $this->subconsultaDeClientes())
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) AS total'),
            'conversas.created_at',
            $periodo->inicio,
            $periodo->fim,
        )->pluck('total', 'user_id');

        $classificadas = $this->entre(
            DB::table('classificacoes_de_conversa')
                ->whereIn('user_id', $this->subconsultaDeClientes())
                // As que aguardam a secretaria ainda não têm desfecho: ficam fora da taxa de resolvidas.
                ->whereRaw(self::RESULTADO.' <> ?', [ResultadoDaConversa::Aguardando->value])
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) AS total, sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS resolvidas', [ResultadoDaConversa::Resolvida->value]),
            'classificacoes_de_conversa.classificada_em',
            $periodo->inicio,
            $periodo->fim,
        )->get()->keyBy('user_id');

        $indicadores = [];

        foreach ($this->consultaDeClientes()->pluck('id') as $id) {
            $indicadores[(string) $id] = [
                'mensagens' => (int) ($mensagens[$id] ?? 0),
                'reservas' => (int) ($reservas[$id] ?? 0),
                'conversas' => (int) ($conversas[$id] ?? 0),
                'classificadas' => (int) ($classificadas[$id]->total ?? 0),
                'resolvidas' => (int) ($classificadas[$id]->resolvidas ?? 0),
            ];
        }

        return $indicadores;
    }

    /**
     * Última mensagem de usuário ou última reserva de cada cliente (a maior das duas), em UTC.
     *
     * @return array<string, CarbonImmutable>
     */
    private function ultimosAcessos(?string $userId = null): array
    {
        $escopo = fn (Builder $consulta, string $coluna): Builder => $userId === null
            ? $consulta->whereIn($coluna, $this->subconsultaDeClientes())
            : $consulta->where($coluna, $userId);

        $mensagens = $escopo(
            DB::table('mensagens')
                ->join('conversas', 'conversas.id', '=', 'mensagens.conversa_id')
                ->where('mensagens.papel', PapelDaMensagem::Usuario->value)
                ->groupBy('conversas.user_id')
                ->selectRaw('conversas.user_id, max(mensagens.created_at) AS ultimo'),
            'conversas.user_id',
        )->pluck('ultimo', 'user_id');

        $reservas = $escopo(
            DB::table('reservas')->groupBy('user_id')->selectRaw('user_id, max(created_at) AS ultimo'),
            'user_id',
        )->pluck('ultimo', 'user_id');

        $acessos = [];

        foreach ([$mensagens, $reservas] as $grupo) {
            foreach ($grupo as $id => $ultimo) {
                $data = CarbonImmutable::parse((string) $ultimo, 'UTC');

                if (! isset($acessos[(string) $id]) || $data->greaterThan($acessos[(string) $id])) {
                    $acessos[(string) $id] = $data;
                }
            }
        }

        return $acessos;
    }

    /**
     * @return array{entrada: int, saida: int}
     */
    private function tokensDoAssistente(PeriodoDoPainel $periodo, ?string $userId = null): array
    {
        $consulta = DB::table('mensagens')
            ->join('conversas', 'conversas.id', '=', 'mensagens.conversa_id')
            ->where('mensagens.papel', PapelDaMensagem::Assistente->value);

        $consulta = $userId === null
            ? $consulta->whereIn('conversas.user_id', $this->subconsultaDeClientes())
            : $consulta->where('conversas.user_id', $userId);

        $linha = $this->entre($consulta, 'mensagens.created_at', $periodo->inicio, $periodo->fim)
            ->selectRaw('coalesce(sum(mensagens.tokens_entrada), 0) AS entrada, coalesce(sum(mensagens.tokens_saida), 0) AS saida')
            ->first();

        return [
            'entrada' => $linha === null ? 0 : (int) $linha->entrada,
            'saida' => $linha === null ? 0 : (int) $linha->saida,
        ];
    }

    /**
     * Custo estimado do assistente. Sem os dois preços configurados, não há valor (null).
     */
    public function custoDoAssistente(int $entrada, int $saida): ?float
    {
        $precoEntrada = config('gestao.preco_assistente_entrada_por_milhao');
        $precoSaida = config('gestao.preco_assistente_saida_por_milhao');

        if (blank($precoEntrada) || blank($precoSaida)) {
            return null;
        }

        return round(($entrada * (float) $precoEntrada + $saida * (float) $precoSaida) / 1_000_000, 6);
    }

    private function custoDasRodadas(PeriodoDoPainel $periodo, string $coluna): float
    {
        return (float) $this->entre(DB::table('rodadas_de_classificacao'), 'rodadas_de_classificacao.iniciada_em', $periodo->inicio, $periodo->fim)->sum($coluna);
    }

    /**
     * @return array{classificadas: int, resolvidas: int}
     */
    private function contagemDeClassificacoes(string $userId, PeriodoDoPainel $periodo): array
    {
        $linha = $this->entre(
            DB::table('classificacoes_de_conversa')
                ->where('user_id', $userId)
                ->whereRaw(self::RESULTADO.' <> ?', [ResultadoDaConversa::Aguardando->value]),
            'classificacoes_de_conversa.classificada_em',
            $periodo->inicio,
            $periodo->fim,
        )
            ->selectRaw('count(*) AS total, sum(case when '.self::RESULTADO.' = ? then 1 else 0 end) AS resolvidas', [ResultadoDaConversa::Resolvida->value])
            ->first();

        return [
            'classificadas' => $linha === null ? 0 : (int) $linha->total,
            'resolvidas' => $linha === null ? 0 : (int) $linha->resolvidas,
        ];
    }

    /**
     * @return array{feitos: int, total: int}|null
     */
    private function configuracaoResumida(User $usuario): ?array
    {
        $estado = $this->primeirosPassos->estado($usuario);

        return $estado === null ? null : ['feitos' => $estado['feitos'], 'total' => $estado['total']];
    }

    /**
     * @return list<array{tipo: string, nivel: 'alto'|'medio'|'baixo', titulo: string, texto: string, link: string}>
     */
    private function alertaDeErrosDoAssistente(PeriodoDoPainel $periodo): array
    {
        $hoje = CarbonImmutable::now($this->fuso())->startOfDay();

        $base = fn (): Builder => DB::table('mensagens')
            ->join('conversas', 'conversas.id', '=', 'mensagens.conversa_id')
            ->where('mensagens.papel', PapelDaMensagem::Assistente->value)
            ->where('mensagens.erro', true)
            ->whereIn('conversas.user_id', $this->subconsultaDeClientes());

        $noPeriodo = $this->entre($base(), 'mensagens.created_at', $periodo->inicio, $periodo->fim)->count();
        $ontem = $this->entre($base(), 'mensagens.created_at', $hoje->subDay(), $hoje)->count();
        $media = $noPeriodo / $periodo->dias;

        if ($ontem === 0 || $ontem <= self::FATOR_DE_ERROS_DO_ASSISTENTE * $media) {
            return [];
        }

        return [[
            'tipo' => 'erros_do_assistente',
            'nivel' => 'alto',
            'titulo' => $ontem === 1 ? '1 resposta com erro ontem' : "{$ontem} respostas com erro ontem",
            'texto' => 'Acima do dobro da média diária do período ('.number_format($media, 1, ',', '').' por dia).',
            'link' => route('gestao.conversas.index'),
        ]];
    }

    /**
     * Clientes sem mensagem nem reserva há o limite de dias, cuja última conversa terminou sem resolver.
     *
     * @return list<array{tipo: string, nivel: 'alto'|'medio'|'baixo', titulo: string, texto: string, link: string}>
     */
    private function alertasDeClientesParados(): array
    {
        $limite = (int) config('gestao.dias_sem_acesso_alerta');
        $corte = CarbonImmutable::now()->subDays($limite);

        $ids = DB::table('classificacoes_de_conversa')
            ->whereIn('user_id', $this->subconsultaDeClientes())
            ->whereIn(DB::raw(self::RESULTADO), self::EM_ABERTO)
            ->where('classificacoes_de_conversa.mensagens_ate', '<', $corte->utc())
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        $acessos = $this->ultimosAcessos();
        $usuarios = User::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');
        $parados = [];

        foreach ($ids as $id) {
            $ultimo = $acessos[$id] ?? null;

            if ($ultimo !== null && $ultimo->greaterThanOrEqualTo($corte)) {
                continue;
            }

            $parados[] = ['usuario' => $usuarios->get($id), 'ultimo' => $ultimo];
        }

        usort($parados, fn (array $a, array $b): int => ($a['ultimo']?->getTimestamp() ?? 0) <=> ($b['ultimo']?->getTimestamp() ?? 0));

        return array_map(function (array $item) use ($limite): array {
            $nome = (string) $item['usuario']?->name;

            return [
                'tipo' => 'cliente_parado',
                'nivel' => 'alto',
                'titulo' => "{$nome} parou de usar o app",
                'texto' => $item['ultimo'] === null
                    ? 'Sem registro de atividade, e a última conversa terminou sem resolver.'
                    : "Sem acesso há {$limite} dias ou mais, e a última conversa terminou sem resolver.",
                'link' => route('gestao.clientes.show', ['user' => $item['usuario']?->id]),
            ];
        }, $parados);
    }

    /**
     * @return list<array{tipo: string, nivel: 'alto'|'medio'|'baixo', titulo: string, texto: string, link: string}>
     */
    private function alertaDoTemaMaisCitado(PeriodoDoPainel $periodo): array
    {
        $linha = $this->entre(
            DB::table('classificacoes_de_conversa')->whereIn('user_id', $this->subconsultaDeClientes()),
            'classificacoes_de_conversa.classificada_em',
            $periodo->inicio,
            $periodo->fim,
        )
            ->whereRaw('('.self::TEMA.') IN ('.self::TEMAS_ATIVOS.')')
            ->selectRaw(
                self::TEMA.' AS tema_id, count(*) AS conversas, count(distinct user_id) AS paroquias, sum(case when frustracao >= ? then 1 else 0 end) AS frustradas',
                [self::FRUSTRACAO_ALTA],
            )
            ->groupByRaw(self::TEMA)
            ->orderByDesc('conversas')
            ->orderBy('tema_id')
            ->first();

        if ($linha === null) {
            return [];
        }

        $temaId = (string) $linha->tema_id;
        $nome = (string) TemaDePedido::query()->whereKey($temaId)->value('nome');
        $frustradas = (int) $linha->frustradas;

        return [[
            'tipo' => 'tema_mais_citado',
            'nivel' => $frustradas > 0 ? 'alto' : 'medio',
            'titulo' => "Mais citado: {$nome}",
            'texto' => $this->contar((int) $linha->conversas, 'conversa', 'conversas').' de '.$this->contar((int) $linha->paroquias, 'paróquia', 'paróquias').(' no período, '.$frustradas.' com frustração alta.'),
            'link' => route('gestao.temas.index', ['tema' => $temaId]),
        ]];
    }

    /**
     * Temas novos com conversas suficientes nos últimos sete dias (o limite está em config).
     *
     * @return list<array{tipo: string, nivel: 'alto'|'medio'|'baixo', titulo: string, texto: string, link: string}>
     */
    private function alertasDeTemasNovos(): array
    {
        $desde = CarbonImmutable::now()->subDays(self::DIAS_DO_TEMA_NOVO)->utc();

        $contagens = DB::table('classificacoes_de_conversa')
            ->whereIn('user_id', $this->subconsultaDeClientes())
            ->where('classificacoes_de_conversa.classificada_em', '>=', $desde)
            ->whereRaw('('.self::TEMA.') IN (SELECT id::text FROM temas_de_pedido WHERE situacao = ? AND juntado_em_id IS NULL)', [SituacaoDoTema::Novo->value])
            ->selectRaw(self::TEMA.' AS tema_id, count(*) AS conversas')
            ->groupByRaw(self::TEMA)
            ->pluck('conversas', 'tema_id');

        $minimo = (int) config('gestao.tema_novo_alerta_semana');
        $temas = TemaDePedido::query()
            ->whereIn('id', $contagens->filter(fn ($total) => (int) $total >= $minimo)->keys()->all())
            ->get()
            ->sortByDesc(fn (TemaDePedido $tema): int => (int) $contagens[(string) $tema->id])
            ->values();

        return array_values($temas->map(fn (TemaDePedido $tema): array => [
            'tipo' => 'tema_novo',
            'nivel' => 'medio',
            'titulo' => "Pedido novo: {$tema->nome}",
            'texto' => "{$contagens[(string) $tema->id]} conversas nos últimos sete dias pedem algo que o app ainda não cobre.",
            'link' => route('gestao.temas.index', ['tema' => $tema->id]),
        ])->all());
    }

    /**
     * @return list<array{tipo: string, nivel: 'alto'|'medio'|'baixo', titulo: string, texto: string, link: string}>
     */
    private function alertaDeRevisao(): array
    {
        $total = DB::table('classificacoes_de_conversa')
            ->where('a_revisar', true)
            ->whereIn('user_id', $this->subconsultaDeClientes())
            ->count();

        if ($total === 0) {
            return [];
        }

        return [[
            'tipo' => 'a_revisar',
            'nivel' => 'baixo',
            'titulo' => $total === 1 ? '1 conversa para revisar' : "{$total} conversas para revisar",
            'texto' => 'A classificação ficou em dúvida. Confira e corrija antes de contar esses números.',
            'link' => route('gestao.conversas.index', ['a_revisar' => 1]),
        ]];
    }

    /**
     * Próximo horário da rodada (05:00 ou 17:00) estritamente depois de agora, no fuso da gestão.
     */
    private function proximaRodada(CarbonImmutable $agora): CarbonImmutable
    {
        $hoje = $agora->startOfDay();

        foreach ([$hoje, $hoje->addDay()] as $dia) {
            foreach (self::HORARIOS_DA_RODADA as $horario) {
                $momento = $dia->setTimeFromTimeString($horario);

                if ($momento->greaterThan($agora)) {
                    return $momento;
                }
            }
        }

        return $hoje->addDay()->setTimeFromTimeString(self::HORARIOS_DA_RODADA[0]);
    }

    private function contar(int $quantidade, string $singular, string $plural): string
    {
        return $quantidade.' '.($quantidade === 1 ? $singular : $plural);
    }

    private function porcento(int $parte, int $total): ?float
    {
        return $total === 0 ? null : round($parte * 100 / $total, 1);
    }

    public function data(mixed $momento): ?string
    {
        if ($momento === null) {
            return null;
        }

        return CarbonImmutable::instance($momento)->setTimezone($this->fuso())->toDateString();
    }
}
