<?php

namespace App\Policies;

use App\Models\Espaco;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Só o dono da agenda vê, edita e exclui. Quem não é dono recebe 404, para não revelar que o registro existe.
 */
class EspacoPolicy
{
    public function view(User $user, Espaco $espaco): Response
    {
        return $this->responder($user, $espaco);
    }

    public function update(User $user, Espaco $espaco): Response
    {
        return $this->responder($user, $espaco);
    }

    public function delete(User $user, Espaco $espaco): Response
    {
        return $this->responder($user, $espaco);
    }

    private function responder(User $user, Espaco $espaco): Response
    {
        return $espaco->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
