<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * O manual do MCP da gestão, em resources/mcp/manual-da-gestao.md. Também sai pela ferramenta manual_da_gestao,
 * para clientes que não leem recursos.
 */
#[Name('manual_da_gestao')]
#[Uri('gestao://manual')]
#[MimeType('text/markdown')]
#[Description('Manual do MCP da gestão: o que é o app, como as conversas são classificadas, o significado de cada campo e receitas para achar melhorias.')]
class ManualDaGestao extends Resource
{
    public static function texto(): string
    {
        return (string) file_get_contents(resource_path('mcp/manual-da-gestao.md'));
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('gestao') ?? false;
    }

    public function handle(Request $request): Response
    {
        return Response::text(self::texto());
    }
}
