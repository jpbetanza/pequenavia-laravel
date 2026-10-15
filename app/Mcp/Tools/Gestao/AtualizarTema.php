<?php

namespace App\Mcp\Tools\Gestao;

use App\Enums\SituacaoDoTema;
use App\Models\TemaDePedido;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('atualizar_tema')]
#[Description('Edita um tema de pedido: nome, descrição ou situação (novo, acompanhando, planejado, descartado). Mande só o que muda. Use "planejado" quando decidir construir algo e "descartado" quando não for fazer.')]
#[IsIdempotent]
class AtualizarTema extends FerramentaDaGestao
{
    public function handle(Request $request): Response
    {
        $tema = TemaDePedido::query()->whereKey((string) $request->get('tema_id'))->first();

        if ($tema === null) {
            return Response::error('Tema não encontrado.');
        }

        $dados = $request->validate([
            'tema_id' => ['required', 'uuid'],
            'nome' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('temas_de_pedido', 'nome')->ignore($tema->id)],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:300'],
            'situacao' => ['sometimes', 'required', Rule::enum(SituacaoDoTema::class)],
        ], [
            'nome.unique' => 'Já existe um tema com esse nome.',
        ]);

        $mudancas = Arr::only($dados, ['nome', 'descricao', 'situacao']);

        if ($mudancas === []) {
            return Response::error('Informe ao menos um campo: nome, descricao ou situacao.');
        }

        $tema->update($mudancas);

        return Response::json($tema->only(['id', 'nome', 'descricao', 'situacao']));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tema_id' => $schema->string()->format('uuid')->required(),
            'nome' => $schema->string()->max(80),
            'descricao' => $schema->string()->max(300)->nullable(),
            'situacao' => $schema->string()->enum(array_column(SituacaoDoTema::cases(), 'value')),
        ];
    }
}
