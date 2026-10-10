<?php

namespace App\Http\Controllers\Agendamentos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\StoreEspacoRequest;
use App\Http\Requests\Agendamentos\UpdateEspacoRequest;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\EspacoHorario;
use App\Models\Reserva;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Espaços da agenda do usuário. Espaço de outro dono responde 404.
 */
class EspacoController extends Controller
{
    /**
     * Lista os espaços do dono por nome, com o estado de hoje e as reservas da semana.
     */
    public function index(Request $request): Response
    {
        $fuso = config('agendamentos.fuso');
        $agora = now($fuso);
        $hoje = $agora->toDateString();
        $inicioDaSemana = $agora->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
        $fimDaSemana = $agora->copy()->endOfWeek(CarbonInterface::SUNDAY)->toDateString();

        $espacos = Espaco::query()
            ->where('user_id', $request->user()->id)
            ->withCount(['reservas as reservas_na_semana' => fn (Builder $reservas) => $reservas
                ->whereBetween('data', [$inicioDaSemana, $fimDaSemana])])
            ->with(['horarios', 'bloqueios'])
            ->orderBy('nome')
            ->get();

        return Inertia::render('agendamentos/espacos/index', [
            'espacos' => $espacos->map(function (Espaco $espaco) use ($hoje) {
                $bloqueioAtual = $espaco->ativo ? $this->bloqueioQueCobre($espaco, $hoje) : null;

                return [
                    'id' => $espaco->id,
                    'nome' => $espaco->nome,
                    'capacidade' => $espaco->capacidade,
                    'localizacao' => $espaco->localizacao,
                    'cor' => $espaco->cor->value,
                    'recursos_fixos' => $espaco->recursos_fixos,
                    'ativo' => $espaco->ativo,
                    'situacao' => $this->situacao($espaco, $bloqueioAtual),
                    'bloqueio_atual' => $bloqueioAtual === null ? null : [
                        'motivo' => $bloqueioAtual->motivo,
                        'ate' => $bloqueioAtual->fim->format('d/m'),
                    ],
                    'horarios' => $espaco->horarios->map(fn (EspacoHorario $horario) => $this->horario($horario))->values(),
                    'reservas_na_semana' => (int) $espaco->reservas_na_semana,
                ];
            })->values(),
        ]);
    }

