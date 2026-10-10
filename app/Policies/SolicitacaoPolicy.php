<?php

namespace App\Policies;

use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Só o dono da agenda vê, edita e exclui. Quem não é dono recebe 404, para não revelar que o registro existe.
 */
class SolicitacaoPolicy
{
    public function view(User $user, Solicitacao $solicitacao): Response
    {
        return $this->responder($user, $solicitacao);
    }

    public function update(User $user, Solicitacao $solicitacao): Response
    {
        return $this->responder($user, $solicitacao);
    }

    public function delete(User $user, Solicitacao $solicitacao): Response
    {
        return $this->responder($user, $solicitacao);
    }

    private function responder(User $user, Solicitacao $solicitacao): Response
    {
        return $solicitacao->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
