<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\User;

/**
 * Ferramenta que o assistente pode chamar. Toda consulta roda com o usuário logado, nunca com um dono vindo do modelo.
 */
interface Ferramenta
{
    public function nome(): string;

    public function descricao(): string;

    /**
     * @return array<string, mixed> JSON Schema com type=object
     */
    public function parametros(): array;

    /**
     * Texto mostrado enquanto a ferramenta roda, ex.: "Consultando a agenda…".
     */
    public function status(): string;

    /**
     * @param  array<string, mixed>  $argumentos
     *
     * @throws ArgumentoInvalido
     */
    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta;
}
