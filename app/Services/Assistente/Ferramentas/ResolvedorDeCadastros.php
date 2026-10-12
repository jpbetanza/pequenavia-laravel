<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Acha um cadastro do usuário pelo id ou pelo nome. O nome ignora maiúsculas e acentos.
 * Cadastro de outro usuário não é encontrado, então a mensagem de erro é a mesma de um nome inexistente.
 */
final class ResolvedorDeCadastros
{
    /**
     * @throws ArgumentoInvalido
     */
    public function espaco(User $usuario, string $idOuNome): Espaco
    {
        /** @var Espaco|null $espaco */
        $espaco = $this->encontrar(Espaco::query()->where('user_id', $usuario->id), $idOuNome);

        return $espaco ?? throw new ArgumentoInvalido("Não encontrei o espaço “{$idOuNome}”. Use listar_cadastros para ver os nomes.");
    }

    /**
     * @throws ArgumentoInvalido
     */
    public function grupo(User $usuario, ?string $idOuNome): ?Grupo
    {
        if ($idOuNome === null) {
            return null;
        }

        /** @var Grupo|null $grupo */
        $grupo = $this->encontrar(Grupo::query()->where('user_id', $usuario->id), $idOuNome);

        return $grupo ?? throw new ArgumentoInvalido("Não encontrei o grupo “{$idOuNome}”. Use listar_cadastros para ver os nomes.");
    }

    /**
     * @throws ArgumentoInvalido
     */
    public function equipamento(User $usuario, string $idOuNome): Equipamento
    {
        /** @var Equipamento|null $equipamento */
        $equipamento = $this->encontrar(Equipamento::query()->where('user_id', $usuario->id), $idOuNome);

        return $equipamento ?? throw new ArgumentoInvalido("Não encontrei o equipamento “{$idOuNome}”. Use listar_cadastros para ver os nomes.");
    }

    /**
     * @param  Builder<Model>  $consulta
     */
    private function encontrar(Builder $consulta, string $idOuNome): ?Model
    {
        if (Str::isUuid($idOuNome)) {
            return (clone $consulta)->whereKey($idOuNome)->first();
        }

        $procurado = $this->normalizar($idOuNome);

        return $consulta->get()->first(fn (Model $cadastro) => $this->normalizar((string) $cadastro->getAttribute('nome')) === $procurado);
    }

    private function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }
}
