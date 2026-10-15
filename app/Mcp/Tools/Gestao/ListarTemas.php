<?php

namespace App\Mcp\Tools\Gestao;

use App\Services\Gestao\TemasDaGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('listar_temas')]
#[Description('Pedidos não atendidos agrupados em temas, com quantas vezes e por quantas paróquias foram citados no período, e a última rodada. Com tema_id, traz também o detalhe do tema e as conversas que o citam. É a melhor fonte para decidir o que construir no app.')]
#[IsReadOnly]
class ListarTemas extends FerramentaDaGestao
{
    public function handle(Request $request, TemasDaGestao $temas): Response
    {
        $dados = $request->validate(['tema_id' => ['nullable', 'uuid']]);

        $periodo = $this->prepararPeriodo($request);

        return Response::json([
            'periodo' => $periodo->dias,
            'rodada' => $temas->rodada(),
            'temas' => $temas->temas($periodo),
            'selecionado' => $temas->selecionado($periodo, $dados['tema_id'] ?? null),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->camposDoPeriodo($schema),
            'tema_id' => $schema->string()->format('uuid')->description('Abre o detalhe deste tema.'),
        ];
    }
}
