<?php

namespace App\Http\Controllers\Assistente;

use App\Enums\StatusDaAcao;
use App\Http\Controllers\Controller;
use App\Models\AcaoProposta;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\User;
use App\Services\Agenda\CriadorDeReservas;
use App\Services\Assistente\Cadastros\ExecutorDeCadastro;
use App\Services\Assistente\Operacoes\ExecutorDeOperacao;
use App\Services\Assistente\Operacoes\Operacoes;
use App\Services\Assistente\ResumoDaAcao;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Confirma ou descarta uma ação que o assistente propôs. Ação de outro usuário responde 404.
 *
 * A ação é travada na transação: um duplo clique não cria duas reservas, e um conflito desfaz a confirmação.
 */
class AcaoPropostaController extends Controller
{
    public function __construct(
        private readonly CriadorDeReservas $criador,
        private readonly ResumoDaAcao $resumo,
        private readonly ExecutorDeCadastro $cadastros,
        private readonly ExecutorDeOperacao $operacoes,
    ) {}

    public function confirmar(Request $request, AcaoProposta $acao): JsonResponse
    {
        Gate::authorize('confirmar', $acao);

        DB::transaction(function () use ($request, $acao): void {
            $pendente = $this->pendenteTravada($acao);
            $usuario = $request->user();

            if (Operacoes::ehOperacao($pendente->tipo)) {
                $pendente->update([
                    'status' => StatusDaAcao::Confirmada,
                    'resultado_id' => $this->operacoes->executar($usuario, $pendente),
                    'confirmada_em' => now(),
                ]);

                return;
            }

            if ($pendente->tipo !== 'criar_reserva') {
                $cadastroId = $this->cadastros->executar($usuario, $pendente);

                $pendente->update([
                    'status' => StatusDaAcao::Confirmada,
                    'resultado_id' => $cadastroId,
                    'confirmada_em' => now(),
                ]);

                return;
            }

            $dados = $pendente->dados;

            $espaco = Espaco::query()->where('user_id', $usuario->id)->whereKey($dados['espaco_id'])->first()
                ?? $this->recusar('O espaço não existe mais.');
            $grupo = isset($dados['grupo_id'])
                ? (Grupo::query()->where('user_id', $usuario->id)->whereKey($dados['grupo_id'])->first() ?? $this->recusar('O grupo não existe mais.'))
                : null;
            $equipamentos = $this->equipamentosAtuais($usuario, $dados['equipamentos'] ?? []);
            $data = CarbonImmutable::createFromFormat('Y-m-d', $dados['data'], config('agendamentos.fuso'))->startOfDay();

            $reservas = $this->criador->criar($usuario, $espaco, $grupo, $dados, [$data], $equipamentos);

            $pendente->update([
                'status' => StatusDaAcao::Confirmada,
                'resultado_id' => $reservas[0]->id,
                'confirmada_em' => now(),
            ]);
        });

        return response()->json(['acao' => $this->resumo->paraCartao($acao->fresh())]);
    }

    public function descartar(AcaoProposta $acao): JsonResponse
    {
        Gate::authorize('descartar', $acao);

        DB::transaction(function () use ($acao): void {
            $this->pendenteTravada($acao)->update(['status' => StatusDaAcao::Descartada]);
        });

        return response()->json(['acao' => $this->resumo->paraCartao($acao->fresh())]);
    }

    /**
     * Trava a ação e confere que ainda está pendente. Precisa rodar dentro de uma transação.
     */
    private function pendenteTravada(AcaoProposta $acao): AcaoProposta
    {
        $travada = AcaoProposta::query()->whereKey($acao->id)->lockForUpdate()->firstOrFail();

        if ($travada->status !== StatusDaAcao::Proposta) {
            $this->recusar('Esta ação não está mais pendente.', ['acao' => $this->resumo->paraCartao($travada)]);
        }

        return $travada;
    }

    /**
     * Equipamentos pedidos na proposta, ainda do usuário. Um que sumiu impede a confirmação.
     *
     * @param  list<array{id: string, quantidade: int}>  $pedidos
     * @return list<array{id: string, quantidade: int}>
     */
    private function equipamentosAtuais(User $usuario, array $pedidos): array
    {
        if ($pedidos === []) {
            return [];
        }

        $existentes = Equipamento::query()
            ->where('user_id', $usuario->id)
            ->whereIn('id', array_column($pedidos, 'id'))
            ->count();

        if ($existentes !== count($pedidos)) {
            $this->recusar('Um dos equipamentos não existe mais.');
        }

        return array_map(fn (array $pedido) => ['id' => $pedido['id'], 'quantidade' => (int) $pedido['quantidade']], $pedidos);
    }

    /**
     * @param  array<string, mixed>  $extras  Campos extras do JSON, como o cartão atual da ação.
     */
    private function recusar(string $mensagem, array $extras = []): never
    {
        abort(response()->json(['message' => $mensagem, ...$extras], 422));
    }
}
