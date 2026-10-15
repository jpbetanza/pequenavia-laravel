<?php

namespace App\Mcp\Tools\Gestao;

use App\Models\TemaDePedido;
use App\Services\Gestao\TemasDaGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('juntar_temas')]
#[Description('Junta um tema em outro quando os dois são o mesmo pedido. As citações passam a contar no destino e o tema de origem deixa de aparecer. Não dá para desfazer pelo MCP.')]
#[IsDestructive]
class JuntarTemas extends FerramentaDaGestao
{
    public function handle(Request $request, TemasDaGestao $temas): Response
    {
        $dados = $request->validate([
            'tema_id' => ['required', 'uuid', 'different:destino_id'],
            'destino_id' => ['required', 'uuid'],
        ], [
            'tema_id.different' => 'Escolha outro tema: não dá para juntar um tema nele mesmo.',
        ]);

        $origem = TemaDePedido::query()->whereKey($dados['tema_id'])->first();
        $destino = TemaDePedido::query()->ativos()->whereKey($dados['destino_id'])->first();

        if ($origem === null) {
            return Response::error('Tema de origem não encontrado.');
        }

        if ($origem->juntado_em_id !== null) {
            return Response::error('Este tema já foi juntado a outro.');
        }

        if ($destino === null) {
            return Response::error('O tema de destino não existe ou não está ativo.');
        }

        $temas->juntar($origem, $destino);

        return Response::text("Tema \"{$origem->nome}\" juntado em \"{$destino->nome}\".");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tema_id' => $schema->string()->format('uuid')->required()->description('Tema que vai sumir.'),
            'destino_id' => $schema->string()->format('uuid')->required()->description('Tema que fica, ativo.'),
        ];
    }
}
