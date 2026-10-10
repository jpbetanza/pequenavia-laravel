<?php

namespace App\Http\Requests\Agendamentos;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

class UpdateGrupoRequest extends StoreGrupoRequest
{
    /**
     * A autorização vem antes da validação: quem não é dono recebe 404 mesmo com dados inválidos.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('grupo'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->regras($this->route('grupo'));
    }
}
