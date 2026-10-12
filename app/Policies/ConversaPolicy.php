<?php

namespace App\Policies;

use App\Models\Conversa;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Só o dono vê, envia mensagens e exclui a conversa. Quem não é dono recebe 404.
 */
class ConversaPolicy
{
    public function view(User $user, Conversa $conversa): Response
    {
        return $this->responder($user, $conversa);
    }

    public function enviar(User $user, Conversa $conversa): Response
    {
        return $this->responder($user, $conversa);
    }

    public function delete(User $user, Conversa $conversa): Response
    {
        return $this->responder($user, $conversa);
    }

    private function responder(User $user, Conversa $conversa): Response
    {
        return $conversa->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
