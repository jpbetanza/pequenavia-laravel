<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Agenda\PivotDoEquipamento;
use Carbon\CarbonImmutable;

/**
 * Lista as reservas do usuário em um período de até 31 dias, com filtro opcional de espaço e grupo.
 */
final class ConsultarAgenda implements Ferramenta
{
    use ValidaArgumentos;

    private const DIAS_MAXIMOS = 31;

    private const LIMITE_DE_RESERVAS = 100;

    public function __construct(private readonly ResolvedorDeCadastros $resolvedor) {}

    public function nome(): string
    {
        return 'consultar_agenda';
    }

    public function descricao(): string
    {
        return 'Lista as reservas da agenda do usuário em um período de no máximo 31 dias. Pode filtrar por espaço ou grupo.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'inicio' => ['type' => 'string', 'description' => 'Primeiro dia do período, no formato AAAA-MM-DD.'],
                'fim' => ['type' => 'string', 'description' => 'Último dia do período, no formato AAAA-MM-DD.'],
                'espaco' => ['type' => 'string', 'description' => 'Nome ou id do espaço, para filtrar.'],
                'grupo' => ['type' => 'string', 'description' => 'Nome ou id do grupo, para filtrar.'],
            ],
            'required' => ['inicio', 'fim'],
        ];
    }

    public function status(): string
    {
        return 'Consultando a agenda…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $dados = $this->validar($argumentos, [
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'espaco' => ['nullable', 'string'],
            'grupo' => ['nullable', 'string'],
        ]);

        $inicio = CarbonImmutable::createFromFormat('Y-m-d', $dados['inicio'])->startOfDay();
        $fim = CarbonImmutable::createFromFormat('Y-m-d', $dados['fim'])->startOfDay();

        if ((int) $inicio->diffInDays($fim) + 1 > self::DIAS_MAXIMOS) {
            throw new ArgumentoInvalido('O período pode ter no máximo '.self::DIAS_MAXIMOS.' dias.');
        }

        $espaco = isset($dados['espaco']) ? $this->resolvedor->espaco($usuario, $dados['espaco']) : null;
        $grupo = $this->resolvedor->grupo($usuario, $dados['grupo'] ?? null);

        $reservas = Reserva::query()
            ->where('user_id', $usuario->id)
            ->whereBetween('data', [$dados['inicio'], $dados['fim']])
            ->when($espaco, fn ($consulta) => $consulta->where('espaco_id', $espaco->id))
            ->when($grupo, fn ($consulta) => $consulta->where('grupo_id', $grupo->id))
            ->with(['espaco', 'grupo', 'equipamentos'])
            ->orderBy('data')
            ->orderBy('inicio')
            ->limit(self::LIMITE_DE_RESERVAS)
            ->get()
            ->map(fn (Reserva $reserva) => [
                'id' => $reserva->id,
                'titulo' => $reserva->titulo,
                'data' => $reserva->data->toDateString(),
                'inicio' => substr($reserva->inicio, 0, 5),
                'fim' => substr($reserva->fim, 0, 5),
                'espaco_nome' => $reserva->espaco->nome,
                'espaco_cor' => $reserva->espaco->cor->value,
                'grupo_nome' => $reserva->grupo?->nome,
                'serie' => $reserva->serie_id !== null,
                'equipamentos' => $reserva->equipamentos
                    ->map(fn (Equipamento $equipamento) => [
                        'nome' => $equipamento->nome,
                        'quantidade' => PivotDoEquipamento::quantidade($equipamento),
                        'entregue' => PivotDoEquipamento::entregueEm($equipamento) !== null,
                        'recolhido' => PivotDoEquipamento::recolhidoEm($equipamento) !== null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return new ResultadoDeFerramenta(
            paraOModelo: [
                'reservas' => array_map(fn (array $reserva) => [
                    'id' => $reserva['id'],
                    'titulo' => $reserva['titulo'],
                    'data' => $reserva['data'],
                    'inicio' => $reserva['inicio'],
                    'fim' => $reserva['fim'],
                    'espaco' => $reserva['espaco_nome'],
                    'grupo' => $reserva['grupo_nome'],
                    'serie' => $reserva['serie'],
                    'equipamentos' => $reserva['equipamentos'],
                ], $reservas),
            ],
            cartao: [
                'tipo' => 'reservas',
                'dados' => [
                    'inicio' => $dados['inicio'],
                    'fim' => $dados['fim'],
                    'reservas' => $reservas,
                ],
            ],
        );
    }
}
