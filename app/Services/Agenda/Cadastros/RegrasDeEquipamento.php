<?php

namespace App\Services\Agenda\Cadastros;

use App\Models\Equipamento;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Regras de validação de um equipamento. O nome é único só entre os equipamentos do dono.
 */
final class RegrasDeEquipamento
{
    /**
     * @return array<string, mixed>
     */
    public static function regras(User $dono, ?Equipamento $atual = null): array
    {
        $nomeUnico = Rule::unique('equipamentos', 'nome')
            ->where('user_id', $dono->id)
            ->ignore($atual?->id);

        return [
            'nome' => ['required', 'string', 'max:120', $nomeUnico],
            'descricao' => ['nullable', 'string', 'max:160'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:1000'],
            'local_guarda' => ['nullable', 'string', 'max:120'],
            'manutencao_ate' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensagens(): array
    {
        return [
            'nome.required' => 'Informe o nome do equipamento.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'nome.unique' => 'Já existe um equipamento com este nome.',
            'descricao.max' => 'A descrição pode ter no máximo 160 caracteres.',
            'quantidade.required' => 'Informe quantas unidades existem.',
            'quantidade.integer' => 'A quantidade precisa ser um número inteiro.',
            'quantidade.min' => 'Informe pelo menos uma unidade.',
            'quantidade.max' => 'A quantidade pode ser no máximo 1000.',
            'local_guarda.max' => 'O local de guarda pode ter no máximo 120 caracteres.',
            'manutencao_ate.date_format' => 'Informe a data de manutenção no formato dia/mês/ano.',
        ];
    }
}
