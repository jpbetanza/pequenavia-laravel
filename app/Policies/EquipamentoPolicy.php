<?php

namespace App\Policies;

use App\Models\Equipamento;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Só o dono da agenda vê, edita e exclui. Quem não é dono recebe 404, para não revelar que o registro existe.
 */
class EquipamentoPolicy
{
    public function view(User $user, Equipamento $equipamento): Response
    {
        return $this->responder($user, $equipamento);
    }

    public function update(User $user, Equipamento $equipamento): Response
    {
        return $this->responder($user, $equipamento);
    }

    public function delete(User $user, Equipamento $equipamento): Response
    {
        return $this->responder($user, $equipamento);
    }

    private function responder(User $user, Equipamento $equipamento): Response
    {
        return $equipamento->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
