<?php

namespace App\Mcp\Tools\Gestao;

use App\Services\Gestao\PainelDeGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('listar_clientes')]
#[Description('Paróquias com a atividade do período (reservas, conversas, % resolvidas, configuração e último acesso), da mais ativa para a menos. Aceita busca por nome ou e-mail.')]
#[IsReadOnly]
class ListarClientes extends FerramentaDaGestao
{
    public function handle(Request $request, PainelDeGestao $painel): Response
    {
        $dados = $request->validate(['busca' => ['nullable', 'string', 'max:100']]);

        $periodo = $this->prepararPeriodo($request);

        return Response::json([
            'periodo' => $periodo->dias,
            'clientes' => $painel->clientes($periodo, $dados['busca'] ?? null),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->camposDoPeriodo($schema),
            'busca' => $schema->string()->max(100)->description('Parte do nome ou do e-mail.'),
        ];
    }
}
