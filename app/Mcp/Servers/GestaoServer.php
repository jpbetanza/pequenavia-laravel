<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\AnalisarMelhorias;
use App\Mcp\Resources\ManualDaGestao as RecursoDoManual;
use App\Mcp\Tools\Gestao\AtualizarTema;
use App\Mcp\Tools\Gestao\CorrigirClassificacao;
use App\Mcp\Tools\Gestao\JuntarTemas;
use App\Mcp\Tools\Gestao\ListarClientes;
use App\Mcp\Tools\Gestao\ListarConversas;
use App\Mcp\Tools\Gestao\ListarTemas;
use App\Mcp\Tools\Gestao\ManualDaGestao;
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
    Dados do painel de gestão do Pequena Via, app para secretarias paroquiais (agenda e reservas de espaços e
    equipamentos, grupos, solicitações, certificados e um assistente de IA). Cada cliente é uma paróquia. O
    objetivo é descobrir o que melhorar no app e no assistente.

    Antes de analisar, chame manual_da_gestao (ou leia o recurso gestao://manual): ele explica os campos
    (casos de uso, resultados, frustração de 0 a 3, a_revisar, temas), qual ferramenta usar para cada pergunta
    e receitas passo a passo. O prompt analisar_melhorias traz um roteiro pronto.

    Caminho curto: resumo_do_painel, depois listar_temas (pedidos não atendidos, priorize por número de
    paróquias), depois listar_conversas (nao_resolvida, frustrada) e ver_conversa nas mais representativas.

    Regras: os dados pessoais vêm mascarados, não tente reconstruí-los. A amostra ainda é pequena: informe
    contagens, não só porcentagens. ver_conversa registra o acesso. corrigir_classificacao, atualizar_tema e
    juntar_temas alteram dados: proponha e só execute depois que o gestor confirmar.
    MARKDOWN)]
class GestaoServer extends Server
{
    protected array $tools = [
        ManualDaGestao::class,
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

    protected array $resources = [
        RecursoDoManual::class,
    ];

    protected array $prompts = [
        AnalisarMelhorias::class,
    ];
}
