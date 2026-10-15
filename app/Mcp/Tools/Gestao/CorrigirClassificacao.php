<?php

namespace App\Mcp\Tools\Gestao;

use App\Enums\CasoDeUso;
use App\Enums\ResultadoDaConversa;
use App\Http\Requests\Gestao\CorrigirClassificacaoRequest;
use App\Models\ClassificacaoDeConversa;
use App\Services\Gestao\EscopoDaGestao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('corrigir_classificacao')]
#[Description('Corrige a classificação de uma conversa, como o botão "Corrigir classificação" do painel. Mande só os campos que mudam. Nulo em caso_de_uso ou resultado volta ao valor do JEV; nulo em tema_id quer dizer "nenhum tema". A conversa sai da fila de revisão.')]
#[IsIdempotent]
class CorrigirClassificacao extends FerramentaDaGestao
{
    private const CAMPOS = ['caso_de_uso', 'resultado', 'tema_id'];

    public function handle(Request $request, EscopoDaGestao $escopo): Response
    {
        $regras = new CorrigirClassificacaoRequest;

        $dados = $request->validate(
            ['conversa_id' => ['required', 'uuid'], ...$regras->rules()],
            $regras->messages(),
        );

        $correcao = Arr::only($dados, self::CAMPOS);

        if ($correcao === []) {
            return Response::error('Informe ao menos um campo para corrigir: caso_de_uso, resultado ou tema_id.');
        }

        $escopo->forcar((bool) $request->get('incluir_gestores', false));

        $classificacao = ClassificacaoDeConversa::query()
            ->where('conversa_id', $dados['conversa_id'])
            ->whereIn('user_id', $escopo->ids())
            ->first();

        if ($classificacao === null) {
            return Response::error('Essa conversa não tem classificação (ou é de uma conta de gestão e incluir_gestores está desligado).');
        }

        $classificacao->correcao = array_merge($classificacao->correcao ?? [], $correcao);
        $classificacao->a_revisar = false;
        $classificacao->save();

        return Response::json([
            'conversa_id' => $classificacao->conversa_id,
            'caso_de_uso' => $classificacao->casoEfetivo()->value,
            'resultado' => $classificacao->resultadoEfetivo()->value,
            'tema_id' => $classificacao->temaEfetivoId(),
            'correcao' => $classificacao->correcao,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'conversa_id' => $schema->string()->format('uuid')->required(),
            'caso_de_uso' => $schema->string()->enum(array_column(CasoDeUso::cases(), 'value'))->nullable(),
            'resultado' => $schema->string()->enum(array_column(ResultadoDaConversa::cases(), 'value'))->nullable(),
            'tema_id' => $schema->string()->format('uuid')->nullable()->description('Id de um tema ativo (veja listar_temas).'),
            'incluir_gestores' => $schema->boolean()->default(false)
                ->description('Permite corrigir conversas das contas de gestão.'),
        ];
    }
}
