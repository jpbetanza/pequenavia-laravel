<?php

namespace App\Services\Assistente\Ferramentas;

use Illuminate\Support\Facades\Validator;

trait ValidaArgumentos
{
    /**
     * @param  array<string, mixed>  $argumentos
     * @param  array<string, mixed>  $regras
     * @return array<string, mixed>
     *
     * @throws ArgumentoInvalido
     */
    protected function validar(array $argumentos, array $regras): array
    {
        $validador = Validator::make($argumentos, $regras, [
            'date_format' => 'O campo :attribute precisa estar no formato :format.',
            'required' => 'O campo :attribute é obrigatório.',
            'after' => 'O campo :attribute precisa ser depois de :date.',
            'after_or_equal' => 'O campo :attribute precisa ser igual ou depois de :date.',
            'in' => 'O campo :attribute tem um valor inválido.',
            'integer' => 'O campo :attribute precisa ser um número inteiro.',
            'max' => 'O campo :attribute passou do limite.',
            'min' => 'O campo :attribute está abaixo do mínimo.',
        ]);

        if ($validador->fails()) {
            throw new ArgumentoInvalido($validador->errors()->first());
        }

        return $validador->validated();
    }
}
