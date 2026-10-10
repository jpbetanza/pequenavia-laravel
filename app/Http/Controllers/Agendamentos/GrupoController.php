<?php

namespace App\Http\Controllers\Agendamentos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\StoreGrupoRequest;
use App\Http\Requests\Agendamentos\UpdateGrupoRequest;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Grupos e pastorais da agenda do usuário. Grupo de outro dono responde 404.
 */
class GrupoController extends Controller
{
    /** Dias da semana abreviados em pt-BR, indexados pelo `dayOfWeek` do Carbon (0 = domingo). */
    private const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /** Quantas reuniões fixas aparecem na tabela por grupo. */
    private const REUNIOES_POR_GRUPO = 2;

    /**
     * Lista os grupos do dono por nome, com as reuniões fixas derivadas das séries futuras.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $grupos = Grupo::query()
            ->where('user_id', $user->id)
            ->orderBy('nome')
            ->get();

        $reunioesPorGrupo = $this->reunioesFixasPorGrupo($user, $grupos);

        return Inertia::render('agendamentos/grupos/index', [
            'grupos' => $grupos->map(fn (Grupo $grupo) => [
                'id' => $grupo->id,
                'nome' => $grupo->nome,
                'coordenacao' => $grupo->coordenacao,
                'contato' => $grupo->contato,
                'observacao' => $grupo->observacao,
                'ativo' => $grupo->ativo,
                'reunioes' => $reunioesPorGrupo[$grupo->id] ?? [],
            ])->values(),
        ]);
    }

    /**
     * Cria o grupo do dono logado.
     */
    public function store(StoreGrupoRequest $request): RedirectResponse
    {
        $grupo = new Grupo($request->validated());
        $grupo->user()->associate($request->user());
        $grupo->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupo criado.']);

        return to_route('agendamentos.grupos.index');
    }

    /**
     * Atualiza os dados do grupo.
     */
    public function update(UpdateGrupoRequest $request, Grupo $grupo): RedirectResponse
    {
        $grupo->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupo atualizado.']);

        return to_route('agendamentos.grupos.index');
    }

    /**
     * Exclui o grupo. As reservas dele continuam na agenda, sem grupo (a FK zera o campo).
     */
    public function destroy(Grupo $grupo): RedirectResponse
    {
        Gate::authorize('delete', $grupo);

        $grupo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupo excluído.']);

        return to_route('agendamentos.grupos.index');
    }

    /**
     * Uma consulta só para todos os grupos: pega as reservas futuras com série, uma por série e no máximo duas por grupo.
     *
     * @param  Collection<int, Grupo>  $grupos
     * @return array<string, list<string>>
     */
    private function reunioesFixasPorGrupo(User $user, Collection $grupos): array
    {
        if ($grupos->isEmpty()) {
            return [];
        }

        $hoje = now(config('agendamentos.fuso'))->toDateString();

        return Reserva::query()
            ->where('user_id', $user->id)
            ->whereIn('grupo_id', $grupos->modelKeys())
            ->whereNotNull('serie_id')
            ->where('data', '>=', $hoje)
            ->with('espaco:id,nome')
            ->orderBy('data')
            ->orderBy('inicio')
            ->get()
            ->groupBy('grupo_id')
            ->map(fn (Collection $reservas) => $reservas
                ->unique('serie_id')
                ->take(self::REUNIOES_POR_GRUPO)
                ->map(fn (Reserva $reserva) => $this->descreverReuniao($reserva))
                ->values()
                ->all())
            ->all();
    }

    /**
     * Ex.: "Seg 19:00 · Sala de catequese".
     */
    private function descreverReuniao(Reserva $reserva): string
    {
        return sprintf(
            '%s %s · %s',
            self::DIAS[$reserva->data->dayOfWeek],
            substr($reserva->inicio, 0, 5),
            $reserva->espaco->nome,
        );
    }
}
