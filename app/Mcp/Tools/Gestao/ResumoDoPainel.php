<?php

namespace App\Mcp\Tools\Gestao;

use App\Services\Gestao\PainelDeGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('resumo_do_painel')]
#[Description('Visão geral do período: números (paróquias, conversas, resolvidas, ações, custos), conversas por dia, casos de uso, alertas e a última rodada de classificação. Comece por aqui.')]
#[IsReadOnly]
class ResumoDoPainel extends FerramentaDaGestao
{
    public function handle(Request $request, PainelDeGestao $painel): Response
    {
        $periodo = $this->prepararPeriodo($request);

        return Response::json([
            'periodo' => $periodo->dias,
            'resumo' => $painel->resumo($periodo),
            'por_dia' => $painel->porDia($periodo),
            'casos' => $painel->casos($periodo),
            'alertas' => $painel->alertas($periodo),
            'classificacao' => $painel->classificacao(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->camposDoPeriodo($schema);
    }
}
