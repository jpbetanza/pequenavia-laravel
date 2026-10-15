<?php

namespace App\Mcp\Tools\Gestao;

use App\Services\Gestao\EscopoDaGestao;
use App\Services\Gestao\PainelDeGestao;
use App\Services\Gestao\PeriodoDoPainel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * Base das ferramentas do MCP da gestão. Só aparecem para quem passa no gate "gestao", e reaproveitam os mesmos
 * serviços do painel: os dados pessoais saem mascarados como na tela.
 */
abstract class FerramentaDaGestao extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('gestao') ?? false;
    }

    /**
     * Campos comuns às ferramentas que leem um período.
     *
     * @return array<string, Type>
     */
    protected function camposDoPeriodo(JsonSchema $schema): array
    {
        return [
            'periodo' => $schema->integer()->enum([7, 30, 90])->default(30)
                ->description('Janela em dias, contando hoje (fuso de São Paulo).'),
            'incluir_gestores' => $schema->boolean()->default(false)
                ->description('Inclui as contas de gestão (admin) nos números e listas. Por padrão, só clientes.'),
        ];
    }

    /**
     * Aplica o escopo de contas pedido e devolve o período.
     */
    protected function prepararPeriodo(Request $request): PeriodoDoPainel
    {
        app(EscopoDaGestao::class)->forcar((bool) $request->get('incluir_gestores', false));

        return app(PainelDeGestao::class)->periodo($request->get('periodo'));
    }
}
