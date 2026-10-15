<?php

namespace App\Mcp\Tools\Gestao;

use App\Mcp\Resources\ManualDaGestao as RecursoDoManual;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('manual_da_gestao')]
#[Description('Leia primeiro. Explica o app, como as conversas são classificadas (JEV e Haiku), o significado de cada campo (casos, resultados, frustração 0 a 3, a_revisar, temas), qual ferramenta usar para cada pergunta, receitas passo a passo para achar melhorias e os cuidados com a amostra pequena e os dados mascarados.')]
#[IsReadOnly]
#[IsIdempotent]
class ManualDaGestao extends FerramentaDaGestao
{
    public function handle(Request $request): Response
    {
        return Response::text(RecursoDoManual::texto());
    }
}
