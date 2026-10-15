<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Gestao\AtualizarTema;
use App\Mcp\Tools\Gestao\CorrigirClassificacao;
use App\Mcp\Tools\Gestao\JuntarTemas;
use App\Mcp\Tools\Gestao\ListarClientes;
use App\Mcp\Tools\Gestao\ListarConversas;
use App\Mcp\Tools\Gestao\ListarTemas;
use App\Mcp\Tools\Gestao\ResumoDoPainel;
use App\Mcp\Tools\Gestao\VerCliente;
use App\Mcp\Tools\Gestao\VerConversa;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * MCP do painel de gestão (/mcp/gestao): os mesmos dados de /gestao, para analisar o uso do app e do assistente
 * e decidir melhorias. Só para contas admin, com login por OAuth.
 */
#[Name('Pequena Via - Gestão')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Dados do painel de gestão do Pequena Via, um app para secretarias paroquiais (reservas de espaços e
    equipamentos, grupos, certificados e um assistente de IA). As conversas do assistente são classificadas
    duas vezes por dia (05h e 17h, São Paulo) pelo JEV, com resumo do Haiku para as que não se resolveram.

    Para achar melhorias: comece por resumo_do_painel, veja os pedidos não atendidos em listar_temas e as
    conversas não resolvidas ou frustradas em listar_conversas; abra os detalhes com ver_conversa. Os dados
    pessoais vêm mascarados. Por padrão as contas de gestão ficam de fora (incluir_gestores = true as inclui).

    As ferramentas corrigir_classificacao, atualizar_tema e juntar_temas alteram dados: confirme com o gestor
    antes de usá-las.
    MARKDOWN)]
class GestaoServer extends Server
{
    protected array $tools = [
        ResumoDoPainel::class,
        ListarConversas::class,
        VerConversa::class,
        ListarTemas::class,
        ListarClientes::class,
        VerCliente::class,
        CorrigirClassificacao::class,
        AtualizarTema::class,
        JuntarTemas::class,
    ];
}
