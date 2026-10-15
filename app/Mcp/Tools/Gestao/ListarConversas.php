<?php

namespace App\Mcp\Tools\Gestao;

use App\Enums\CasoDeUso;
use App\Enums\ResultadoDaConversa;
use App\Services\Gestao\ConversasDaGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('listar_conversas')]
#[Description('Conversas do assistente classificadas no período, 25 por página, com caso de uso, resultado, frustração, tema e resumo. Traz também a matriz de casos de uso por resultado e as opções de filtro (casos, resultados, clientes e temas, com ids).')]
#[IsReadOnly]
class ListarConversas extends FerramentaDaGestao
{
    public function handle(Request $request, ConversasDaGestao $conversas): Response
    {
        $dados = $request->validate([
            'caso' => ['nullable', 'string'],
            'resultado' => ['nullable', 'string'],
            'cliente_id' => ['nullable', 'uuid'],
            'frustrada' => ['nullable', 'boolean'],
            'a_revisar' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $periodo = $this->prepararPeriodo($request);

        $filtros = [
            'caso' => CasoDeUso::tryFrom((string) ($dados['caso'] ?? '')),
            'resultado' => ResultadoDaConversa::tryFrom((string) ($dados['resultado'] ?? '')),
            'cliente' => $dados['cliente_id'] ?? null,
            'frustrada' => (bool) ($dados['frustrada'] ?? false),
            'a_revisar' => (bool) ($dados['a_revisar'] ?? false),
        ];

        $pagina = $conversas->lista($periodo, $filtros, (int) ($dados['pagina'] ?? 1));

        return Response::json([
            'periodo' => $periodo->dias,
            'conversas' => $pagina->items(),
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
            'a_revisar_total' => $conversas->aRevisarTotal(),
            'matriz' => $conversas->matriz($periodo, $filtros['cliente']),
            'opcoes' => $conversas->opcoes(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->camposDoPeriodo($schema),
            'caso' => $schema->string()->enum(array_column(CasoDeUso::cases(), 'value'))->description('Caso de uso efetivo.'),
            'resultado' => $schema->string()->enum(array_column(ResultadoDaConversa::cases(), 'value'))->description('Resultado efetivo.'),
            'cliente_id' => $schema->string()->format('uuid')->description('Id do cliente (veja opcoes.clientes).'),
            'frustrada' => $schema->boolean()->description('Só conversas com frustração alta.'),
            'a_revisar' => $schema->boolean()->description('Só a fila de revisão.'),
            'pagina' => $schema->integer()->min(1)->default(1),
        ];
    }
}
