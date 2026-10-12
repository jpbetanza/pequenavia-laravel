<?php

namespace App\Services\Agenda\Cadastros;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Regras de validação de um grupo ou pastoral. O nome é único só entre os grupos do dono.
 */
final class RegrasDeGrupo
{
    /**
     * @return array<string, mixed>
     */
    public static function regras(User $dono, ?Grupo $atual = null): array
    {
        $nomeUnico = Rule::unique('grupos', 'nome')
            ->where('user_id', $dono->id)
            ->ignore($atual?->id);

        return [
            'nome' => ['required', 'string', 'max:120', $nomeUnico],
            'coordenacao' => ['nullable', 'string', 'max:120'],
            'contato' => ['nullable', 'string', 'max:120'],
            'observacao' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensagens(): array
    {
        return [
            'nome.required' => 'Informe o nome do grupo.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'nome.unique' => 'Já existe um grupo com este nome.',
            'coordenacao.max' => 'A coordenação pode ter no máximo 120 caracteres.',
            'contato.max' => 'O contato pode ter no máximo 120 caracteres.',
            'observacao.string' => 'A observação precisa ser um texto.',
            'ativo.boolean' => 'Informe se o grupo está ativo.',
        ];
    }
}
