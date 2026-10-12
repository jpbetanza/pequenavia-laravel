<?php

namespace App\Http\Controllers\Agendamentos;

use App\Enums\EscopoDaReserva;
use App\Enums\StatusSolicitacao;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\CalendarioRequest;
use App\Http\Requests\Agendamentos\DestroyReservaRequest;
use App\Http\Requests\Agendamentos\StoreReservaRequest;
use App\Http\Requests\Agendamentos\UpdateReservaRequest;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\EspacoHorario;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Agenda\ConflitosDeReservaException;
use App\Services\Agenda\CriadorDeReservas;
use App\Services\Agenda\Recorrencia;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendário e reservas da agenda do usuário. Reserva, espaço, grupo ou equipamento de outro dono responde 404.
 *
 * Toda gravação revalida as datas com o verificador de disponibilidade. Com conflito, nada é salvo e a resposta é 422.
 */
class ReservaController extends Controller
{
    public function __construct(private readonly CriadorDeReservas $criador) {}

    /**
     * Calendário da semana (padrão) ou do dia, com as reservas, espaços, equipamentos, grupos e solicitações pendentes do intervalo.
     */
    public function calendario(CalendarioRequest $request): Response
    {
        $user = $request->user();
        $fuso = config('agendamentos.fuso');
        $agora = CarbonImmutable::now($fuso);
        $dados = $request->validated();

        $data = isset($dados['data'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $dados['data'], $fuso)->startOfDay()
            : $agora->startOfDay();
        $visao = $dados['visao'] ?? 'semana';

        $semanaInicio = $data->startOfWeek(CarbonInterface::MONDAY);
        $semanaFim = $data->endOfWeek(CarbonInterface::SUNDAY);
        [$inicio, $fim] = $visao === 'dia' ? [$data, $data] : [$semanaInicio, $semanaFim];

        return Inertia::render('agendamentos/calendario', [
            'semana' => ['inicio' => $semanaInicio->toDateString(), 'fim' => $semanaFim->toDateString()],
            'visao' => $visao,
            'data' => $data->toDateString(),
            'reservas' => $this->reservasDoIntervalo($user, $inicio->toDateString(), $fim->toDateString()),
            'espacos' => $this->espacosDoIntervalo($user, $inicio->toDateString(), $fim->toDateString()),
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
            'grupos' => Grupo::query()
                ->where('user_id', $user->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(fn (Grupo $grupo) => ['id' => $grupo->id, 'nome' => $grupo->nome])
                ->values()
                ->all(),
            'solicitacoes' => $this->solicitacoesPendentesDoIntervalo($user, $inicio->toDateString(), $fim->toDateString()),
            'agora' => $agora->toIso8601String(),
        ]);
    }

    /**
     * Cria uma reserva ou a série inteira. Todas as datas são conferidas antes de gravar; qualquer conflito cancela tudo.
     */
    public function store(StoreReservaRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $espaco = $this->espacoDoDono($dados['espaco_id']);
        $grupo = $this->grupoDoDono($dados['grupo_id'] ?? null);
        $equipamentos = $this->equipamentosDoDono($dados['equipamentos']);
        $datas = $this->datasDaSerie($dados);

        $this->criador->criar($request->user(), $espaco, $grupo, $dados, $datas, $equipamentos);

        Inertia::flash('toast', ['type' => 'success', 'message' => count($datas) === 1 ? 'Reserva criada.' : count($datas).' reservas criadas.']);

        return to_route('agendamentos.calendario', ['data' => $datas[0]->toDateString()]);
    }

    /**
     * Edita a reserva. Com escopo "esta e as próximas", aplica a mesma mudança às reservas da série com data igual ou posterior,
     * mantendo cada data. A mudança é gravada e conferida dentro da transação: um conflito desfaz tudo.
     */
    public function update(UpdateReservaRequest $request, Reserva $reserva): RedirectResponse
    {
        $dados = $request->validated();
        $user = $request->user();
        $escopo = EscopoDaReserva::from($dados['escopo'] ?? EscopoDaReserva::SoEsta->value);
        $espaco = $this->espacoDoDono($dados['espaco_id']);
        $grupo = $this->grupoDoDono($dados['grupo_id'] ?? null);
        $equipamentos = $this->equipamentosDoDono($dados['equipamentos']);
        $membros = $this->membrosDaSerie($user, $reserva, $escopo);
        $espacosOriginais = $membros->mapWithKeys(fn (Reserva $membro) => [$membro->id => $membro->espaco_id])->all();

        DB::transaction(function () use ($user, $reserva, $espaco, $grupo, $dados, $equipamentos, $membros, $espacosOriginais): void {
            $this->criador->travarAgendaDo($user);

            foreach ($membros as $membro) {
                $ehEsta = $membro->is($reserva);

                $membro->fill([
                    'espaco_id' => $espaco->id,
                    'grupo_id' => $grupo?->id,
                    'titulo' => $dados['titulo'],
                    'inicio' => $dados['inicio'],
                    'fim' => $dados['fim'],
                    'pessoas' => $dados['pessoas'] ?? null,
                    'observacoes' => $dados['observacoes'] ?? null,
                    ...($ehEsta ? ['data' => $dados['data']] : []),
                ])->save();

                $membro->equipamentos()->sync($this->criador->pivotDosEquipamentos($equipamentos));
            }

            $conflitos = [];

            foreach ($membros as $membro) {
                $conflitosDaData = $this->criador->conflitosDaData(
                    $espaco,
                    CarbonImmutable::instance($membro->data)->startOfDay(),
                    [
                        'inicio' => $dados['inicio'],
                        'fim' => $dados['fim'],
                    ],
                    $equipamentos,
                    $membro->id,
                    ignorarEspacoInativo: $espacosOriginais[$membro->id] === $espaco->id,
                );

                if ($conflitosDaData !== null) {
                    $conflitos[] = $conflitosDaData;
                }
            }

            if ($conflitos !== []) {
                throw new ConflitosDeReservaException($conflitos);
            }
        });

        $reserva->refresh();

        Inertia::flash('toast', ['type' => 'success', 'message' => $membros->count() === 1 ? 'Reserva atualizada.' : 'Reservas atualizadas.']);

        return to_route('agendamentos.calendario', ['data' => $reserva->data->toDateString()]);
    }

    /**
     * Exclui a reserva. Com escopo "esta e as próximas", exclui também as da série com data igual ou posterior.
     * Os equipamentos saem junto (cascade).
     */
    public function destroy(DestroyReservaRequest $request, Reserva $reserva): RedirectResponse
    {
        $escopo = EscopoDaReserva::from($request->validated('escopo') ?? EscopoDaReserva::SoEsta->value);
        $membros = $this->membrosDaSerie($request->user(), $reserva, $escopo);
        $data = $reserva->data->toDateString();

        Reserva::query()->whereIn('id', $membros->pluck('id'))->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => $membros->count() === 1 ? 'Reserva excluída.' : 'Reservas excluídas.']);

        return to_route('agendamentos.calendario', ['data' => $data]);
    }

