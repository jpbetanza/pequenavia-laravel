<?php

namespace App\Http\Controllers;

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\GeracaoCertificado;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Agenda\CartaoDeSolicitacao;
use App\Services\Agenda\PainelDeEquipamentosDeHoje;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Início da secretaria: o dia nas salas, os pedidos pendentes, os equipamentos a separar e os certificados do mês.
 */
class InicioController extends Controller
{
    private const PEDIDOS_NO_INICIO = 3;

    public function __construct(
        private readonly CartaoDeSolicitacao $cartoes,
        private readonly PainelDeEquipamentosDeHoje $painelDeHoje,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $fuso = config('agendamentos.fuso');
        $agora = CarbonImmutable::now($fuso);
        $horaAtual = $agora->format('H:i');

        return Inertia::render('inicio', [
            'hoje' => [
                'data' => $agora->toDateString(),
                'rotulo' => $agora->locale('pt_BR')->isoFormat('dddd, D [de] MMMM'),
                'agora' => $horaAtual,
                'periodo' => match (true) {
                    $agora->hour < 12 => 'manha',
                    $agora->hour < 18 => 'tarde',
                    default => 'noite',
                },
            ],
            'reservas' => $this->reservasDeHoje($user, $agora->toDateString(), $horaAtual),
            'solicitacoes' => $this->solicitacoes($user),
            'equipamentos_hoje' => $this->painelDeHoje->montar($user, $agora),
            'certificados' => $this->certificados($user, $agora),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reservasDeHoje(User $user, string $data, string $horaAtual): array
    {
        return Reserva::query()
            ->where('user_id', $user->id)
            ->where('data', $data)
            ->with(['espaco:id,nome,cor', 'grupo:id,nome', 'equipamentos' => fn ($consulta) => $consulta->orderBy('equipamentos.nome')])
            ->orderBy('inicio')
            ->orderBy('titulo')
            ->get()
            ->map(function (Reserva $reserva) use ($horaAtual) {
                $inicio = substr($reserva->inicio, 0, 5);
                $fim = substr($reserva->fim, 0, 5);

                return [
                    'id' => $reserva->id,
                    'titulo' => $reserva->titulo,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'espaco' => ['nome' => $reserva->espaco->nome, 'cor' => $reserva->espaco->cor->value],
                    'grupo_nome' => $reserva->grupo?->nome,
                    'equipamentos' => $reserva->equipamentos
                        ->map(fn (Equipamento $equipamento) => ['nome' => $equipamento->nome, 'quantidade' => (int) $equipamento->pivot->quantidade])
                        ->values()
                        ->all(),
                    'situacao' => match (true) {
                        $fim <= $horaAtual => 'encerrada',
                        $inicio <= $horaAtual => 'em_andamento',
                        default => 'proxima',
                    },
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, primeiras: list<array<string, mixed>>}
     */
    private function solicitacoes(User $user): array
    {
        $pendentes = Solicitacao::query()
            ->where('user_id', $user->id)
            ->where('status', StatusSolicitacao::Pendente->value);

        return [
            'total' => (clone $pendentes)->count(),
            'primeiras' => $pendentes
                ->with(['grupo:id,nome', 'espaco', 'equipamentos'])
                ->orderBy('data')
                ->orderBy('inicio')
                ->limit(self::PEDIDOS_NO_INICIO)
                ->get()
                ->map(fn (Solicitacao $solicitacao) => $this->cartoes->montar($solicitacao) + [
                    'espaco_nome' => $solicitacao->espaco?->nome,
                    'espaco_cor' => $solicitacao->espaco?->cor->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{emitidos_no_mes: int, ultimo: array{modelo: string, gerado_em: string}|null}
     */
    private function certificados(User $user, CarbonImmutable $agora): array
    {
        $ultimo = GeracaoCertificado::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return [
            'emitidos_no_mes' => GeracaoCertificado::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [
                    $agora->startOfMonth()->utc(),
                    $agora->endOfMonth()->utc(),
                ])
                ->count(),
            'ultimo' => $ultimo === null ? null : [
                'modelo' => $ultimo->modelo_title,
                'gerado_em' => $ultimo->created_at->setTimezone(config('agendamentos.fuso'))->toIso8601String(),
            ],
        ];
    }
}
