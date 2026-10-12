<?php

namespace App\Http\Controllers\Agendamentos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\StoreEquipamentoRequest;
use App\Http\Requests\Agendamentos\UpdateEquipamentoRequest;
use App\Models\Equipamento;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeEquipamento;
use App\Services\Agenda\UsoDeEquipamento;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Equipamentos da agenda do usuário. Equipamento de outro dono responde 404.
 */
class EquipamentoController extends Controller
{
    /** Minutos de antecedência com que o equipamento sai para a reserva. */
    private const ANTECEDENCIA_DA_SEPARACAO_MIN = 15;

    /**
     * Lista os equipamentos do dono por nome, com o estado de agora, o uso da semana e o painel de hoje.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $agora = now(config('agendamentos.fuso'));
        $hoje = $agora->toDateString();
        $horaAtual = $agora->format('H:i');
        $inicioDaSemana = CarbonImmutable::instance($agora)->startOfWeek(CarbonInterface::MONDAY);

        $usosPorEquipamento = $this->usosPorEquipamento($user, $inicioDaSemana, $inicioDaSemana->addDays(6));

        $equipamentos = Equipamento::query()
            ->where('user_id', $user->id)
            ->orderBy('nome')
            ->get();

        return Inertia::render('agendamentos/equipamentos/index', [
            'equipamentos' => $equipamentos->map(function (Equipamento $equipamento) use ($usosPorEquipamento, $hoje, $horaAtual, $inicioDaSemana) {
                $usos = $usosPorEquipamento->get($equipamento->id, []);
                $usosDeHoje = array_values(array_filter($usos, fn (array $uso) => $uso['data'] === $hoje));
                $manutencaoAte = $equipamento->manutencao_ate?->toDateString();
                $emManutencao = $manutencaoAte !== null && $manutencaoAte >= $hoje;

                return [
                    'id' => $equipamento->id,
                    'nome' => $equipamento->nome,
                    'descricao' => $equipamento->descricao,
                    'quantidade' => $equipamento->quantidade,
                    'local_guarda' => $equipamento->local_guarda,
                    'manutencao_ate' => $manutencaoAte,
                    'em_manutencao' => $emManutencao,
                    'agora' => $this->agora($equipamento, $emManutencao, $usosDeHoje, $horaAtual),
                    'semana' => $this->semana($equipamento, $usos, $inicioDaSemana),
                ];
            })->values(),
            'hoje' => $this->painelDeHoje($request->user(), $agora),
        ]);
    }

    /**
     * Cria o equipamento do dono logado.
     */
    public function store(StoreEquipamentoRequest $request, GravadorDeEquipamento $gravador): RedirectResponse
    {
        $gravador->criar($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipamento criado.']);

        return to_route('agendamentos.equipamentos.index');
    }

    /**
     * Atualiza os dados do equipamento.
     */
    public function update(UpdateEquipamentoRequest $request, Equipamento $equipamento, GravadorDeEquipamento $gravador): RedirectResponse
    {
        $gravador->atualizar($equipamento, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipamento atualizado.']);

        return to_route('agendamentos.equipamentos.index');
    }

    /**
     * Exclui o equipamento. A checagem de reservas fica no gravador.
     */
    public function destroy(Equipamento $equipamento, GravadorDeEquipamento $gravador): RedirectResponse
    {
        Gate::authorize('delete', $equipamento);

        $gravador->excluir($equipamento);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipamento excluído.']);

        return to_route('agendamentos.equipamentos.index');
    }

    /**
     * Uso de cada equipamento nas reservas da semana, uma lista por equipamento.
     *
     * @return Collection<string, list<array{data: string, inicio: string, fim: string, quantidade: int, espaco: string}>>
     */
    private function usosPorEquipamento(User $user, CarbonImmutable $inicio, CarbonImmutable $fim): Collection
    {
        $reservas = Reserva::query()
            ->where('user_id', $user->id)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->whereHas('equipamentos')
            ->with(['equipamentos', 'espaco:id,nome'])
            ->get();

        $usos = [];

        foreach ($reservas as $reserva) {
            foreach ($reserva->equipamentos as $equipamento) {
                $usos[$equipamento->id][] = [
                    'data' => $reserva->data->toDateString(),
                    'inicio' => substr($reserva->inicio, 0, 5),
                    'fim' => substr($reserva->fim, 0, 5),
                    'quantidade' => (int) $equipamento->pivot->quantidade,
                    'espaco' => $reserva->espaco->nome,
                ];
            }
        }

        return collect($usos);
    }

    /**
     * Estado de agora: em manutenção, em uso (com a contagem e os espaços) ou livre.
     *
     * @param  list<array{data: string, inicio: string, fim: string, quantidade: int, espaco: string}>  $usosDeHoje
     * @return array{situacao: string, em_uso: int, total: int, livres: int, espacos: list<string>, ate: string|null}
     */
    private function agora(Equipamento $equipamento, bool $emManutencao, array $usosDeHoje, string $horaAtual): array
    {
        if ($emManutencao) {
            return [
                'situacao' => 'manutencao',
                'em_uso' => 0,
                'total' => $equipamento->quantidade,
                'livres' => 0,
                'espacos' => [],
                'ate' => $equipamento->manutencao_ate->format('d/m'),
            ];
        }

        $cobrindoAgora = array_values(array_filter(
            $usosDeHoje,
            fn (array $uso) => UsoDeEquipamento::emUsoNoHorario([$uso], $horaAtual) > 0,
        ));
        $emUso = UsoDeEquipamento::emUsoNoHorario($cobrindoAgora, $horaAtual);

        return [
            'situacao' => $emUso > 0 ? 'em_uso' : 'livre',
            'em_uso' => $emUso,
            'total' => $equipamento->quantidade,
            'livres' => max(0, $equipamento->quantidade - $emUso),
            'espacos' => array_values(array_unique(array_column($cobrindoAgora, 'espaco'))),
            'ate' => null,
        ];
    }

    /**
     * Sete células, de segunda a domingo, com o pico simultâneo de cada dia.
     *
     * @param  list<array{data: string, inicio: string, fim: string, quantidade: int, espaco: string}>  $usos
     * @return list<array{data: string, pico: int, quantidade: int}>
     */
    private function semana(Equipamento $equipamento, array $usos, CarbonImmutable $inicioDaSemana): array
    {
        return collect(range(0, 6))
            ->map(function (int $deslocamento) use ($equipamento, $usos, $inicioDaSemana) {
                $data = $inicioDaSemana->addDays($deslocamento)->toDateString();
                $usosDoDia = array_values(array_filter($usos, fn (array $uso) => $uso['data'] === $data));

                return [
                    'data' => $data,
                    'pico' => UsoDeEquipamento::picoDoDia($usosDoDia),
                    'quantidade' => $equipamento->quantidade,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Levar (início − 15 min) e recolher (fim) de cada reserva de hoje que tem equipamento, em ordem de horário.
     *
     * @return array{rotulo: string, movimentos: list<array<string, mixed>>}
     */
    private function painelDeHoje(User $user, CarbonInterface $agora): array
    {
        $fuso = config('agendamentos.fuso');

        $reservas = Reserva::query()
            ->where('user_id', $user->id)
            ->where('data', $agora->toDateString())
            ->whereHas('equipamentos')
            ->with(['equipamentos' => fn ($consulta) => $consulta->orderBy('equipamentos.nome'), 'espaco:id,nome'])
            ->get();

        $movimentos = $reservas
            ->flatMap(function (Reserva $reserva) use ($fuso) {
                $inicio = CarbonImmutable::parse($reserva->data->toDateString().' '.$reserva->inicio, $fuso);
                $fim = CarbonImmutable::parse($reserva->data->toDateString().' '.$reserva->fim, $fuso);

                return [
                    $this->movimento($reserva, 'levar', $inicio->subMinutes(self::ANTECEDENCIA_DA_SEPARACAO_MIN)->format('H:i')),
                    $this->movimento($reserva, 'recolher', $fim->format('H:i')),
                ];
            })
            ->sortBy([['horario', 'asc'], ['titulo', 'asc']])
            ->values()
            ->all();

        return [
            'rotulo' => $agora->locale('pt_BR')->isoFormat('dddd, D [de] MMMM'),
            'movimentos' => $movimentos,
        ];
    }

    /**
     * @return array{chave: string, acao: string, horario: string, titulo: string, espaco: string, reserva_id: string, itens: list<array{equipamento_id: string, nome: string, quantidade: int, marcado: bool}>}
     */
    private function movimento(Reserva $reserva, string $acao, string $horario): array
    {
        $coluna = $acao === 'levar' ? 'entregue_em' : 'recolhido_em';

        return [
            'chave' => "{$reserva->id}-{$acao}",
            'acao' => $acao,
            'horario' => $horario,
            'titulo' => $reserva->titulo,
            'espaco' => $reserva->espaco->nome,
            'reserva_id' => $reserva->id,
            'itens' => $reserva->equipamentos->map(fn (Equipamento $equipamento) => [
                'equipamento_id' => $equipamento->id,
                'nome' => $equipamento->nome,
                'quantidade' => (int) $equipamento->pivot->quantidade,
                'marcado' => $equipamento->pivot->{$coluna} !== null,
            ])->values()->all(),
        ];
    }
}
