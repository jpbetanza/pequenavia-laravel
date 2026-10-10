<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Só o dono da agenda vê, edita e exclui. Quem não é dono recebe 404, para não revelar que o registro existe.
 */
class GrupoPolicy
{
    public function view(User $user, Grupo $grupo): Response
    {
        return $this->responder($user, $grupo);
    }

    public function update(User $user, Grupo $grupo): Response
    {
        return $this->responder($user, $grupo);
    }

    public function delete(User $user, Grupo $grupo): Response
    {
        return $this->responder($user, $grupo);
    }

    private function responder(User $user, Grupo $grupo): Response
    {
        return $grupo->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