    /**
     * Reservas do dono no intervalo, em ordem de data e horário, com grupo e equipamentos (sem consulta por reserva).
     *
     * @return list<array<string, mixed>>
     */
    private function reservasDoIntervalo(User $user, string $inicio, string $fim): array
    {
        return Reserva::query()
            ->where('user_id', $user->id)
            ->whereBetween('data', [$inicio, $fim])
            ->with(['grupo:id,nome', 'equipamentos'])
            ->orderBy('data')
            ->orderBy('inicio')
            ->get()
            ->map(fn (Reserva $reserva) => [
                'id' => $reserva->id,
                'titulo' => $reserva->titulo,
                'data' => $reserva->data->toDateString(),
                'inicio' => substr($reserva->inicio, 0, 5),
                'fim' => substr($reserva->fim, 0, 5),
                'pessoas' => $reserva->pessoas,
                'observacoes' => $reserva->observacoes,
                'serie_id' => $reserva->serie_id,
                'espaco_id' => $reserva->espaco_id,
                'grupo_id' => $reserva->grupo_id,
                'grupo_nome' => $reserva->grupo?->nome,
                'equipamentos' => $reserva->equipamentos
                    ->map(fn (Equipamento $equipamento) => [
                        'id' => $equipamento->id,
                        'nome' => $equipamento->nome,
                        'quantidade' => (int) $equipamento->pivot->quantidade,
                        'entregue_em' => $this->iso($equipamento->pivot->entregue_em),
                        'recolhido_em' => $this->iso($equipamento->pivot->recolhido_em),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Espaços ativos do dono e os inativos que têm reserva no intervalo, com horários e os bloqueios que tocam o intervalo.
     *
     * @return list<array<string, mixed>>
     */
    private function espacosDoIntervalo(User $user, string $inicio, string $fim): array
    {
        return Espaco::query()
            ->where('user_id', $user->id)
            ->where(fn (Builder $consulta) => $consulta
                ->where('ativo', true)
                ->orWhereHas('reservas', fn (Builder $reservas) => $reservas->whereBetween('data', [$inicio, $fim])))
            ->with([
                'horarios',
                'bloqueios' => fn (HasMany $bloqueios) => $bloqueios
                    ->where('inicio', '<=', $fim)
                    ->where('fim', '>=', $inicio),
            ])
            ->orderBy('nome')
            ->get()
            ->map(fn (Espaco $espaco) => [
                'id' => $espaco->id,
                'nome' => $espaco->nome,
                'capacidade' => $espaco->capacidade,
                'ativo' => $espaco->ativo,
                'cor' => $espaco->cor->value,
                'recursos_fixos' => $espaco->recursos_fixos,
                'horarios' => $espaco->horarios
                    ->map(fn (EspacoHorario $horario) => [
                        'dia_semana' => $horario->dia_semana,
                        'abre' => substr($horario->abre, 0, 5),
                        'fecha' => substr($horario->fecha, 0, 5),
                    ])
                    ->values()
                    ->all(),
                'bloqueios' => $espaco->bloqueios
                    ->map(fn (EspacoBloqueio $bloqueio) => [
                        'id' => $bloqueio->id,
                        'inicio' => $bloqueio->inicio->toDateString(),
                        'fim' => $bloqueio->fim->toDateString(),
                        'motivo' => $bloqueio->motivo,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Solicitações pendentes cuja data cai no intervalo. O estado de conflito fica para a tela de solicitações.
     *
     * @return list<array<string, mixed>>
     */
    private function solicitacoesPendentesDoIntervalo(User $user, string $inicio, string $fim): array
    {
        return Solicitacao::query()
            ->where('user_id', $user->id)
            ->where('status', StatusSolicitacao::Pendente)
            ->whereBetween('data', [$inicio, $fim])
            ->orderBy('data')
            ->orderBy('inicio')
            ->get()
            ->map(fn (Solicitacao $solicitacao) => [
                'id' => $solicitacao->id,
                'titulo' => $solicitacao->titulo,
                'nome' => $solicitacao->nome,
                'telefone' => $solicitacao->telefone,
                'data' => $solicitacao->data->toDateString(),
                'inicio' => substr($solicitacao->inicio, 0, 5),
                'fim' => substr($solicitacao->fim, 0, 5),
                'pessoas' => $solicitacao->pessoas,
                'espaco_id' => $solicitacao->espaco_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Datas da série, sem as puladas. Limite de 52 ocorrências, vindo do Recorrencia.
     *
     * @param  array<string, mixed>  $dados
     * @return list<CarbonImmutable>
     */
    private function datasDaSerie(array $dados): array
    {
        $inicio = CarbonImmutable::createFromFormat('Y-m-d', $dados['data'])->startOfDay();
        $ate = isset($dados['ate']) ? CarbonImmutable::createFromFormat('Y-m-d', $dados['ate'])->startOfDay() : null;
        $pulos = $dados['pular'] ?? [];

        $datas = array_values(array_filter(
            Recorrencia::datas($inicio, $dados['repetir'], $ate),
            fn (CarbonImmutable $data) => ! in_array($data->toDateString(), $pulos, true),
        ));

        if ($datas === []) {
            throw ValidationException::withMessages([
                'pular' => 'Nenhuma data restante para reservar. Desmarque alguma das datas puladas.',
            ]);
        }

        return $datas;
    }

    /**
     * Reservas que a edição ou a exclusão atinge: só a própria, ou a série a partir da data dela.
     *
     * @return Collection<int, Reserva>
     */
    private function membrosDaSerie(User $user, Reserva $reserva, EscopoDaReserva $escopo): Collection
    {
        if ($escopo === EscopoDaReserva::SoEsta || $reserva->serie_id === null) {
            return collect([$reserva]);
        }

        return Reserva::query()
            ->where('user_id', $user->id)
            ->where('serie_id', $reserva->serie_id)
            ->where('data', '>=', $reserva->data->toDateString())
            ->orderBy('data')
            ->get();
    }

    private function espacoDoDono(string $id): Espaco
    {
        $espaco = Espaco::query()->findOrFail($id);
        Gate::authorize('view', $espaco);

        return $espaco;
    }

    private function grupoDoDono(?string $id): ?Grupo
    {
        if ($id === null) {
            return null;
        }

        $grupo = Grupo::query()->findOrFail($id);
        Gate::authorize('view', $grupo);

        return $grupo;
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
     * Horário gravado em UTC pelo banco, devolvido em ISO 8601 no fuso da agenda.
     */
    private function iso(?string $momento): ?string
    {
        if ($momento === null) {
            return null;
        }

        return CarbonImmutable::parse($momento, config('app.timezone'))
            ->setTimezone(config('agendamentos.fuso'))
            ->toIso8601String();
    }
}
