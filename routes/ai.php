<?php

use App\Mcp\Servers\GestaoServer;
use Laravel\Mcp\Facades\Mcp;

// Descoberta do OAuth e registro dinâmico de clientes (claude.ai, Claude Code).
Mcp::oauthRoutes();

Mcp::web('/mcp/gestao', GestaoServer::class)
    ->middleware(['auth:api', 'can:gestao', 'throttle:120,1']);