    /**
     * Formulário de criação de um espaço.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('agendamentos/espacos/form', [
            'espaco' => null,
            'recursosSugeridos' => $this->recursosSugeridos($request->user()),
        ]);
    }

    /**
     * Cria o espaço do dono logado, com horários e bloqueios, em uma transação.
     */
    public function store(StoreEspacoRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $espaco = $request->user()->espacos()->create($this->atributos($request->validated()));

            $this->gravarHorariosEBloqueios($espaco, $request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Espaço criado.']);

        return to_route('agendamentos.espacos.index');
    }

    /**
     * Formulário de edição, com os horários e bloqueios do espaço.
     */
    public function edit(Request $request, Espaco $espaco): Response
    {
        Gate::authorize('update', $espaco);

        $espaco->load(['horarios', 'bloqueios']);

        return Inertia::render('agendamentos/espacos/form', [
            'espaco' => [
                'id' => $espaco->id,
                'nome' => $espaco->nome,
                'capacidade' => $espaco->capacidade,
                'localizacao' => $espaco->localizacao,
                'cor' => $espaco->cor->value,
                'recursos_fixos' => $espaco->recursos_fixos,
                'aceita_solicitacoes' => $espaco->aceita_solicitacoes,
                'antecedencia_min_horas' => $espaco->antecedencia_min_horas,
                'duracao_max_min' => $espaco->duracao_max_min,
                'intervalo_min' => $espaco->intervalo_min,
                'ativo' => $espaco->ativo,
                'horarios' => $espaco->horarios->map(fn (EspacoHorario $horario) => $this->horario($horario))->values(),
                'bloqueios' => $espaco->bloqueios->map(fn (EspacoBloqueio $bloqueio) => [
                    'inicio' => $bloqueio->inicio->toDateString(),
                    'fim' => $bloqueio->fim->toDateString(),
                    'motivo' => $bloqueio->motivo,
                ])->values(),
            ],
            'recursosSugeridos' => $this->recursosSugeridos($request->user()),
        ]);
    }

    /**
     * Atualiza o espaço. Horários e bloqueios enviados substituem os anteriores.
     */
    public function update(UpdateEspacoRequest $request, Espaco $espaco): RedirectResponse
    {
        DB::transaction(function () use ($request, $espaco) {
            $espaco->fill($this->atributos($request->validated()))->save();

            $espaco->horarios()->delete();
            $espaco->bloqueios()->delete();

            $this->gravarHorariosEBloqueios($espaco, $request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Espaço atualizado.']);

        return to_route('agendamentos.espacos.index');
    }

    /**
     * Exclui o espaço, mas só sem reservas. A FK de reservas é restrita, então a checagem vem antes.
     */
    public function destroy(Espaco $espaco): RedirectResponse
    {
        Gate::authorize('delete', $espaco);

        $reservas = Reserva::query()->where('espaco_id', $espaco->id);
        $hoje = now(config('agendamentos.fuso'))->toDateString();

        if ((clone $reservas)->where('data', '>=', $hoje)->exists()) {
            throw ValidationException::withMessages([
                'espaco' => 'Há reservas futuras usando este espaço. Desative em vez de excluir.',
            ]);
        }

        if ($reservas->exists()) {
            throw ValidationException::withMessages([
                'espaco' => 'Há reservas usando este espaço. Desative em vez de excluir.',
            ]);
        }

        $espaco->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Espaço excluído.']);

        return to_route('agendamentos.espacos.index');
    }

    /**
     * Situação de exibição: inativo tem prioridade, depois bloqueado por um bloqueio de hoje, senão ativo.
     */
    private function situacao(Espaco $espaco, ?EspacoBloqueio $bloqueioAtual): string
    {
        if (! $espaco->ativo) {
            return 'inativo';
        }

        return $bloqueioAtual === null ? 'ativo' : 'bloqueado';
    }

    /**
     * O primeiro bloqueio que cobre a data informada, ou null.
     */
    private function bloqueioQueCobre(Espaco $espaco, string $dia): ?EspacoBloqueio
    {
        return $espaco->bloqueios->first(fn (EspacoBloqueio $bloqueio) => $bloqueio->inicio->toDateString() <= $dia
            && $bloqueio->fim->toDateString() >= $dia);
    }

    /**
     * @return array{dia_semana: int, abre: string, fecha: string}
     */
    private function horario(EspacoHorario $horario): array
    {
        return [
            'dia_semana' => $horario->dia_semana,
            'abre' => substr($horario->abre, 0, 5),
            'fecha' => substr($horario->fecha, 0, 5),
        ];
    }

    /**
     * Recursos já usados nos espaços do dono, para virarem sugestão no formulário.
     *
     * @return list<string>
     */
    private function recursosSugeridos(User $user): array
    {
        return Espaco::query()
            ->where('user_id', $user->id)
            ->pluck('recursos_fixos')
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Atributos da tabela `espacos`, sem as listas de horários e bloqueios.
     *
     * @param  array<string, mixed>  $validado
     * @return array<string, mixed>
     */
    private function atributos(array $validado): array
    {
        return Arr::except($validado, ['horarios', 'bloqueios']);
    }

    /**
     * @param  array<string, mixed>  $validado
     */
    private function gravarHorariosEBloqueios(Espaco $espaco, array $validado): void
    {
        $espaco->horarios()->createMany($validado['horarios'] ?? []);
        $espaco->bloqueios()->createMany($validado['bloqueios'] ?? []);
    }
}
