<?php

namespace App\Policies;

use App\Models\AcaoProposta;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Só o dono da conversa confirma ou descarta a ação proposta. Quem não é dono recebe 404.
 */
class AcaoPropostaPolicy
{
    public function confirmar(User $user, AcaoProposta $acao): Response
    {
        return $this->responder($user, $acao);
    }

    public function descartar(User $user, AcaoProposta $acao): Response
    {
        return $this->responder($user, $acao);
    }

    private function responder(User $user, AcaoProposta $acao): Response
    {
        return $acao->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
