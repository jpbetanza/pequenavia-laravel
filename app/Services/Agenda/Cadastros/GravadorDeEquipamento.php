<?php

namespace App\Services\Agenda\Cadastros;

use App\Models\Equipamento;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Grava equipamentos para a tela e para o assistente. Quem chama confere a autorização.
 */
final class GravadorDeEquipamento
{
    /**
     * @param  array<string, mixed>  $dados  já validados
     */
    public function criar(User $dono, array $dados): Equipamento
    {
        return $dono->equipamentos()->create($dados);
    }

    /**
     * @param  array<string, mixed>  $dados  já validados
     */
    public function atualizar(Equipamento $equipamento, array $dados): Equipamento
    {
        $equipamento->fill($dados)->save();

        return $equipamento;
    }

    /**
     * Exclui o equipamento, mas só sem reservas. A FK de reserva_equipamento é DEFERRABLE, então a checagem vem antes, no código.
     *
     * @throws ValidationException com a mensagem de `motivoParaNaoExcluir`
     */
    public function excluir(Equipamento $equipamento): void
    {
        $motivo = $this->motivoParaNaoExcluir($equipamento);

        if ($motivo !== null) {
            throw ValidationException::withMessages(['equipamento' => $motivo]);
        }

        $equipamento->delete();
    }

    /**
     * Mensagem que impede a exclusão, ou null quando o equipamento não tem reservas.
     */
    public function motivoParaNaoExcluir(Equipamento $equipamento): ?string
    {
        $reservas = $equipamento->reservas();
        $hoje = now(config('agendamentos.fuso'))->toDateString();

        if ((clone $reservas)->where('reservas.data', '>=', $hoje)->exists()) {
            return 'Há reservas futuras usando este equipamento. Desative em vez de excluir.';
        }

        if ($reservas->exists()) {
            return 'Há reservas usando este equipamento. Desative em vez de excluir.';
        }

        return null;
    }
}
