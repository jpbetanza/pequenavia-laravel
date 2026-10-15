<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('analisar_melhorias')]
#[Description('Roteiro pronto: analisa o uso do assistente no período e devolve uma lista priorizada de melhorias para o app, com evidências.')]
class AnalisarMelhorias extends Prompt
{
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('gestao') ?? false;
    }

    public function handle(Request $request): Response
    {
        $periodo = in_array((int) $request->get('periodo'), [7, 30, 90], true) ? (int) $request->get('periodo') : 30;
        $foco = trim((string) $request->get('foco', ''));

        $texto = <<<TEXTO
            Analise o uso do Pequena Via nos últimos {$periodo} dias e proponha melhorias para o app e para o assistente.

            1. Leia manual_da_gestao, se ainda não leu nesta conversa.
            2. Rode resumo_do_painel (periodo {$periodo}).
            3. Rode listar_temas (periodo {$periodo}) e priorize pelo número de paróquias diferentes.
            4. Rode listar_conversas com resultado nao_resolvida e depois com frustrada true; abra de 3 a 5 conversas representativas com ver_conversa.
            5. Separe o que achou em: funcionalidade que falta, falha do assistente, problema de usabilidade.

            Entregue uma lista priorizada (no máximo 7 itens). Para cada um: o problema, a evidência (ids das conversas, paróquias, citações), a melhoria proposta e o esforço estimado (pequeno, médio, grande). Diga o tamanho da amostra. Não altere nada (sem corrigir_classificacao, atualizar_tema ou juntar_temas) sem eu confirmar.
            TEXTO;

        if ($foco !== '') {
            $texto .= "\n\nFoco desta análise: {$foco}.";
        }

        return Response::text($texto);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('periodo', 'Janela em dias: 7, 30 ou 90. Padrão 30.', required: false),
            new Argument('foco', 'Opcional: um caso de uso, um cliente ou um tema para olhar com mais atenção.', required: false),
        ];
    }
}
