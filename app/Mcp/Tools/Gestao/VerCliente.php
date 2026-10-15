<?php

namespace App\Mcp\Tools\Gestao;

use App\Models\User;
use App\Services\Gestao\EscopoDaGestao;
use App\Services\Gestao\PainelDeGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ver_cliente')]
#[Description('Tudo de uma paróquia, como na página do cliente: situação, configuração, cadastros, reservas, uso do assistente, casos de uso, trava de uso, linha do tempo e conversas recentes.')]
#[IsReadOnly]
class VerCliente extends FerramentaDaGestao
{
    public function handle(Request $request, PainelDeGestao $painel, EscopoDaGestao $escopo): Response
    {
        $dados = $request->validate(['cliente_id' => ['required', 'uuid']]);

        $periodo = $this->prepararPeriodo($request);
        $cliente = User::query()->whereKey($dados['cliente_id'])->first();

        // Gestores só entram quando pedidos, como no painel.
        if ($cliente === null || ($cliente->admin && ! $escopo->incluiGestores())) {
            return Response::error('Cliente não encontrado.');
        }

        return Response::json([
            'periodo' => $periodo->dias,
            'cliente' => $painel->cliente($cliente),
            'situacao' => $painel->situacao($cliente, $periodo),
            'configuracao' => $painel->configuracao($cliente),
            'cadastros' => $painel->cadastros($cliente),
            'reservas' => $painel->reservas($cliente, $periodo),
            'assistente' => $painel->assistente($cliente, $periodo),
            'casos' => $painel->casosDoCliente($cliente, $periodo),
            'trava' => $painel->trava($cliente),
            'linha_do_tempo' => $painel->linhaDoTempo($cliente),
            'conversas' => $painel->conversasRecentes($cliente),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->camposDoPeriodo($schema),
            'cliente_id' => $schema->string()->format('uuid')->required()->description('Id vindo de listar_clientes.'),
        ];
    }
}
