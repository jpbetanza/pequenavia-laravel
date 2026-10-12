<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Agenda\Conflito;
use App\Services\Agenda\VerificadorDeDisponibilidade;
use Carbon\CarbonImmutable;

/**
 * Mostra a ocupação de um espaço no dia. Com horário, também diz se o pedido cabe e, se não couber, sugere alternativas.
 */
final class VerificarDisponibilidade implements Ferramenta
{
    use ValidaArgumentos;

    public function __construct(
        private readonly ResolvedorDeCadastros $resolvedor,
        private readonly VerificadorDeDisponibilidade $verificador,
    ) {}

    public function nome(): string
    {
        return 'verificar_disponibilidade';
    }

    public function descricao(): string
    {
        return 'Verifica um espaço em uma data. Com inicio e fim, diz se o horário está livre e sugere outros espaços ou horários quando há conflito. Sem horário, mostra as reservas do dia.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'espaco' => ['type' => 'string', 'description' => 'Nome ou id do espaço.'],
                'data' => ['type' => 'string', 'description' => 'Dia, no formato AAAA-MM-DD.'],
                'inicio' => ['type' => 'string', 'description' => 'Horário de início, HH:MM. Opcional.'],
                'fim' => ['type' => 'string', 'description' => 'Horário de término, HH:MM. Opcional.'],
                'equipamentos' => [
                    'type' => 'array',
                    'description' => 'Equipamentos pedidos junto com o espaço.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'equipamento' => ['type' => 'string', 'description' => 'Nome ou id do equipamento.'],
                            'quantidade' => ['type' => 'integer', 'description' => 'Quantas unidades.'],
                        ],
                        'required' => ['equipamento', 'quantidade'],
                    ],
                ],
                'pessoas' => ['type' => 'integer', 'description' => 'Número de pessoas, para sugerir espaços com capacidade suficiente.'],
            ],
            'required' => ['espaco', 'data'],
        ];
    }

    public function status(): string
    {
        return 'Verificando a disponibilidade…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        foreach (['inicio', 'fim'] as $campo) {
            if (($argumentos[$campo] ?? null) === '') {
                $argumentos[$campo] = null;
            }
        }

        $dados = $this->validar($argumentos, [
            'espaco' => ['required', 'string'],
            'data' => ['required', 'date_format:Y-m-d'],
            'inicio' => ['nullable', 'date_format:H:i'],
            'fim' => ['nullable', 'date_format:H:i'],
            'equipamentos' => ['nullable', 'array'],
            'equipamentos.*.equipamento' => ['required', 'string'],
            'equipamentos.*.quantidade' => ['required', 'integer', 'min:1'],
            'pessoas' => ['nullable', 'integer', 'min:1'],
        ]);

        $espaco = $this->resolvedor->espaco($usuario, $dados['espaco']);
        $dia = CarbonImmutable::createFromFormat('Y-m-d', $dados['data'])->startOfDay();
        $equipamentos = collect($dados['equipamentos'] ?? [])
            ->map(fn (array $pedido) => [
                'id' => $this->resolvedor->equipamento($usuario, $pedido['equipamento'])->id,
                'quantidade' => (int) $pedido['quantidade'],
            ])
            ->values()
            ->all();

        $espaco->loadMissing('horarios');
        $horario = $espaco->horarios->firstWhere('dia_semana', $dia->dayOfWeek);
        $reservas = $this->reservasDoDia($usuario, $espaco->id, $dia);

        $cartaoDados = [
            'espaco' => ['id' => $espaco->id, 'nome' => $espaco->nome, 'cor' => $espaco->cor->value],
            'data' => $dia->toDateString(),
            'horario_do_dia' => $horario === null ? null : ['abre' => substr($horario->abre, 0, 5), 'fecha' => substr($horario->fecha, 0, 5)],
            'reservas' => $reservas,
            'pedido' => null,
        ];
        $paraOModelo = [
            'espaco' => $espaco->nome,
            'data' => $cartaoDados['data'],
            'horario_do_dia' => $cartaoDados['horario_do_dia'],
            'reservas' => $reservas,
        ];

        $comHorario = filled($dados['inicio'] ?? null);

        if ($comHorario !== filled($dados['fim'] ?? null)) {
            throw new ArgumentoInvalido('Informe início e fim, ou nenhum dos dois.');
        }

        if ($comHorario) {
            $conflitos = $this->verificador->verificar($espaco, $dia, $dados['inicio'], $dados['fim'], $equipamentos);
            $sugestoes = $conflitos === []
                ? ['espacos' => [], 'horario' => null]
                : $this->verificador->sugestoes($espaco, $dia, $dados['inicio'], $dados['fim'], $equipamentos, null, $dados['pessoas'] ?? null);

            $pedido = [
                'inicio' => $dados['inicio'],
                'fim' => $dados['fim'],
                'conflitos' => array_map(fn (Conflito $conflito) => $conflito->toArray(), $conflitos),
                'sugestoes' => $sugestoes,
            ];

            $cartaoDados['pedido'] = $pedido;
            $paraOModelo['livre'] = $conflitos === [];
            $paraOModelo['conflitos'] = $pedido['conflitos'];
            $paraOModelo['sugestoes'] = $pedido['sugestoes'];
        }

        return new ResultadoDeFerramenta(
            paraOModelo: $paraOModelo,
            cartao: ['tipo' => 'disponibilidade', 'dados' => $cartaoDados],
        );
    }

    /**
     * @return list<array{inicio: string, fim: string, titulo: string}>
     */
    private function reservasDoDia(User $usuario, string $espacoId, CarbonImmutable $dia): array
    {
        return Reserva::query()
            ->where('user_id', $usuario->id)
            ->where('espaco_id', $espacoId)
            ->where('data', $dia->toDateString())
            ->orderBy('inicio')
            ->get()
            ->map(fn (Reserva $reserva) => [
                'inicio' => substr($reserva->inicio, 0, 5),
                'fim' => substr($reserva->fim, 0, 5),
                'titulo' => $reserva->titulo,
            ])
            ->values()
            ->all();
    }
}
