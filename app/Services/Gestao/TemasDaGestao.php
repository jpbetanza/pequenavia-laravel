<?php

namespace App\Services\Gestao;

use App\Enums\SituacaoDoTema;
use App\Models\ClassificacaoDeConversa;
use App\Models\RodadaDeClassificacao;
use App\Models\TemaDePedido;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Temas de pedido do painel de gestão: a rodada mais recente, a lista de temas ativos com as contagens do período
 * e os últimos sete dias, o tema selecionado com exemplos, e a junção de dois temas.
 */
final class TemasDaGestao
{
    private const DIAS_DA_SEMANA = 7;

    public function __construct(
        private readonly PainelDeGestao $painel,
        private readonly EscopoDaGestao $escopo,
    ) {}

    /**
     * @return array{iniciada_em: string|null, terminada_em: string|null, conversas_lidas: int, resumidas: int, temas_novos: int, falhas: int, tokens_jev: int, custo_jev: float, custo_haiku: float, erro: string|null}|null
     */
    public function rodada(): ?array
    {
        $rodada = RodadaDeClassificacao::query()->latest('iniciada_em')->first();

        if ($rodada === null) {
            return null;
        }

        return [
            'iniciada_em' => $this->iso($rodada->iniciada_em),
            'terminada_em' => $this->iso($rodada->terminada_em),
            'conversas_lidas' => $rodada->conversas_lidas,
            'resumidas' => $rodada->resumidas,
            'temas_novos' => $rodada->temas_novos,
            'falhas' => $rodada->falhas,
            'tokens_jev' => $rodada->tokens_jev,
            'custo_jev' => (float) $rodada->custo_jev,
            'custo_haiku' => (float) $rodada->custo_haiku,
            'erro' => $rodada->erro,
        ];
    }

    /**
     * Temas ativos (não juntados e não descartados), do que mais aparece no período para o que menos aparece.
     *
     * @return list<array<string, mixed>>
     */
    public function temas(PeriodoDoPainel $periodo): array
    {
        $agora = CarbonImmutable::now();

        $noPeriodo = $this->contagens($periodo->inicio, $periodo->fim);
        $semana = $this->contagens($agora->subDays(self::DIAS_DA_SEMANA), null);
        $semanaAnterior = $this->contagens($agora->subDays(2 * self::DIAS_DA_SEMANA), $agora->subDays(self::DIAS_DA_SEMANA));

        $temas = TemaDePedido::query()->ativos()->orderBy('nome')->get()
            ->map(fn (TemaDePedido $tema): array => $this->formatar(
                $tema,
                $noPeriodo->get($tema->id),
                $semana->get($tema->id),
                $semanaAnterior->get($tema->id),
            ))
            ->all();

        usort($temas, fn (array $a, array $b): int => $b['conversas'] <=> $a['conversas']);

        return $temas;
    }

    /**
     * O tema pedido em ?tema= (se estiver entre os ativos) ou o primeiro da lista, com os três resumos mais recentes.
     *
     * @return array{tema: array<string, mixed>, exemplos: list<array{resumo: string, paroquia: string, data: string|null}>}|null
     */
    public function selecionado(PeriodoDoPainel $periodo, ?string $temaId): ?array
    {
        $temas = $this->temas($periodo);

        if ($temas === []) {
            return null;
        }

        $tema = collect($temas)->firstWhere('id', $temaId) ?? $temas[0];

        $exemplos = DB::table('classificacoes_de_conversa')
            ->join('users', 'users.id', '=', 'classificacoes_de_conversa.user_id')
            ->whereRaw('('.PainelDeGestao::TEMA.') = ?', [$tema['id']])
            ->whereNotNull('classificacoes_de_conversa.resumo')
            ->orderByDesc('classificacoes_de_conversa.classificada_em')
            ->limit(3)
            ->get(['classificacoes_de_conversa.resumo', 'classificacoes_de_conversa.classificada_em', 'users.name']);

        return [
            'tema' => $tema,
            'exemplos' => array_values($exemplos->map(fn (object $linha): array => [
                'resumo' => (string) $linha->resumo,
                'paroquia' => (string) $linha->name,
                'data' => $this->painel->data(CarbonImmutable::parse((string) $linha->classificada_em, 'UTC')),
            ])->all()),
        ];
    }

