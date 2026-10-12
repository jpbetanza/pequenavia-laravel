<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\User;

/**
 * Espaços, equipamentos e grupos do usuário, para o modelo saber os nomes certos antes de consultar ou reservar.
 */
final class ListarCadastros implements Ferramenta
{
    public function nome(): string
    {
        return 'listar_cadastros';
    }

    public function descricao(): string
    {
        return 'Lista os espaços e grupos ativos e os equipamentos do usuário, com nomes, capacidade, horários e manutenção.';
    }

    public function parametros(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function status(): string
    {
        return 'Conferindo os cadastros…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $espacos = Espaco::query()
            ->where('user_id', $usuario->id)
            ->where('ativo', true)
            ->with('horarios')
            ->orderBy('nome')
            ->get()
            ->map(fn (Espaco $espaco) => [
                'id' => $espaco->id,
                'nome' => $espaco->nome,
                'capacidade' => $espaco->capacidade,
                'horarios' => $espaco->horarios
                    ->map(fn ($horario) => [
                        'dia_semana' => $horario->dia_semana,
                        'abre' => substr($horario->abre, 0, 5),
                        'fecha' => substr($horario->fecha, 0, 5),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $equipamentos = Equipamento::query()
            ->where('user_id', $usuario->id)
            ->orderBy('nome')
            ->get()
            ->map(fn (Equipamento $equipamento) => [
                'id' => $equipamento->id,
                'nome' => $equipamento->nome,
                'quantidade' => $equipamento->quantidade,
                'manutencao_ate' => $equipamento->manutencao_ate?->toDateString(),
            ])
            ->values()
            ->all();

        $grupos = Grupo::query()
            ->where('user_id', $usuario->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get()
            ->map(fn (Grupo $grupo) => ['id' => $grupo->id, 'nome' => $grupo->nome])
            ->values()
            ->all();

        return new ResultadoDeFerramenta(
            paraOModelo: ['espacos' => $espacos, 'equipamentos' => $equipamentos, 'grupos' => $grupos],
        );
    }
}
