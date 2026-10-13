<?php

namespace App\Http\Controllers\Agendamentos;

use App\Enums\StatusSolicitacao;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\ListarSolicitacoesRequest;
use App\Http\Requests\Agendamentos\RecusarSolicitacaoRequest;
use App\Http\Requests\Agendamentos\UpdateSolicitacaoRequest;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Agenda\CartaoDeSolicitacao;
use App\Services\Agenda\RespostaASolicitacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Solicitações da agenda do usuário: lista, edição, aprovação e recusa. Solicitação de outro dono responde 404.
 *
 * Só as pendentes podem ser editadas, aprovadas ou recusadas (422 caso contrário). A aprovação revalida tudo com o
 * verificador, numa transação com a agenda travada, e com conflito não cria nada.
 */
class SolicitacaoController extends Controller
{
    public function __construct(
        private readonly CartaoDeSolicitacao $cartoes,
        private readonly RespostaASolicitacao $respostas,
    ) {}

    /**
     * Lista as solicitações do status pedido (pendentes por padrão), com as contagens das três abas, o link do formulário
     * e os espaços e equipamentos para edição.
     */
    public function index(ListarSolicitacoesRequest $request): Response
    {
        $user = $request->user();
        $status = StatusSolicitacao::from($request->validated('status') ?? StatusSolicitacao::Pendente->value);

        $solicitacoes = Solicitacao::query()
            ->where('user_id', $user->id)
            ->where('status', $status->value)
            ->with(['grupo:id,nome', 'espaco', 'equipamentos'])
            ->orderBy('data')
            ->orderBy('inicio')
            ->get();

        $porStatus = Solicitacao::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('agendamentos/solicitacoes', [
            'link_do_formulario' => route('reservar.create', $user->agendaSlug()),
            'contagens' => collect(StatusSolicitacao::cases())
                ->mapWithKeys(fn (StatusSolicitacao $item) => [$item->value => (int) ($porStatus[$item->value] ?? 0)])
                ->all(),
            'status' => $status->value,
            'solicitacoes' => $solicitacoes->map(fn (Solicitacao $solicitacao) => $this->cartoes->montar($solicitacao))->values()->all(),
            'espacos' => Espaco::query()
                ->where('user_id', $user->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(fn (Espaco $espaco) => ['id' => $espaco->id, 'nome' => $espaco->nome, 'capacidade' => $espaco->capacidade])
                ->values()
                ->all(),
            'equipamentos' => Equipamento::query()
                ->where('user_id', $user->id)
                ->orderBy('nome')
                ->get()
                ->map(fn (Equipamento $equipamento) => [
                    'id' => $equipamento->id,
                    'nome' => $equipamento->nome,
                    'quantidade' => $equipamento->quantidade,
                    'manutencao_ate' => $equipamento->manutencao_ate?->toDateString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Edita espaço, data, horário, pessoas e equipamentos da solicitação pendente. Responde o cartão recalculado em JSON
     * quando a requisição espera JSON; senão volta para a página anterior.
     */
    public function update(UpdateSolicitacaoRequest $request, Solicitacao $solicitacao): JsonResponse|RedirectResponse
    {
        $dados = $request->validated();
        $user = $request->user();
        $espacoId = $dados['espaco_id'] ?? null;

        if ($espacoId !== null) {
            $this->espacoDoDono($espacoId);
        }

        $equipamentos = $this->equipamentosDoDono($dados['equipamentos']);

        DB::transaction(function () use ($user, $solicitacao, $dados, $espacoId, $equipamentos): void {
            $this->travarAgendaDo($user);
            $solicitacao->refresh();
            $this->respostas->exigirPendente($solicitacao, 'Só é possível editar uma solicitação pendente.');

            $solicitacao->forceFill([
                'espaco_id' => $espacoId,
                'data' => $dados['data'],
                'inicio' => $dados['inicio'],
                'fim' => $dados['fim'],
                'pessoas' => $dados['pessoas'] ?? null,
            ])->save();

            $solicitacao->equipamentos()->sync($this->pivotDosEquipamentos($equipamentos));
        });

        $solicitacao->refresh();

        if ($request->expectsJson()) {
            return response()->json($this->cartoes->montar($solicitacao));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitação atualizada.']);

        return back();
    }

    /**
     * Cria a reserva com os equipamentos e vincula. Sem espaço ou com conflito, responde 422 e nada é criado.
     */
    public function aprovar(Request $request, Solicitacao $solicitacao): RedirectResponse
    {
        Gate::authorize('update', $solicitacao);

        $this->respostas->aprovar($request->user(), $solicitacao);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitação aprovada e reserva criada.']);

        return to_route('agendamentos.solicitacoes.index');
    }

    /**
     * Recusa a solicitação pendente, com motivo opcional.
     */
    public function recusar(RecusarSolicitacaoRequest $request, Solicitacao $solicitacao): RedirectResponse
    {
        $this->respostas->recusar($request->user(), $solicitacao, $request->validated('motivo_recusa'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitação recusada.']);

        return to_route('agendamentos.solicitacoes.index');
    }

    /**
     * Trava a linha do dono até o fim da transação, para que a aprovação e a edição não se cruzem com outra gravação da agenda.
     */
    private function travarAgendaDo(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
    }

    private function espacoDoDono(string $id): Espaco
    {
        $espaco = Espaco::query()->findOrFail($id);
        Gate::authorize('view', $espaco);

        return $espaco;
    }

    /**
     * Equipamentos pedidos, todos do dono. Um equipamento de outro dono ou inexistente responde 404.
     *
     * @param  list<array{id: string, quantidade: int|string}>  $pedidos
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

    /**
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return array<string, array{quantidade: int}>
     */
    private function pivotDosEquipamentos(array $equipamentos): array
    {
        return collect($equipamentos)
            ->mapWithKeys(fn (array $equipamento) => [$equipamento['id'] => ['quantidade' => $equipamento['quantidade']]])
            ->all();
    }
}
