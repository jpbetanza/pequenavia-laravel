<?php

namespace App\Services\Agenda\Cadastros;

use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;

/**
 * Grava grupos e pastorais para a tela e para o assistente. Quem chama confere a autorização.
 */
final class GravadorDeGrupo
{
    /**
     * @param  array<string, mixed>  $dados  já validados
     */
    public function criar(User $dono, array $dados): Grupo
    {
        return $dono->grupos()->create($dados);
    }

    /**
     * @param  array<string, mixed>  $dados  já validados
     */
    public function atualizar(Grupo $grupo, array $dados): Grupo
    {
        $grupo->fill($dados)->save();

        return $grupo;
    }

    /**
     * Exclui o grupo. As reservas dele continuam na agenda, sem grupo (a FK zera o campo).
     */
    public function excluir(Grupo $grupo): void
    {
        $grupo->delete();
    }

    /**
     * Reservas do grupo a partir de hoje, no fuso da agenda: as que a exclusão ou a troca de dados afetariam.
     */
    public function reservasAfetadas(Grupo $grupo): int
    {
        $hoje = now(config('agendamentos.fuso'))->toDateString();

        return Reserva::query()
            ->where('grupo_id', $grupo->id)
            ->where('data', '>=', $hoje)
            ->count();
    }
}
