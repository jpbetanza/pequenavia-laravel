<?php

namespace App\Http\Controllers\Agendamentos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\DisponibilidadeRequest;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Services\Agenda\Conflito;
use App\Services\Agenda\VerificadorDeDisponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Confere, por data, se uma reserva candidata cabe. Sugestões são calculadas só nas datas com conflito.
 * Espaço, equipamentos e reserva de outro dono respondem 404.
 */
class DisponibilidadeController extends Controller
{
    public function __invoke(DisponibilidadeRequest $request, VerificadorDeDisponibilidade $verificador): JsonResponse
    {
        $dados = $request->validated();

        $espaco = Espaco::query()->findOrFail($dados['espaco_id']);
        Gate::authorize('view', $espaco);

        $equipamentos = $this->equipamentosDoDono($dados['equipamentos']);
        $ignorarReservaId = $dados['ignorar_reserva_id'] ?? null;

        if ($ignorarReservaId !== null) {
            Gate::authorize('view', Reserva::query()->findOrFail($ignorarReservaId));
        }

        $datas = [];

        foreach ($dados['datas'] as $texto) {
            $data = CarbonImmutable::parse($texto)->startOfDay();
            $conflitos = $verificador->verificar($espaco, $data, $dados['inicio'], $dados['fim'], $equipamentos, $ignorarReservaId);

            $datas[] = [
                'data' => $data->toDateString(),
                'conflitos' => array_map(fn (Conflito $conflito) => $conflito->toArray(), $conflitos),
                'sugestoes' => $conflitos === []
                    ? ['espacos' => [], 'horario' => null]
                    : $verificador->sugestoes($espaco, $data, $dados['inicio'], $dados['fim'], $equipamentos, $ignorarReservaId, $dados['pessoas'] ?? null),
            ];
        }

        return response()->json(['datas' => $datas]);
    }

    /**
     * @param  list<array{id: string, quantidade: int}>  $pedidos
     * @return list<array{id: string, quantidade: int}>
     */
    private function equipamentosDoDono(array $pedidos): array
    {
        $modelos = Equipamento::query()->whereIn('id', array_column($pedidos, 'id'))->get();

        if ($modelos->count() !== count($pedidos)) {
            abort(404);
        }

        $modelos->each(fn (Equipamento $equipamento) => Gate::authorize('view', $equipamento));

        return array_map(
            fn (array $pedido) => ['id' => $pedido['id'], 'quantidade' => (int) $pedido['quantidade']],
            $pedidos,
        );
    }
}
