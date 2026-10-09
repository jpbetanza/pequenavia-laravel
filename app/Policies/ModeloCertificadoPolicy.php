<?php

namespace App\Policies;

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Quem não pode ver o modelo recebe 404 em qualquer ação, para não revelar que ele existe.
 * Quem pode ver, mas não tem a permissão da ação, recebe 403.
 */
class ModeloCertificadoPolicy
{
    public function view(User $user, ModeloCertificado $modelo): Response
    {
        return $this->responder($this->podeVer($user, $modelo), $user, $modelo);
    }

    public function gerar(User $user, ModeloCertificado $modelo): Response
    {
        return $this->view($user, $modelo);
    }

    public function update(User $user, ModeloCertificado $modelo): Response
    {
        return $this->responder($this->podeEditar($user, $modelo), $user, $modelo);
    }

    public function editarCampos(User $user, ModeloCertificado $modelo): Response
    {
        return $this->update($user, $modelo);
    }

    public function gerenciarCompartilhamentos(User $user, ModeloCertificado $modelo): Response
    {
        return $this->responder($this->ehDono($user, $modelo), $user, $modelo);
    }

    public function delete(User $user, ModeloCertificado $modelo): Response
    {
        return $this->responder($this->ehDono($user, $modelo), $user, $modelo);
    }

    private function responder(bool $permitido, User $user, ModeloCertificado $modelo): Response
    {
        if ($permitido) {
            return Response::allow();
        }

        return $this->podeVer($user, $modelo) ? Response::deny() : Response::denyAsNotFound();
    }

    private function podeVer(User $user, ModeloCertificado $modelo): bool
    {
        return $this->ehDono($user, $modelo) || $this->compartilhamentoAtivo($user, $modelo) !== null;
    }

    private function podeEditar(User $user, ModeloCertificado $modelo): bool
    {
        return $this->ehDono($user, $modelo)
            || $this->compartilhamentoAtivo($user, $modelo)?->pode_editar === true;
    }

    private function ehDono(User $user, ModeloCertificado $modelo): bool
    {
        return $modelo->user_id === $user->id;
    }

    /**
     * O compartilhamento que dá ao usuário acesso a este modelo, ou null se não houver.
     *
     * O compartilhamento é por email, e o email do usuário e o do compartilhamento já são
     * guardados em minúsculas. A regra de segurança é o email verificado: sem ela, qualquer
     * pessoa poderia criar uma conta com o email de outra e ver os modelos compartilhados com ela.
     */
    private function compartilhamentoAtivo(User $user, ModeloCertificado $modelo): ?CompartilhamentoModelo
    {
        if (! $user->hasVerifiedEmail()) {
            return null;
        }

        return $modelo->compartilhamentos()->where('email', $user->email)->first();
    }
}
