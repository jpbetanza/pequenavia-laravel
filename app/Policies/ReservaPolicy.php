<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Só o dono da agenda vê, edita e exclui. Quem não é dono recebe 404, para não revelar que o registro existe.
 */
class ReservaPolicy
{
    public function view(User $user, Reserva $reserva): Response
    {
        return $this->responder($user, $reserva);
    }

    public function update(User $user, Reserva $reserva): Response
    {
        return $this->responder($user, $reserva);
    }

    public function delete(User $user, Reserva $reserva): Response
    {
        return $this->responder($user, $reserva);
    }

    private function responder(User $user, Reserva $reserva): Response
    {
        return $reserva->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
