<?php

namespace App\Mcp\Tools\Gestao;

use App\Models\Conversa;
use App\Models\User;
use App\Services\Gestao\ConversasDaGestao;
use App\Services\Gestao\EscopoDaGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ver_conversa')]
#[Description('Uma conversa com as mensagens (dados pessoais mascarados), a classificação do JEV, o resumo do Haiku e as correções. Cada leitura fica registrada no histórico de acessos, como no painel.')]
#[IsReadOnly]
class VerConversa extends FerramentaDaGestao
{
    public function handle(Request $request, ConversasDaGestao $conversas, EscopoDaGestao $escopo): Response
    {
        $dados = $request->validate(['conversa_id' => ['required', 'uuid']]);

        $escopo->forcar((bool) $request->get('incluir_gestores', false));

        $conversa = Conversa::query()
            ->whereKey($dados['conversa_id'])
            ->whereIn('user_id', $escopo->ids())
            ->first();

        if ($conversa === null) {
            return Response::error('Conversa não encontrada. Se ela for de uma conta de gestão, chame com incluir_gestores = true.');
        }

        /** @var User $gestor */
        $gestor = $request->user();

        return Response::json($conversas->detalhe($conversa, $gestor));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'conversa_id' => $schema->string()->format('uuid')->required(),
            'incluir_gestores' => $schema->boolean()->default(false)
                ->description('Permite abrir conversas das contas de gestão.'),
        ];
    }
}