    /**
     * Junta o tema de origem no de destino: as classificações (valor do JEV ou correção) passam para o destino, e a
     * origem fica como descartada, apontando para o destino.
     */
    public function juntar(TemaDePedido $origem, TemaDePedido $destino): void
    {
        DB::transaction(function () use ($origem, $destino): void {
            ClassificacaoDeConversa::query()
                ->where('tema_id', $origem->id)
                ->update(['tema_id' => $destino->id]);

            // A correção guarda o tema escolhido pelo gestor; ela também tem que seguir para o destino.
            ClassificacaoDeConversa::query()
                ->whereRaw("correcao->>'tema_id' = ?", [$origem->id])
                ->get()
                ->each(function (ClassificacaoDeConversa $classificacao) use ($destino): void {
                    $classificacao->correcao = array_merge($classificacao->correcao ?? [], ['tema_id' => $destino->id]);
                    $classificacao->save();
                });

            $origem->update([
                'juntado_em_id' => $destino->id,
                'situacao' => SituacaoDoTema::Descartado,
            ]);
        });
    }

    /**
     * Contagens por tema efetivo numa janela (fim aberto quando null). Parcelas por paróquia e frustração alta.
     *
     * @return Collection<int|string, \stdClass>
     */
    private function contagens(CarbonImmutable $de, ?CarbonImmutable $ate): Collection
    {
        $consulta = DB::table('classificacoes_de_conversa')
            ->whereIn('user_id', $this->escopo->ids())
            ->whereRaw('('.PainelDeGestao::TEMA.') IS NOT NULL')
            ->where('classificada_em', '>=', $de->utc());

        if ($ate !== null) {
            $consulta->where('classificada_em', '<', $ate->utc());
        }

        return $consulta
            ->selectRaw(
                PainelDeGestao::TEMA.' AS tema_id, count(*) AS conversas, count(distinct user_id) AS paroquias, '
                .'sum(case when frustracao >= ? then 1 else 0 end) AS frustradas',
                [PainelDeGestao::FRUSTRACAO_ALTA],
            )
            ->groupByRaw(PainelDeGestao::TEMA)
            ->get()
            ->keyBy('tema_id');
    }

    /**
     * @param  object|null  $noPeriodo  linha de contagens do período
     * @param  object|null  $semana  linha de contagens da semana
     * @param  object|null  $semanaAnterior  linha de contagens da semana anterior
     * @return array<string, mixed>
     */
    private function formatar(TemaDePedido $tema, ?object $noPeriodo, ?object $semana, ?object $semanaAnterior): array
    {
        return [
            'id' => $tema->id,
            'nome' => $tema->nome,
            'descricao' => $tema->descricao,
            'situacao' => $tema->situacao->value,
            'situacao_rotulo' => $tema->situacao->rotulo(),
            'criado_por' => $tema->criado_por,
            'criado_em' => $this->painel->data($tema->created_at),
            'conversas' => (int) ($noPeriodo->conversas ?? 0),
            'paroquias' => (int) ($noPeriodo->paroquias ?? 0),
            'frustradas' => (int) ($noPeriodo->frustradas ?? 0),
            'semana' => (int) ($semana->conversas ?? 0),
            'semana_anterior' => (int) ($semanaAnterior->conversas ?? 0),
        ];
    }

    private function iso(mixed $momento): ?string
    {
        if ($momento === null) {
            return null;
        }

        return CarbonImmutable::instance($momento)->setTimezone((string) config('gestao.fuso'))->toIso8601String();
    }
}
